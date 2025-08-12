<?php

namespace App\Http\Controllers;

use App\Mail\VideoShareMail;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ShareEmailController extends Controller
{
    public function send(Request $request, string $uuid): JsonResponse
    {
        // Find the video by share UUID
        $video = Video::where('share_uuid', $uuid)
            ->where('is_public', true)
            ->where('status', 'completed')
            ->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'emails' => 'required|string',
            'sender_name' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Parse and validate email addresses
        $emails = array_map('trim', explode(',', $request->input('emails')));
        $emails = array_filter($emails); // Remove empty values
        
        // Validate each email
        $invalidEmails = [];
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidEmails[] = $email;
            }
        }

        if (!empty($invalidEmails)) {
            return response()->json([
                'error' => 'Invalid email addresses: ' . implode(', ', $invalidEmails)
            ], 422);
        }

        // Limit to 5 recipients
        if (count($emails) > 5) {
            return response()->json([
                'error' => 'Maximum 5 email addresses allowed'
            ], 422);
        }

        // Rate limiting - 5 emails per minute per IP
        $key = 'send-email:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'error' => "Too many emails sent. Please try again in {$seconds} seconds."
            ], 429);
        }

        RateLimiter::hit($key, 60); // 60 seconds decay

        // Generate the share URL
        $shareUrl = url("/share/{$uuid}");

        // Send emails
        try {
            foreach ($emails as $email) {
                Mail::to($email)->send(new VideoShareMail(
                    video: $video,
                    shareUrl: $shareUrl,
                    personalMessage: $request->input('message'),
                    senderName: $request->input('sender_name')
                ));
            }

            return response()->json([
                'success' => true,
                'message' => 'Email(s) sent successfully to ' . count($emails) . ' recipient(s)'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send email. Please try again later.'
            ], 500);
        }
    }
}