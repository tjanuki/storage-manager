<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Shared With You</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .video-title {
            color: #2563eb;
            font-size: 24px;
            font-weight: 600;
            margin: 20px 0;
        }
        .video-info {
            background-color: #f9fafb;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }
        .info-label {
            color: #6b7280;
            font-size: 14px;
        }
        .info-value {
            color: #111827;
            font-weight: 500;
        }
        .personal-message {
            background-color: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .thumbnail {
            width: 100%;
            max-width: 400px;
            height: auto;
            border-radius: 6px;
            margin: 20px auto;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #111827; margin: 0;">Video Shared With You</h1>
            @if($senderName)
                <p style="color: #6b7280; margin-top: 10px;">{{ $senderName }} has shared a video with you</p>
            @endif
        </div>

        <div class="video-title">{{ $video->title }}</div>

        @if($video->thumbnail_url)
            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="thumbnail">
        @endif

        @if($video->description)
            <p style="color: #4b5563; margin: 15px 0;">{{ $video->description }}</p>
        @endif

        @if($personalMessage)
            <div class="personal-message">
                <strong>Personal Message:</strong><br>
                {{ $personalMessage }}
            </div>
        @endif

        <div class="video-info">
            <div class="info-row">
                <span class="info-label">File Size:</span>
                <span class="info-value">{{ $video->formatted_size }}</span>
            </div>
            @if($video->formatted_duration)
                <div class="info-row">
                    <span class="info-label">Duration:</span>
                    <span class="info-value">{{ $video->formatted_duration }}</span>
                </div>
            @endif
            <div class="info-row">
                <span class="info-label">Format:</span>
                <span class="info-value">{{ $video->mime_type }}</span>
            </div>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $shareUrl }}" class="button">Watch Video</a>
            <p style="color: #6b7280; font-size: 14px; margin-top: 10px;">
                If the button doesn't work, copy and paste this link into your browser:
            </p>
            <p style="color: #2563eb; font-size: 14px; word-break: break-all;">
                {{ $shareUrl }}
            </p>
        </div>

        <div class="footer">
            <p>This link will expire after some time for security reasons.</p>
            <p>{{ config('app.name') }} &copy; {{ date('Y') }}</p>
        </div>
    </div>
</body>
</html>