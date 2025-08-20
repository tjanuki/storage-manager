<?php

namespace App\Console\Commands;

use App\Services\VimeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateVimeoMetadata extends Command
{
    protected $signature = 'videos:generate-metadata 
                            {--all : Generate metadata for all videos from Vimeo}
                            {--id=* : Generate metadata for specific Vimeo IDs}
                            {--limit= : Limit number of videos to process}
                            {--output= : Output directory (default: storage/app/import/metadata)}
                            {--format=json : Output format (json or csv)}';

    protected $description = 'Generate metadata JSON files from Vimeo API for local import';

    protected VimeoService $vimeoService;
    protected string $outputPath;

    public function __construct(VimeoService $vimeoService)
    {
        parent::__construct();
        $this->vimeoService = $vimeoService;
    }

    public function handle(): int
    {
        $this->info('🎬 Fetching video metadata from Vimeo API...');
        
        if (!config('services.vimeo.access_token')) {
            $this->error('❌ Vimeo access token not configured. Please set VIMEO_ACCESS_TOKEN in your .env file.');
            return Command::FAILURE;
        }

        $this->outputPath = $this->option('output') ?? storage_path('app/import/metadata');
        
        // Create output directory if it doesn't exist
        if (!file_exists($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
            $this->info("Created directory: {$this->outputPath}");
        }

        try {
            if ($this->option('all')) {
                $this->generateForAllVideos();
            } elseif ($ids = $this->option('id')) {
                $this->generateForSpecificVideos($ids);
            } else {
                $this->info('Please use --all to fetch all videos or --id=VIDEO_ID for specific videos');
                $this->info('Example: php artisan videos:generate-metadata --all');
                $this->info('Example: php artisan videos:generate-metadata --id=1111479736 --id=1111479737');
                return Command::SUCCESS;
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to generate metadata: ' . $e->getMessage());
            Log::error('Metadata generation failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    protected function generateForAllVideos(): void
    {
        $this->info('📡 Fetching all videos from Vimeo...');
        
        $videos = $this->vimeoService->getAllVideos();
        
        if ($limit = $this->option('limit')) {
            $videos = array_slice($videos, 0, (int) $limit);
        }

        $this->info('Found ' . count($videos) . ' videos');
        $this->processVideos($videos);
    }

    protected function generateForSpecificVideos(array $videoIds): void
    {
        $this->info('📡 Fetching specific videos from Vimeo...');
        
        $videos = [];
        $progressBar = $this->output->createProgressBar(count($videoIds));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        
        foreach ($videoIds as $videoId) {
            $progressBar->setMessage("Fetching video {$videoId}");
            
            try {
                $video = $this->vimeoService->getVideo($videoId);
                if ($video) {
                    $videos[] = $video;
                }
            } catch (\Exception $e) {
                $this->warn("Failed to fetch video {$videoId}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->info('');
        
        $this->info('Successfully fetched ' . count($videos) . ' videos');
        $this->processVideos($videos);
    }

    protected function processVideos(array $videos): void
    {
        if (empty($videos)) {
            $this->warn('No videos to process');
            return;
        }

        $this->info('');
        $this->info('💾 Generating metadata files...');
        
        $format = $this->option('format');
        $generated = 0;
        $skipped = 0;
        
        $progressBar = $this->output->createProgressBar(count($videos));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        
        $table = [];
        
        foreach ($videos as $video) {
            $metadata = $this->vimeoService->extractVideoMetadata($video);
            $filename = $this->generateFilename($metadata);
            
            $progressBar->setMessage("Processing: {$metadata['title']}");
            
            if ($format === 'json') {
                $outputFile = $this->outputPath . '/' . $filename . '.json';
                
                if (file_exists($outputFile)) {
                    $progressBar->setMessage("Skipping (exists): {$metadata['title']}");
                    $skipped++;
                } else {
                    $this->writeJsonMetadata($outputFile, $metadata);
                    $progressBar->setMessage("Generated: {$filename}.json");
                    $generated++;
                }
                
                $table[] = [
                    $metadata['vimeo_id'],
                    substr($metadata['title'], 0, 40) . (strlen($metadata['title']) > 40 ? '...' : ''),
                    $filename . '.json',
                    file_exists($outputFile) ? '✅' : '❌',
                ];
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        
        $this->info('');
        $this->info('');
        
        // Display results table
        $this->table(['Vimeo ID', 'Title', 'Filename', 'Created'], $table);
        
        $this->info('');
        $this->info('✅ Metadata generation completed!');
        $this->info("  • Generated: {$generated} files");
        $this->info("  • Skipped: {$skipped} files (already exist)");
        $this->info("  • Output directory: {$this->outputPath}");
        
        $this->info('');
        $this->info('📌 Next steps:');
        $this->info('1. Download videos from Vimeo manually');
        $this->info('2. Rename them to match metadata files (e.g., ' . ($table[0][2] ?? 'VideoTitle_1234567890.mp4') . ')');
        $this->info('3. Place videos in storage/app/import/videos/');
        $this->info('4. Run: php artisan videos:import-local --with-metadata');
    }

    protected function writeJsonMetadata(string $filepath, array $metadata): void
    {
        $jsonContent = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($filepath, $jsonContent);
        
        Log::info("Generated metadata file", [
            'file' => $filepath,
            'vimeo_id' => $metadata['vimeo_id'],
            'title' => $metadata['title'],
        ]);
    }

    protected function generateFilename(array $metadata): string
    {
        // Create filename from title and Vimeo ID
        $title = $this->sanitizeFilename($metadata['title']);
        $vimeoId = $metadata['vimeo_id'];
        
        // Limit title length to avoid too long filenames
        if (strlen($title) > 50) {
            $title = substr($title, 0, 50);
        }
        
        return $title . '_' . $vimeoId;
    }

    protected function sanitizeFilename(string $filename): string
    {
        // Remove or replace invalid filename characters
        $filename = preg_replace('/[^a-zA-Z0-9\-\_]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        return trim($filename, '_');
    }
}