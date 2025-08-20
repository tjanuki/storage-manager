<?php

namespace App\Console\Commands;

use App\Jobs\ImportLocalVideo;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportLocalVideosQueue extends Command
{
    protected $signature = 'videos:import-local-queue 
                            {--path= : Path to directory containing video files (default: storage/app/import/videos)}
                            {--with-metadata : Look for JSON metadata files alongside videos}
                            {--dry-run : List videos without importing}
                            {--limit= : Limit number of videos to import}
                            {--queue=default : Queue name to use}
                            {--priority=0 : Job priority (higher = more important)}
                            {--delay=0 : Delay between dispatching jobs in seconds}
                            {--pattern= : File pattern to match (default: *.mp4)}
                            {--move-processed : Move imported files to processed directory}';

    protected $description = 'Import videos from local directory using queue jobs for optimal parallel processing';

    protected string $importPath;
    protected string $processedPath;
    protected string $metadataPath;

    public function handle(): int
    {
        $this->info('📁 Starting local video import via queue...');
        
        $this->setupDirectories();
        
        $videoFiles = $this->findVideoFiles();
        
        if (empty($videoFiles)) {
            $this->warn('No video files found in ' . $this->importPath);
            return Command::SUCCESS;
        }

        $this->info('Found ' . count($videoFiles) . ' video files');

        if ($this->option('dry-run')) {
            $this->displayDryRunInfo($videoFiles);
            return Command::SUCCESS;
        }

        $this->dispatchImportJobs($videoFiles);
        
        return Command::SUCCESS;
    }

    protected function setupDirectories(): void
    {
        $basePath = $this->option('path') ?? storage_path('app/import/videos');
        $this->importPath = $basePath;
        $this->processedPath = dirname($basePath) . '/processed';
        $this->metadataPath = dirname($basePath) . '/metadata';

        // Create directories if they don't exist
        foreach ([$this->importPath, $this->processedPath, $this->metadataPath] as $path) {
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
                $this->info("Created directory: $path");
            }
        }
    }

    protected function findVideoFiles(): array
    {
        $pattern = $this->option('pattern') ?: '*.mp4';
        $files = glob($this->importPath . '/' . $pattern);
        
        if ($files === false) {
            return [];
        }
        
        if ($limit = $this->option('limit')) {
            $files = array_slice($files, 0, (int) $limit);
        }
        
        $videoFiles = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $videoFiles[] = $file;
            }
        }
        
        return $videoFiles;
    }

    protected function displayDryRunInfo(array $videoFiles): void
    {
        $this->info('');
        $this->info('📋 DRY RUN - Videos to be queued:');
        $this->info('=====================================');
        
        $table = [];
        $totalSize = 0;
        $toImport = 0;
        $skipped = 0;
        
        foreach ($videoFiles as $index => $file) {
            $metadata = $this->extractFileMetadata($file);
            $size = filesize($file);
            $totalSize += $size;
            $alreadyImported = $this->videoAlreadyImported($metadata['vimeo_id'] ?? null);
            
            $table[] = [
                $index + 1,
                basename($file),
                $this->formatBytes($size),
                $metadata['vimeo_id'] ?? 'N/A',
                $alreadyImported ? '✅ Imported' : '⏳ Pending',
            ];
            
            if ($alreadyImported) {
                $skipped++;
            } else {
                $toImport++;
            }
        }

        $this->table(['#', 'Filename', 'Size', 'Vimeo ID', 'Status'], $table);
        
        $this->info('');
        $this->info("📊 Summary:");
        $this->info("  • Total files: " . count($videoFiles));
        $this->info("  • Already imported: $skipped");
        $this->info("  • To import: $toImport");
        $this->info("  • Total size: " . $this->formatBytes($totalSize));
        
        if ($this->option('with-metadata')) {
            $this->info("  • Metadata mode: JSON sidecar files");
        } else {
            $this->info("  • Metadata mode: Parse from filename");
        }
        
        $this->info("  • Queue: " . $this->option('queue'));
    }

    protected function dispatchImportJobs(array $videoFiles): void
    {
        $queue = $this->option('queue');
        $priority = (int) $this->option('priority');
        $delay = (int) $this->option('delay');
        $withMetadata = $this->option('with-metadata');
        $moveProcessed = $this->option('move-processed');
        $userId = auth()->id() ?? 1;
        
        $dispatched = 0;
        $skipped = 0;
        
        $this->info('');
        $this->info('🚀 Dispatching import jobs to queue...');
        
        $progressBar = $this->output->createProgressBar(count($videoFiles));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        
        foreach ($videoFiles as $index => $file) {
            $filename = basename($file);
            $metadata = $this->extractFileMetadata($file);
            
            if ($metadata['vimeo_id'] && $this->videoAlreadyImported($metadata['vimeo_id'])) {
                $progressBar->setMessage("Skipping: $filename");
                $skipped++;
            } else {
                $job = new ImportLocalVideo(
                    $file, 
                    $userId, 
                    $withMetadata,
                    $moveProcessed,
                    $this->processedPath,
                    $this->metadataPath
                );
                
                if ($delay > 0) {
                    $job->onQueue($queue)->delay(now()->addSeconds($delay * $dispatched));
                } else {
                    $job->onQueue($queue);
                }
                
                dispatch($job);
                
                $progressBar->setMessage("Queued: $filename");
                $dispatched++;
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        
        $this->info('');
        $this->info('');
        $this->info('✅ Import jobs dispatched successfully!');
        $this->info("  • Jobs queued: $dispatched");
        $this->info("  • Videos skipped: $skipped");
        $this->info("  • Queue name: $queue");
        
        $this->info('');
        $this->info('📌 Monitor progress with:');
        $this->info("  php artisan queue:work --queue=$queue");
        $this->info('');
        $this->info('📊 Check queue status with:');
        $this->info("  php artisan queue:monitor $queue");
        $this->info('');
        $this->info('📜 View logs with:');
        $this->info("  php artisan pail");
    }

    protected function extractFileMetadata(string $filePath): array
    {
        $metadata = [];
        $filename = basename($filePath);
        
        // Try to load JSON metadata if requested
        if ($this->option('with-metadata')) {
            $metadataFile = $this->getMetadataFilePath($filePath);
            if (file_exists($metadataFile)) {
                $jsonContent = file_get_contents($metadataFile);
                $jsonData = json_decode($jsonContent, true);
                if ($jsonData) {
                    return $jsonData;
                }
            }
        }
        
        // Parse from filename pattern: Title_VimeoID.mp4
        if (preg_match('/^(.+)_(\d{7,12})\.mp4$/i', $filename, $matches)) {
            $metadata['title'] = str_replace('_', ' ', $matches[1]);
            $metadata['vimeo_id'] = $matches[2];
        } else {
            // Just use filename without extension as title
            $metadata['title'] = pathinfo($filename, PATHINFO_FILENAME);
        }
        
        return $metadata;
    }

    protected function getMetadataFilePath(string $videoPath): string
    {
        $filename = pathinfo($videoPath, PATHINFO_FILENAME);
        return $this->metadataPath . '/' . $filename . '.json';
    }

    protected function videoAlreadyImported(?string $vimeoId): bool
    {
        if (!$vimeoId) {
            return false;
        }
        
        return Video::where('metadata->vimeo_id', $vimeoId)->exists();
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}