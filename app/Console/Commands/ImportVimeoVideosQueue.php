<?php

namespace App\Console\Commands;

use App\Jobs\ImportVimeoVideo;
use App\Models\Video;
use App\Services\VimeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportVimeoVideosQueue extends Command
{
    protected $signature = 'videos:import-vimeo-queue 
                            {--dry-run : List videos without downloading}
                            {--limit= : Limit number of videos to import}
                            {--queue=default : Queue name to use}
                            {--priority=0 : Job priority (higher = more important)}
                            {--delay=0 : Delay between dispatching jobs in seconds}';

    protected $description = 'Import videos from Vimeo using queue jobs for optimal parallel processing';

    protected VimeoService $vimeoService;

    public function __construct(VimeoService $vimeoService)
    {
        parent::__construct();
        $this->vimeoService = $vimeoService;
    }

    public function handle(): int
    {
        $this->info('🎬 Starting Vimeo video import via queue...');
        
        if (!config('services.vimeo.access_token')) {
            $this->error('❌ Vimeo access token not configured. Please set VIMEO_ACCESS_TOKEN in your .env file.');
            return Command::FAILURE;
        }

        try {
            $videos = $this->fetchVimeoVideos();
            
            if (empty($videos)) {
                $this->warn('No videos found to import.');
                return Command::SUCCESS;
            }

            if ($this->option('dry-run')) {
                $this->displayDryRunInfo($videos);
                return Command::SUCCESS;
            }

            $this->dispatchImportJobs($videos);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Import failed: ' . $e->getMessage());
            Log::error('Vimeo import failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    protected function fetchVimeoVideos(): array
    {
        $this->info('📡 Fetching video list from Vimeo...');
        
        $progressBar = $this->output->createProgressBar();
        $progressBar->setFormat(' %message%');
        $progressBar->setMessage('Fetching videos from Vimeo API...');
        $progressBar->start();
        
        $videos = $this->vimeoService->getAllVideos();
        
        if ($limit = $this->option('limit')) {
            $videos = array_slice($videos, 0, (int) $limit);
        }

        $progressBar->finish();
        $this->info('');
        
        $totalSize = array_sum(array_map(function ($video) {
            $download = $this->vimeoService->getHighestQualityDownloadLink($video);
            return $download['size'] ?? 0;
        }, $videos));

        $this->info("✅ Found " . count($videos) . " videos (" . $this->formatBytes($totalSize) . ")");
        
        return $videos;
    }

    protected function displayDryRunInfo(array $videos): void
    {
        $this->info('');
        $this->info('📋 DRY RUN - Videos to be imported:');
        $this->info('=====================================');
        
        $table = [];
        $skipped = 0;
        $toImport = 0;
        
        foreach ($videos as $index => $video) {
            $metadata = $this->vimeoService->extractVideoMetadata($video);
            $alreadyImported = $this->videoAlreadyImported($metadata['vimeo_id']);
            
            $table[] = [
                $index + 1,
                substr($metadata['title'], 0, 40) . (strlen($metadata['title']) > 40 ? '...' : ''),
                $this->formatBytes($metadata['size']),
                $metadata['quality'],
                gmdate("H:i:s", $metadata['duration'] ?? 0),
                $alreadyImported ? '✅ Imported' : '⏳ Pending',
            ];
            
            if ($alreadyImported) {
                $skipped++;
            } else {
                $toImport++;
            }
        }

        $this->table(['#', 'Title', 'Size', 'Quality', 'Duration', 'Status'], $table);
        
        $this->info('');
        $this->info("📊 Summary:");
        $this->info("  • Total videos: " . count($videos));
        $this->info("  • Already imported: {$skipped}");
        $this->info("  • To import: {$toImport}");
        
        if ($toImport > 0) {
            $totalSize = array_sum(array_map(function ($video) {
                $metadata = $this->vimeoService->extractVideoMetadata($video);
                if (!$this->videoAlreadyImported($metadata['vimeo_id'])) {
                    return $metadata['size'];
                }
                return 0;
            }, $videos));
            
            $this->info("  • Total size to download: " . $this->formatBytes($totalSize));
            $this->info("  • Estimated time (100 Mbps): " . $this->estimateDownloadTime($totalSize));
        }
    }

    protected function dispatchImportJobs(array $videos): void
    {
        $queue = $this->option('queue');
        $priority = (int) $this->option('priority');
        $delay = (int) $this->option('delay');
        $userId = auth()->id() ?? 1;
        
        $dispatched = 0;
        $skipped = 0;
        
        $this->info('');
        $this->info('🚀 Dispatching import jobs to queue...');
        
        $progressBar = $this->output->createProgressBar(count($videos));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        
        foreach ($videos as $index => $video) {
            $metadata = $this->vimeoService->extractVideoMetadata($video);
            
            if ($this->videoAlreadyImported($metadata['vimeo_id'])) {
                $progressBar->setMessage("Skipping: {$metadata['title']}");
                $skipped++;
            } else {
                $job = new ImportVimeoVideo($video, $userId, true);
                
                if ($priority !== 0) {
                    $job->onQueue($queue)->delay(now()->addSeconds($delay * $dispatched));
                } else {
                    $job->onQueue($queue)->delay(now()->addSeconds($delay * $dispatched));
                }
                
                dispatch($job);
                
                $progressBar->setMessage("Queued: {$metadata['title']}");
                $dispatched++;
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        
        $this->info('');
        $this->info('');
        $this->info('✅ Import jobs dispatched successfully!');
        $this->info("  • Jobs queued: {$dispatched}");
        $this->info("  • Videos skipped: {$skipped}");
        $this->info("  • Queue name: {$queue}");
        
        $this->info('');
        $this->info('📌 Monitor progress with:');
        $this->info("  php artisan queue:work --queue={$queue}");
        $this->info('');
        $this->info('📊 Check queue status with:');
        $this->info("  php artisan queue:monitor {$queue}");
        $this->info('');
        $this->info('📜 View logs with:');
        $this->info("  php artisan pail");
    }

    protected function videoAlreadyImported(string $vimeoId): bool
    {
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

    protected function estimateDownloadTime(int $bytes): string
    {
        $mbps = 100;
        $seconds = ($bytes * 8) / ($mbps * 1000000);
        
        if ($seconds < 60) {
            return round($seconds) . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } else {
            return round($seconds / 3600, 1) . ' hours';
        }
    }
}
