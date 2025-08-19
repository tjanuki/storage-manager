<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VimeoService
{
    protected Client $client;
    protected ?string $accessToken;
    protected string $tempPath;

    public function __construct()
    {
        $this->accessToken = config('services.vimeo.access_token');
        $this->tempPath = storage_path('app/temp/vimeo');
        
        $this->client = new Client([
            'base_uri' => 'https://api.vimeo.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/vnd.vimeo.*+json;version=3.4',
            ],
            'timeout' => 30,
        ]);

        if (!file_exists($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    public function getVideos(int $page = 1, int $perPage = 100): array
    {
        try {
            $response = $this->client->get('/me/videos', [
                'query' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'fields' => 'uri,name,description,duration,created_time,modified_time,download,files,size',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Failed to fetch Vimeo videos', [
                'error' => $e->getMessage(),
                'page' => $page,
            ]);
            throw $e;
        }
    }

    public function getAllVideos(): array
    {
        $allVideos = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->getVideos($page);
            $videos = $response['data'] ?? [];
            
            $allVideos = array_merge($allVideos, $videos);
            
            $hasMore = !empty($response['paging']['next']);
            $page++;
            
            Log::info("Fetched page {$page} with " . count($videos) . " videos");
        }

        return $allVideos;
    }

    public function getHighestQualityDownloadLink(array $video): ?array
    {
        $downloads = $video['download'] ?? [];
        
        if (empty($downloads)) {
            return null;
        }

        usort($downloads, function ($a, $b) {
            return ($b['size'] ?? 0) <=> ($a['size'] ?? 0);
        });

        return $downloads[0];
    }

    public function downloadVideo(array $video, string $destinationPath, ?callable $progressCallback = null): bool
    {
        $download = $this->getHighestQualityDownloadLink($video);
        
        if (!$download) {
            Log::warning('No download link available for video', ['video_id' => $video['uri']]);
            return false;
        }

        $tempFile = $this->tempPath . '/' . uniqid('vimeo_') . '.mp4';
        
        try {
            $resource = fopen($tempFile, 'w');
            
            $response = $this->client->get($download['link'], [
                'sink' => $resource,
                'progress' => function ($downloadTotal, $downloadedBytes) use ($progressCallback, $video) {
                    if ($progressCallback && $downloadTotal > 0) {
                        $percentage = ($downloadedBytes / $downloadTotal) * 100;
                        $progressCallback($video, $percentage, $downloadedBytes, $downloadTotal);
                    }
                },
                'timeout' => 0,
            ]);

            if (file_exists($tempFile)) {
                rename($tempFile, $destinationPath);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to download video', [
                'video_id' => $video['uri'],
                'error' => $e->getMessage(),
            ]);
            
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return false;
        }
    }

    public function downloadWithResume(array $video, string $destinationPath, ?callable $progressCallback = null): bool
    {
        $download = $this->getHighestQualityDownloadLink($video);
        
        if (!$download) {
            return false;
        }

        $tempFile = $destinationPath . '.download';
        $existingSize = file_exists($tempFile) ? filesize($tempFile) : 0;
        
        try {
            $headers = [];
            if ($existingSize > 0) {
                $headers['Range'] = "bytes={$existingSize}-";
            }

            $resource = fopen($tempFile, $existingSize > 0 ? 'a' : 'w');
            
            $response = $this->client->get($download['link'], [
                'sink' => $resource,
                'headers' => $headers,
                'progress' => function ($downloadTotal, $downloadedBytes) use ($progressCallback, $video, $existingSize) {
                    if ($progressCallback && $downloadTotal > 0) {
                        $totalBytes = $downloadTotal + $existingSize;
                        $currentBytes = $downloadedBytes + $existingSize;
                        $percentage = ($currentBytes / $totalBytes) * 100;
                        $progressCallback($video, $percentage, $currentBytes, $totalBytes);
                    }
                },
                'timeout' => 0,
            ]);

            if (file_exists($tempFile)) {
                rename($tempFile, $destinationPath);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to download video with resume', [
                'video_id' => $video['uri'],
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    public function extractVideoMetadata(array $video): array
    {
        $videoId = str_replace('/videos/', '', $video['uri']);
        
        return [
            'vimeo_id' => $videoId,
            'title' => $video['name'] ?? 'Untitled',
            'description' => $video['description'] ?? '',
            'duration' => $video['duration'] ?? null,
            'created_at_vimeo' => $video['created_time'] ?? null,
            'modified_at_vimeo' => $video['modified_time'] ?? null,
            'download_url' => $this->getHighestQualityDownloadLink($video)['link'] ?? null,
            'size' => $this->getHighestQualityDownloadLink($video)['size'] ?? 0,
            'quality' => $this->getHighestQualityDownloadLink($video)['quality'] ?? 'source',
        ];
    }
}