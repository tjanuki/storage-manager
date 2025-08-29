<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'original_filename',
        's3_key',
        's3_bucket',
        's3_region',
        'size',
        'mime_type',
        'duration',
        'status',
        'upload_id',
        'share_uuid',
        'is_public',
        'shared_at',
        'metadata',
        'uploaded_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'duration' => 'integer',
        'is_public' => 'boolean',
        'metadata' => 'array',
        'uploaded_at' => 'datetime',
        'shared_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    public function getS3UrlAttribute(): string
    {
        return "https://{$this->s3_bucket}.s3.{$this->s3_region}.amazonaws.com/{$this->s3_key}";
    }

    public function getFormattedSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if (! $this->duration) {
            return null;
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function enableSharing(): void
    {
        if (! $this->share_uuid) {
            $this->share_uuid = Str::uuid()->toString();
        }
        $this->is_public = true;
        $this->shared_at = now();
        $this->save();
    }

    public function disableSharing(): void
    {
        $this->is_public = false;
        $this->save();
    }

    public function getPublicUrlAttribute(): ?string
    {
        if (! $this->is_public || ! $this->share_uuid) {
            return null;
        }

        return url("/share/{$this->share_uuid}");
    }

    public function syncTags(array $tagNames): void
    {
        $tagIds = collect($tagNames)->map(function ($tagName) {
            return Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            )->id;
        })->toArray();

        $this->tags()->sync($tagIds);
    }

    public function attachTag(string $tagName): void
    {
        $tag = Tag::firstOrCreate(
            ['slug' => Str::slug($tagName)],
            ['name' => $tagName]
        );

        $this->tags()->syncWithoutDetaching([$tag->id]);
    }

    public function detachTag(string $tagName): void
    {
        $tag = Tag::where('slug', Str::slug($tagName))->first();

        if ($tag) {
            $this->tags()->detach($tag->id);
        }
    }

    public function hasTag(string $tagName): bool
    {
        return $this->tags()->where('slug', Str::slug($tagName))->exists();
    }
}
