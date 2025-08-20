<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportLocalVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 3;
    public int $maxExceptions = 2;

    protected string $filePath;
    protected int $userId;
    protected bool $withMetadata;
    protected bool $moveProcessed;
    protected string $processedPath;
    protected string $metadataPath;

    public function __construct(
        string $filePath,
        int $userId,
        bool $withMetadata = false,
        bool $moveProcessed = false,
        string $processedPath = '',
        string $metadataPath = ''
    ) {
        $this->filePath = $filePath;
        $this->userId = $userId;
        $this->withMetadata = $withMetadata;
        $this->moveProcessed = $moveProcessed;
        $this->processedPath = $processedPath ?: storage_path('app/import/processed');
        $this->metadataPath = $metadataPath ?: storage_path('app/import/metadata');
    }

    public function handle(): void
    {
        $filename = basename($this->filePath);
        
        Log::info("Starting local video import", [
            'filename' => $filename,
            'size' => filesize($this->filePath),
            'user_id' => $this->userId,
        ]);

        try {
            $metadata = $this->extractFileMetadata();
            
            // Check if already imported
            if (isset($metadata['vimeo_id']) && $this->videoAlreadyImported($metadata['vimeo_id'])) {
                Log::info("Video already imported", [
                    'filename' => $filename,
                    'vimeo_id' => $metadata['vimeo_id'],
                ]);
                
                if ($this->moveProcessed) {
                    $this->moveToProcessed();
                }
                
                return;
            }

            // Upload to S3
            $s3Key = 'videos/' . $this->userId . '/' . $this->sanitizeFilename($filename);
            
            Log::info("Uploading to S3", [
                's3_key' => $s3Key,
                'file_size' => filesize($this->filePath),
            ]);
            
            Storage::disk('s3')->put($s3Key, fopen($this->filePath, 'r'), [
                'visibility' => 'private',
                'ContentType' => mime_content_type($this->filePath) ?: 'video/mp4',
            ]);

            // Create database record
            Video::create([
                'user_id' => $this->userId,
                'title' => $metadata['title'] ?? pathinfo($filename, PATHINFO_FILENAME),
                'description' => $metadata['description'] ?? '',
                'original_filename' => $filename,
                's3_key' => $s3Key,
                's3_bucket' => config('filesystems.disks.s3.bucket'),
                's3_region' => config('filesystems.disks.s3.region'),
                'size' => filesize($this->filePath),
                'mime_type' => mime_content_type($this->filePath) ?: 'video/mp4',
                'duration' => $metadata['duration'] ?? null,
                'status' => 'completed',
                'metadata' => array_filter([
                    'vimeo_id' => $metadata['vimeo_id'] ?? null,
                    'imported_from' => 'local',
                    'import_date' => now()->toIso8601String(),
                    'original_path' => $this->filePath,
                    'original_quality' => $metadata['quality'] ?? null,
                    'created_at_vimeo' => $metadata['created_at_vimeo'] ?? null,
                    'modified_at_vimeo' => $metadata['modified_at_vimeo'] ?? null,
                ]),
                'uploaded_at' => now(),
            ]);

            Log::info("Local video imported successfully", [
                'filename' => $filename,
                's3_key' => $s3Key,
                'vimeo_id' => $metadata['vimeo_id'] ?? null,
            ]);

            // Move to processed directory if requested
            if ($this->moveProcessed) {
                $this->moveToProcessed();
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to import local video", [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    protected function extractFileMetadata(): array
    {
        $metadata = [];
        $filename = basename($this->filePath);
        
        // Try to load JSON metadata if requested
        if ($this->withMetadata) {
            $metadataFile = $this->getMetadataFilePath();
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
        $duration = $this->getVideoDuration();
        if ($duration !== null) {
            $metadata['duration'] = $duration;
        }
        
        return $metadata;
    }

    protected function getMetadataFilePath(): string
    {
        $filename = pathinfo($this->filePath, PATHINFO_FILENAME);
        return $this->metadataPath . '/' . $filename . '.json';
    }

    protected function getVideoDuration(): ?int
    {
        // Check if ffprobe is available
        exec('which ffprobe 2>/dev/null', $output, $returnCode);
        if ($returnCode !== 0) {
            return null;
        }
        
        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($this->filePath)
        );
        
        $duration = shell_exec($cmd);
        
        return $duration ? (int) round((float) $duration) : null;
    }

    protected function moveToProcessed(): void
    {
        if (!file_exists($this->processedPath)) {
            mkdir($this->processedPath, 0755, true);
        }
        
        $filename = basename($this->filePath);
        $processedFile = $this->processedPath . '/' . $filename;
        
        if (file_exists($this->filePath)) {
            rename($this->filePath, $processedFile);
            
            Log::info("Moved file to processed", [
                'from' => $this->filePath,
                'to' => $processedFile,
            ]);
        }
        
        // Move metadata file if it exists
        if ($this->withMetadata) {
            $metadataFile = $this->getMetadataFilePath();
            if (file_exists($metadataFile)) {
                $processedMetadata = $this->processedPath . '/' . basename($metadataFile);
                rename($metadataFile, $processedMetadata);
                
                Log::info("Moved metadata to processed", [
                    'from' => $metadataFile,
                    'to' => $processedMetadata,
                ]);
            }
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

    public function failed(\Throwable $exception): void
    {
        Log::error("Import local video job failed", [
            'file' => $this->filePath,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}