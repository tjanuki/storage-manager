<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
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

    public function index(): Response
    {
        $videos = Auth::user()->videos()
            ->latest()
            ->paginate(12);

        return Inertia::render('Videos/Index', [
            'videos' => $videos->through(fn ($video) => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'size' => $video->size,
                'formatted_size' => $video->formatted_size,
                'duration' => $video->duration,
                'formatted_duration' => $video->formatted_duration,
                'status' => $video->status,
                'mime_type' => $video->mime_type,
                'uploaded_at' => $video->uploaded_at?->format('Y-m-d H:i:s'),
                'created_at' => $video->created_at->format('Y-m-d H:i:s'),
                's3_url' => $video->status === 'completed' ? $this->getPresignedUrl($video) : null,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Videos/Upload', [
            'maxFileSize' => 10 * 1024 * 1024 * 1024, // 10GB in bytes
        ]);
    }

    public function show(Video $video): Response
    {
        // Verify ownership
        if ($video->user_id !== Auth::id()) {
            abort(403);
        }

        return Inertia::render('Videos/View', [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'size' => $video->size,
                'formatted_size' => $video->formatted_size,
                'duration' => $video->duration,
                'formatted_duration' => $video->formatted_duration,
                'status' => $video->status,
                'mime_type' => $video->mime_type,
                'uploaded_at' => $video->uploaded_at?->format('Y-m-d H:i:s'),
                'created_at' => $video->created_at->format('Y-m-d H:i:s'),
                's3_url' => $video->status === 'completed' ? $this->getPresignedUrl($video) : null,
                'is_public' => $video->is_public,
                'share_uuid' => $video->share_uuid,
                'public_url' => $video->public_url,
                'shared_at' => $video->shared_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function edit(Video $video): Response
    {
        // Verify ownership
        if ($video->user_id !== Auth::id()) {
            abort(403);
        }

        return Inertia::render('Videos/Edit', [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'size' => $video->size,
                'formatted_size' => $video->formatted_size,
                'duration' => $video->duration,
                'formatted_duration' => $video->formatted_duration,
                'status' => $video->status,
                'mime_type' => $video->mime_type,
                'uploaded_at' => $video->uploaded_at?->format('Y-m-d H:i:s'),
                'created_at' => $video->created_at->format('Y-m-d H:i:s'),
                's3_url' => $video->status === 'completed' ? $this->getPresignedUrl($video) : null,
                'is_public' => $video->is_public,
                'share_uuid' => $video->share_uuid,
                'public_url' => $video->public_url,
                'shared_at' => $video->shared_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function update(Request $request, Video $video): JsonResponse
    {
        // Verify ownership
        if ($video->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $video->update($validated);

        return response()->json([
            'success' => true,
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
            ],
        ]);
    }

    public function initiateUpload(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string|max:255',
            'filesize' => 'required|integer|min:1|max:10737418240', // 10GB max
            'mimetype' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'duration' => 'nullable|integer|min:0',
        ]);

        $key = 'videos/'.Auth::id().'/'.Str::uuid().'/'.$request->filename;

        try {
            // Create multipart upload
            $result = $this->s3Client->createMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'ContentType' => $request->mimetype,
                'Metadata' => [
                    'user_id' => (string) Auth::id(),
                    'original_filename' => $request->filename,
                ],
            ]);

            // Create video record
            $video = Auth::user()->videos()->create([
                'title' => $request->title,
                'description' => $request->description,
                'original_filename' => $request->filename,
                's3_key' => $key,
                's3_bucket' => $this->bucket,
                's3_region' => $this->region,
                'size' => $request->filesize,
                'mime_type' => $request->mimetype,
                'duration' => $request->duration,
                'status' => 'uploading',
                'upload_id' => $result['UploadId'],
            ]);

            return response()->json([
                'video_id' => $video->id,
                'upload_id' => $result['UploadId'],
                'key' => $key,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initiate upload: '.$e->getMessage());

            return response()->json(['error' => 'Failed to initiate upload'], 500);
        }
    }

    public function getUploadUrl(Request $request): JsonResponse
    {
        $request->validate([
            'video_id' => 'required|exists:videos,id',
            'upload_id' => 'required|string',
            'key' => 'required|string',
            'part_number' => 'required|integer|min:1|max:10000',
        ]);

        $video = Video::findOrFail($request->video_id);

        // Verify ownership
        if ($video->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $command = $this->s3Client->getCommand('uploadPart', [
                'Bucket' => $this->bucket,
                'Key' => $request->key,
                'UploadId' => $request->upload_id,
                'PartNumber' => $request->part_number,
            ]);

            $presignedUrl = $this->s3Client->createPresignedRequest($command, '+60 minutes');

            return response()->json([
                'url' => (string) $presignedUrl->getUri(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get upload URL: '.$e->getMessage());

            return response()->json(['error' => 'Failed to get upload URL'], 500);
        }
    }

    public function completeUpload(Request $request): JsonResponse
    {
        $request->validate([
            'video_id' => 'required|exists:videos,id',
            'upload_id' => 'required|string',
            'key' => 'required|string',
            'parts' => 'required|array',
            'parts.*.PartNumber' => 'required|integer',
            'parts.*.ETag' => 'required|string',
        ]);

        $video = Video::findOrFail($request->video_id);

        // Verify ownership
        if ($video->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $result = $this->s3Client->completeMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $request->key,
                'UploadId' => $request->upload_id,
                'MultipartUpload' => [
                    'Parts' => $request->parts,
                ],
            ]);

            // Update video status
            $video->update([
                'status' => 'completed',
                'uploaded_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'location' => $result['Location'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to complete upload: '.$e->getMessage());

            // Mark video as failed
            $video->update(['status' => 'failed']);

            return response()->json(['error' => 'Failed to complete upload'], 500);
        }
    }

    public function abortUpload(Request $request): JsonResponse
    {
        $request->validate([
            'video_id' => 'required|exists:videos,id',
            'upload_id' => 'required|string',
            'key' => 'required|string',
        ]);

        $video = Video::findOrFail($request->video_id);

        // Verify ownership
        if ($video->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $this->s3Client->abortMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $request->key,
                'UploadId' => $request->upload_id,
            ]);

            // Delete video record
            $video->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to abort upload: '.$e->getMessage());

            return response()->json(['error' => 'Failed to abort upload'], 500);
        }
    }

    public function toggleSharing(Video $video): JsonResponse
    {
        // Verify ownership
        if ($video->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($video->is_public) {
            $video->disableSharing();
        } else {
            $video->enableSharing();
        }

        return response()->json([
            'success' => true,
            'is_public' => $video->is_public,
            'public_url' => $video->public_url,
        ]);
    }

    public function destroy(Video $video): JsonResponse
    {
        // Verify ownership
        if ($video->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Delete from S3
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $video->s3_key,
            ]);

            // Delete database record
            $video->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to delete video: '.$e->getMessage());

            return response()->json(['error' => 'Failed to delete video'], 500);
        }
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
