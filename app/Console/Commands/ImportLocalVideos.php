<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\VimeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportLocalVideos extends Command
{
    protected $signature = 'videos:import-local 
                            {--path= : Path to directory containing video files (default: storage/app/import/videos)}
                            {--with-metadata : Look for JSON metadata files alongside videos}
                            {--dry-run : List videos without importing}
                            {--move-processed : Move imported files to processed directory}
                            {--pattern= : File pattern to match (default: *.mp4)}';

    protected $description = 'Import video files from local directory to S3 and database';

    protected VimeoService $vimeoService;
    protected string $importPath;
    protected string $processedPath;
    protected string $metadataPath;

    public function __construct(VimeoService $vimeoService)
    {
        parent::__construct();
        $this->vimeoService = $vimeoService;
    }

    public function handle(): int
    {
        $this->info('📁 Starting local video import...');
        
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

        $this->processVideos($videoFiles);
        
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
        $this->info('📋 DRY RUN - Videos to be imported:');
        $this->info('=====================================');
        
        $table = [];
        $totalSize = 0;
        
        foreach ($videoFiles as $index => $file) {
            $metadata = $this->extractFileMetadata($file);
            $size = filesize($file);
            $totalSize += $size;
            
            $table[] = [
                $index + 1,
                basename($file),
                $this->formatBytes($size),
                $metadata['vimeo_id'] ?? 'N/A',
                $this->videoAlreadyImported($metadata['vimeo_id'] ?? null) ? '✅ Imported' : '⏳ Pending',
            ];
        }

        $this->table(['#', 'Filename', 'Size', 'Vimeo ID', 'Status'], $table);
        
        $this->info('');
        $this->info("📊 Summary:");
        $this->info("  • Total files: " . count($videoFiles));
        $this->info("  • Total size: " . $this->formatBytes($totalSize));
        
        if ($this->option('with-metadata')) {
            $this->info("  • Metadata mode: JSON sidecar files");
        } else {
            $this->info("  • Metadata mode: Parse from filename");
        }
    }

    protected function processVideos(array $videoFiles): void
    {
        $userId = auth()->id() ?? 1;
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar(count($videoFiles));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

        foreach ($videoFiles as $file) {
            $filename = basename($file);
            $progressBar->setMessage("Processing: $filename");
            
            try {
                $metadata = $this->extractFileMetadata($file);
                
                // Check if already imported
                if ($metadata['vimeo_id'] && $this->videoAlreadyImported($metadata['vimeo_id'])) {
                    $progressBar->setMessage("Skipping (already imported): $filename");
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                // Upload to S3
                $s3Key = 'videos/' . $userId . '/' . $this->sanitizeFilename($filename);
                
                Storage::disk('s3')->put($s3Key, fopen($file, 'r'), [
                    'visibility' => 'private',
                    'ContentType' => mime_content_type($file) ?: 'video/mp4',
                ]);

                // Create database record
                Video::create([
                    'user_id' => $userId,
                    'title' => $metadata['title'] ?? pathinfo($filename, PATHINFO_FILENAME),
                    'description' => $metadata['description'] ?? '',
                    'original_filename' => $filename,
                    's3_key' => $s3Key,
                    's3_bucket' => config('filesystems.disks.s3.bucket'),
                    's3_region' => config('filesystems.disks.s3.region'),
                    'size' => filesize($file),
                    'mime_type' => mime_content_type($file) ?: 'video/mp4',
                    'duration' => $metadata['duration'] ?? null,
                    'status' => 'completed',
                    'metadata' => array_filter([
                        'vimeo_id' => $metadata['vimeo_id'] ?? null,
                        'imported_from' => 'local',
                        'import_date' => now()->toIso8601String(),
                        'original_path' => $file,
                    ]),
                    'uploaded_at' => now(),
                ]);

                // Move to processed directory if requested
                if ($this->option('move-processed')) {
                    $processedFile = $this->processedPath . '/' . $filename;
                    rename($file, $processedFile);
                    
                    // Move metadata file if it exists
                    if ($this->option('with-metadata')) {
                        $metadataFile = $this->getMetadataFilePath($file);
                        if (file_exists($metadataFile)) {
                            rename($metadataFile, $this->processedPath . '/' . basename($metadataFile));
                        }
                    }
                }

                $progressBar->setMessage("Imported: $filename");
                $imported++;
                
                Log::info("Imported local video", [
                    'filename' => $filename,
                    's3_key' => $s3Key,
                    'vimeo_id' => $metadata['vimeo_id'] ?? null,
                ]);
                
            } catch (\Exception $e) {
                $progressBar->setMessage("Failed: $filename");
                $failed++;
                
                Log::error("Failed to import local video", [
                    'filename' => $filename,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        
        $this->info('');
        $this->info('');
        $this->info('✅ Import completed!');
        $this->info("  • Imported: $imported");
        $this->info("  • Skipped: $skipped");
        if ($failed > 0) {
            $this->error("  • Failed: $failed");
        }
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
        
        // Try to get video duration using ffprobe if available
        $duration = $this->getVideoDuration($filePath);
        if ($duration !== null) {
            $metadata['duration'] = $duration;
        }
        
        return $metadata;
    }

    protected function getMetadataFilePath(string $videoPath): string
    {
        $filename = pathinfo($videoPath, PATHINFO_FILENAME);
        return $this->metadataPath . '/' . $filename . '.json';
    }

    protected function getVideoDuration(string $filePath): ?int
    {
        // Check if ffprobe is available
        exec('which ffprobe 2>/dev/null', $output, $returnCode);
        if ($returnCode !== 0) {
            return null;
        }
        
        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($filePath)
        );
        
        $duration = shell_exec($cmd);
        
        return $duration ? (int) round((float) $duration) : null;
    }

    protected function videoAlreadyImported(?string $vimeoId): bool
    {
        if (!$vimeoId) {
            return false;
        }
        
        return Video::where('metadata->vimeo_id', $vimeoId)->exists();
    }

    protected function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        return trim($filename, '_');
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