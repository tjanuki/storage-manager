<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\VimeoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportVimeoVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 3;
    public int $maxExceptions = 2;

    protected array $videoData;
    protected int $userId;
    protected bool $useResume;

    public function __construct(array $videoData, int $userId, bool $useResume = true)
    {
        $this->videoData = $videoData;
        $this->userId = $userId;
        $this->useResume = $useResume;
    }

    public function handle(): void
    {
        $vimeoService = new VimeoService();
        $metadata = $vimeoService->extractVideoMetadata($this->videoData);
        
        if ($this->videoAlreadyImported($metadata['vimeo_id'])) {
            Log::info("Video already imported", ['vimeo_id' => $metadata['vimeo_id']]);
            return;
        }

        $filename = $this->sanitizeFilename($metadata['title']) . '_' . $metadata['vimeo_id'] . '.mp4';
        $localPath = storage_path('app/videos/' . $filename);
        
        if (!file_exists(dirname($localPath))) {
            mkdir(dirname($localPath), 0755, true);
        }

        Log::info("Starting download", [
            'vimeo_id' => $metadata['vimeo_id'],
            'title' => $metadata['title'],
            'size' => $metadata['size'],
        ]);

        $downloadStartTime = microtime(true);
        $success = false;
        
        if ($this->useResume) {
            $success = $vimeoService->downloadWithResume(
                $this->videoData, 
                $localPath,
                function ($video, $percentage, $downloaded, $total) use ($metadata) {
                    Log::debug("Download progress", [
                        'vimeo_id' => $metadata['vimeo_id'],
                        'percentage' => round($percentage, 2),
                        'downloaded' => $this->formatBytes($downloaded),
                        'total' => $this->formatBytes($total),
                    ]);
                }
            );
        } else {
            $success = $vimeoService->downloadVideo(
                $this->videoData,
                $localPath,
                function ($video, $percentage, $downloaded, $total) use ($metadata) {
                    Log::debug("Download progress", [
                        'vimeo_id' => $metadata['vimeo_id'],
                        'percentage' => round($percentage, 2),
                        'downloaded' => $this->formatBytes($downloaded),
                        'total' => $this->formatBytes($total),
                    ]);
                }
            );
        }

        $downloadTime = microtime(true) - $downloadStartTime;
        
        if ($success) {
            $this->uploadToS3AndSaveRecord($localPath, $filename, $metadata);
            
            Log::info("Video imported successfully", [
                'vimeo_id' => $metadata['vimeo_id'],
                'title' => $metadata['title'],
                'download_time' => round($downloadTime, 2) . ' seconds',
                'size' => $this->formatBytes($metadata['size']),
            ]);
        } else {
            Log::error("Failed to download video", [
                'vimeo_id' => $metadata['vimeo_id'],
                'title' => $metadata['title'],
            ]);
            
            throw new \Exception("Failed to download video: {$metadata['title']}");
        }
    }

    protected function uploadToS3AndSaveRecord(string $localPath, string $filename, array $metadata): void
    {
        try {
            $s3Key = 'videos/' . $this->userId . '/' . $filename;
            
            Log::info("Uploading to S3", [
                's3_key' => $s3Key,
                'file_size' => filesize($localPath),
            ]);
            
            Storage::disk('s3')->put($s3Key, fopen($localPath, 'r'), [
                'visibility' => 'private',
                'ContentType' => 'video/mp4',
            ]);
            
            Video::create([
                'user_id' => $this->userId,
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
                    'created_at_vimeo' => $metadata['created_at_vimeo'],
                    'modified_at_vimeo' => $metadata['modified_at_vimeo'],
                ],
                'uploaded_at' => now(),
            ]);
            
            unlink($localPath);
            
            Log::info("S3 upload completed", ['s3_key' => $s3Key]);
            
        } catch (\Exception $e) {
            Log::error("Failed to upload to S3", [
                'error' => $e->getMessage(),
                'file' => $filename,
            ]);
            
            if (file_exists($localPath)) {
                unlink($localPath);
            }
            
            throw $e;
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

    public function failed(\Throwable $exception): void
    {
        Log::error("Import job failed", [
            'vimeo_id' => $this->videoData['uri'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}