<?php

use App\Models\User;
use App\Models\Video;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can initiate video upload with duration', function () {
    $this->actingAs($this->user);

    $response = $this->postJson('/videos/initiate-upload', [
        'filename' => 'test-video.mp4',
        'filesize' => 1024 * 1024 * 100, // 100MB
        'mimetype' => 'video/mp4',
        'title' => 'Test Video',
        'description' => 'Test video description',
        'duration' => 120, // 2 minutes
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'video_id',
        'upload_id',
        'key',
    ]);

    // Check that the video was created with duration
    $video = Video::find($response->json('video_id'));
    expect($video)->not->toBeNull();
    expect($video->title)->toBe('Test Video');
    expect($video->duration)->toBe(120);
    expect($video->formatted_duration)->toBe('02:00');
    expect($video->status)->toBe('uploading');
    expect($video->user_id)->toBe($this->user->id);
});

it('can initiate video upload without duration', function () {
    $this->actingAs($this->user);

    $response = $this->postJson('/videos/initiate-upload', [
        'filename' => 'test-video.mp4',
        'filesize' => 1024 * 1024 * 100, // 100MB
        'mimetype' => 'video/mp4',
        'title' => 'Test Video',
        'description' => 'Test video description',
        // No duration provided
    ]);

    $response->assertSuccessful();

    $video = Video::find($response->json('video_id'));
    expect($video)->not->toBeNull();
    expect($video->duration)->toBeNull();
    expect($video->formatted_duration)->toBeNull();
});

it('validates video upload parameters', function () {
    $this->actingAs($this->user);

    $response = $this->postJson('/videos/initiate-upload', [
        'filename' => 'test-video.mp4',
        'filesize' => 1024 * 1024 * 100,
        'mimetype' => 'video/mp4',
        // Missing title
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['title']);
});

it('validates maximum file size', function () {
    $this->actingAs($this->user);

    $response = $this->postJson('/videos/initiate-upload', [
        'filename' => 'test-video.mp4',
        'filesize' => 10737418241, // 1 byte over 10GB limit
        'mimetype' => 'video/mp4',
        'title' => 'Test Video',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['filesize']);
});

it('formats video duration correctly', function () {
    $video = new Video(['duration' => 3661]); // 1 hour, 1 minute, 1 second
    expect($video->formatted_duration)->toBe('01:01:01');

    $video = new Video(['duration' => 61]); // 1 minute, 1 second
    expect($video->formatted_duration)->toBe('01:01');

    $video = new Video(['duration' => 5]); // 5 seconds
    expect($video->formatted_duration)->toBe('00:05');

    $video = new Video(['duration' => null]);
    expect($video->formatted_duration)->toBeNull();
});
