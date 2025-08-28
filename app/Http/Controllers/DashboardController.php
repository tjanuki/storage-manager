<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get video statistics for the authenticated user
        $totalVideos = Video::where('user_id', $user->id)->count();
        $totalStorage = Video::where('user_id', $user->id)->sum('size');
        $totalDuration = Video::where('user_id', $user->id)->sum('duration');

        // Get videos by status
        $videosByStatus = Video::where('user_id', $user->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get recent videos
        $recentVideos = Video::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'size', 'duration', 'status', 'created_at', 'is_public'])
            ->map(function ($video) {
                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'formatted_size' => $video->formatted_size,
                    'formatted_duration' => $video->formatted_duration,
                    'status' => $video->status,
                    'is_public' => $video->is_public,
                    'created_at' => $video->created_at->format('M d, Y'),
                    'created_at_human' => $video->created_at->diffForHumans(),
                ];
            });

        // Get public videos count
        $publicVideos = Video::where('user_id', $user->id)
            ->where('is_public', true)
            ->count();

        // Format total storage
        $formattedStorage = $this->formatBytes($totalStorage);

        // Format total duration
        $formattedDuration = $this->formatDuration($totalDuration);

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_videos' => $totalVideos,
                'total_storage' => $totalStorage,
                'formatted_storage' => $formattedStorage,
                'total_duration' => $totalDuration,
                'formatted_duration' => $formattedDuration,
                'public_videos' => $publicVideos,
                'videos_by_status' => [
                    'completed' => $videosByStatus['completed'] ?? 0,
                    'uploading' => $videosByStatus['uploading'] ?? 0,
                    'processing' => $videosByStatus['processing'] ?? 0,
                    'failed' => $videosByStatus['failed'] ?? 0,
                ],
            ],
            'recent_videos' => $recentVideos,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    private function formatDuration(?int $seconds): string
    {
        if (! $seconds) {
            return '0:00';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }
}
