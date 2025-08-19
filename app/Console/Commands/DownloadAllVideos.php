<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\VimeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadAllVideos extends Command
{
    protected $signature = 'videos:import-vimeo 
                            {--parallel=5 : Number of parallel downloads}
                            {--resume : Resume interrupted downloads}
                            {--dry-run : List videos without downloading}
                            {--limit= : Limit number of videos to import}';

    protected $description = 'Import all videos from Vimeo with parallel downloading support';

    protected VimeoService $vimeoService;
    protected array $activeDownloads = [];
    protected int $totalVideos = 0;
    protected int $completedVideos = 0;
    protected int $totalBytes = 0;
    protected int $downloadedBytes = 0;

    public function __construct(VimeoService $vimeoService)
    {
        parent::__construct();
        $this->vimeoService = $vimeoService;
    }

    public function handle(): int
    {
        $this->info('Starting Vimeo video import...');
        
        if (!config('services.vimeo.access_token')) {
            $this->error('Vimeo access token not configured. Please set VIMEO_ACCESS_TOKEN in your .env file.');
            return Command::FAILURE;
        }

        try {
            $videos = $this->fetchVimeoVideos();
            
            if ($this->option('dry-run')) {
                $this->displayDryRunInfo($videos);
                return Command::SUCCESS;
            }

            $this->importVideos($videos);
            
            $this->info('');
            $this->info('Import completed successfully!');
            $this->info("Total videos imported: {$this->completedVideos}/{$this->totalVideos}");
            $this->info('Total size: ' . $this->formatBytes($this->downloadedBytes));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Import failed: ' . $e->getMessage());
            Log::error('Vimeo import failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return Command::FAILURE;
        }
    }

    protected function fetchVimeoVideos(): array
    {
        $this->info('Fetching video list from Vimeo...');
        
        $videos = $this->vimeoService->getAllVideos();
        
        if ($limit = $this->option('limit')) {
            $videos = array_slice($videos, 0, (int) $limit);
        }

        $this->totalVideos = count($videos);
        $this->totalBytes = array_sum(array_map(function ($video) {
            $download = $this->vimeoService->getHighestQualityDownloadLink($video);
            return $download['size'] ?? 0;
        }, $videos));

        $this->info("Found {$this->totalVideos} videos (" . $this->formatBytes($this->totalBytes) . ")");
        
        return $videos;
    }

    protected function displayDryRunInfo(array $videos): void
    {
        $this->info('');
        $this->info('DRY RUN - Videos to be imported:');
        $this->info('================================');
        
        $table = [];
        foreach ($videos as $index => $video) {
            $metadata = $this->vimeoService->extractVideoMetadata($video);
            $table[] = [
                $index + 1,
                $metadata['title'],
                $this->formatBytes($metadata['size']),
                $metadata['quality'],
                gmdate("H:i:s", $metadata['duration'] ?? 0),
            ];
        }

        $this->table(['#', 'Title', 'Size', 'Quality', 'Duration'], $table);
        
        $this->info('');
        $this->info("Total videos: {$this->totalVideos}");
        $this->info("Total size: " . $this->formatBytes($this->totalBytes));
        $this->info("Estimated time: " . $this->estimateDownloadTime($this->totalBytes));
    }

    protected function importVideos(array $videos): void
    {
        $parallelLimit = (int) $this->option('parallel');
        $chunks = array_chunk($videos, $parallelLimit);
        
        $this->info("Starting import with {$parallelLimit} parallel downloads...");
        $this->info('');

        foreach ($chunks as $chunk) {
            $this->processVideoChunk($chunk);
        }
    }

    protected function processVideoChunk(array $videos): void
    {
        $promises = [];
        
        foreach ($videos as $video) {
            $metadata = $this->vimeoService->extractVideoMetadata($video);
            
            if ($this->videoAlreadyImported($metadata['vimeo_id'])) {
                $this->info("[SKIP] {$metadata['title']} - Already imported");
                $this->completedVideos++;
                continue;
            }

            $this->downloadAndStoreVideo($video, $metadata);
        }
    }

    protected function downloadAndStoreVideo(array $video, array $metadata): void
    {
        $filename = $this->sanitizeFilename($metadata['title']) . '_' . $metadata['vimeo_id'] . '.mp4';
        $localPath = storage_path('app/videos/' . $filename);
        
        if (!file_exists(dirname($localPath))) {
            mkdir(dirname($localPath), 0755, true);
        }

        $this->info("[DOWNLOADING] {$metadata['title']}");
        
        $progressBar = $this->output->createProgressBar(100);
        $progressBar->setFormat(' %current%% [%bar%] %elapsed:6s% / %estimated:-6s% %memory:6s%');
        
        $success = false;
        
        if ($this->option('resume')) {
            $success = $this->vimeoService->downloadWithResume($video, $localPath, function ($video, $percentage, $downloaded, $total) use ($progressBar, $metadata) {
                $progressBar->setProgress((int) $percentage);
                $this->downloadedBytes += ($downloaded - ($this->activeDownloads[$metadata['vimeo_id']] ?? 0));
                $this->activeDownloads[$metadata['vimeo_id']] = $downloaded;
            });
        } else {
            $success = $this->vimeoService->downloadVideo($video, $localPath, function ($video, $percentage, $downloaded, $total) use ($progressBar, $metadata) {
                $progressBar->setProgress((int) $percentage);
                $this->downloadedBytes += ($downloaded - ($this->activeDownloads[$metadata['vimeo_id']] ?? 0));
                $this->activeDownloads[$metadata['vimeo_id']] = $downloaded;
            });
        }

        $progressBar->finish();
        $this->info('');
        
        if ($success) {
            $this->uploadToS3AndSaveRecord($localPath, $filename, $metadata);
            $this->completedVideos++;
            $this->info("[SUCCESS] {$metadata['title']}");
        } else {
            $this->error("[FAILED] {$metadata['title']}");
        }
        
        unset($this->activeDownloads[$metadata['vimeo_id']]);
    }

    protected function uploadToS3AndSaveRecord(string $localPath, string $filename, array $metadata): void
    {
        try {
            $s3Key = 'videos/' . auth()->id() . '/' . $filename;
            
            Storage::disk('s3')->put($s3Key, fopen($localPath, 'r'));
            
            Video::create([
                'user_id' => auth()->id() ?? 1,
                'title' => $metadata['title'],
                'description' => $metadata['description'],
                'original_filename' => $filename,
                's3_key' => $s3Key,
                's3_bucket' => config('filesystems.disks.s3.bucket'),
                's3_region' => config('filesystems.disks.s3.region'),
                'size' => filesize($localPath),
                'mime_type' => 'video/mp4',
                'duration' => $metadata['duration'],
                'status' => 'completed',
                'metadata' => [
                    'vimeo_id' => $metadata['vimeo_id'],
                    'imported_from' => 'vimeo',
                    'import_date' => now()->toIso8601String(),
                    'original_quality' => $metadata['quality'],
                ],
                'uploaded_at' => now(),
            ]);
            
            unlink($localPath);
            
        } catch (\Exception $e) {
            $this->error("Failed to upload to S3: " . $e->getMessage());
            Log::error('S3 upload failed', ['error' => $e->getMessage(), 'file' => $filename]);
        }
    }

    protected function videoAlreadyImported(string $vimeoId): bool
    {
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