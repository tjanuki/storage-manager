<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Aws\S3\S3Client;
use Inertia\Inertia;
use Inertia\Response;

class PublicVideoController extends Controller
{
    private S3Client $s3Client;
    private string $bucket;
    private string $region;

    public function __construct()
    {
        $this->region = config('filesystems.disks.s3.region', 'us-east-1');
        $this->bucket = config('filesystems.disks.s3.bucket');
        
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);
    }

    public function show(string $uuid): Response
    {
        $video = Video::where('share_uuid', $uuid)
            ->where('is_public', true)
            ->where('status', 'completed')
            ->firstOrFail();

        return Inertia::render('Videos/Public', [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'size' => $video->size,
                'formatted_size' => $video->formatted_size,
                'duration' => $video->duration,
                'formatted_duration' => $video->formatted_duration,
                'mime_type' => $video->mime_type,
                'uploaded_at' => $video->uploaded_at?->format('Y-m-d H:i:s'),
                'shared_at' => $video->shared_at?->format('Y-m-d H:i:s'),
                's3_url' => $this->getPresignedUrl($video),
                'thumbnail_url' => $video->thumbnail_url ?? null,
            ],
        ]);
    }

    private function getPresignedUrl(Video $video): string
    {
        $command = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $video->s3_key,
        ]);

        $presignedUrl = $this->s3Client->createPresignedRequest($command, '+60 minutes');
        return (string) $presignedUrl->getUri();
    }
}