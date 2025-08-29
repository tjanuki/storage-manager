<?php

use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->video = Video::factory()->create(['user_id' => $this->user->id]);
});

it('can attach tags to a video', function () {
    $this->video->attachTag('Laravel');
    $this->video->attachTag('PHP');

    expect($this->video->tags)->toHaveCount(2);
    expect($this->video->tags->pluck('name')->toArray())->toContain('Laravel', 'PHP');
    expect($this->video->tags->pluck('slug')->toArray())->toContain('laravel', 'php');
});

it('can sync tags on a video', function () {
    $this->video->attachTag('Laravel');
    $this->video->attachTag('PHP');

    $this->video->syncTags(['Vue.js', 'JavaScript']);

    expect($this->video->fresh()->tags)->toHaveCount(2);
    expect($this->video->tags->pluck('name')->toArray())->toContain('Vue.js', 'JavaScript');
    expect($this->video->tags->pluck('name')->toArray())->not->toContain('Laravel', 'PHP');
});

it('can detach a tag from a video', function () {
    $this->video->attachTag('Laravel');
    $this->video->attachTag('PHP');
    $this->video->attachTag('MySQL');

    $this->video->detachTag('PHP');

    expect($this->video->fresh()->tags)->toHaveCount(2);
    expect($this->video->tags->pluck('name')->toArray())->toContain('Laravel', 'MySQL');
    expect($this->video->tags->pluck('name')->toArray())->not->toContain('PHP');
});

it('can check if a video has a specific tag', function () {
    $this->video->attachTag('Laravel');
    $this->video->attachTag('PHP');

    expect($this->video->hasTag('Laravel'))->toBeTrue();
    expect($this->video->hasTag('PHP'))->toBeTrue();
    expect($this->video->hasTag('Python'))->toBeFalse();
});

it('does not create duplicate tags when attaching the same tag multiple times', function () {
    $this->video->attachTag('Laravel');
    $this->video->attachTag('Laravel');
    $this->video->attachTag('laravel'); // Different case

    expect($this->video->fresh()->tags)->toHaveCount(1);
    expect(Tag::where('slug', 'laravel')->count())->toBe(1);
});

it('creates tags with proper slugs', function () {
    $this->video->attachTag('Vue.js Framework');
    $this->video->attachTag('React & Redux');

    $tags = $this->video->tags;

    expect($tags->firstWhere('name', 'Vue.js Framework')->slug)->toBe('vuejs-framework');
    expect($tags->firstWhere('name', 'React & Redux')->slug)->toBe('react-redux');
});

it('can get videos by tag through the tag model', function () {
    $video2 = Video::factory()->create(['user_id' => $this->user->id]);

    $this->video->attachTag('Laravel');
    $video2->attachTag('Laravel');
    $video2->attachTag('PHP');

    $laravelTag = Tag::where('slug', 'laravel')->first();
    $phpTag = Tag::where('slug', 'php')->first();

    expect($laravelTag->videos)->toHaveCount(2);
    expect($phpTag->videos)->toHaveCount(1);
    expect($laravelTag->usage_count)->toBe(2);
    expect($phpTag->usage_count)->toBe(1);
});

it('maintains timestamps on the pivot table', function () {
    $this->video->attachTag('Laravel');

    $pivot = $this->video->tags()->first()->pivot;

    expect($pivot->created_at)->not->toBeNull();
    expect($pivot->updated_at)->not->toBeNull();
});

it('handles empty tag sync gracefully', function () {
    $this->video->attachTag('Laravel');
    $this->video->attachTag('PHP');

    $this->video->syncTags([]);

    expect($this->video->fresh()->tags)->toHaveCount(0);
});

it('can query videos with specific tags', function () {
    $video2 = Video::factory()->create(['user_id' => $this->user->id]);
    $video3 = Video::factory()->create(['user_id' => $this->user->id]);

    $this->video->attachTag('Laravel');
    $this->video->attachTag('PHP');
    $video2->attachTag('Laravel');
    $video2->attachTag('Vue.js');
    $video3->attachTag('Python');

    $laravelVideos = Video::whereHas('tags', function ($query) {
        $query->where('slug', 'laravel');
    })->get();

    $phpAndLaravelVideos = Video::whereHas('tags', function ($query) {
        $query->whereIn('slug', ['laravel', 'php']);
    })->get();

    expect($laravelVideos)->toHaveCount(2);
    expect($phpAndLaravelVideos)->toHaveCount(2);
});
