<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'original_filename' => $this->faker->word().'.mp4',
            's3_key' => 'videos/'.$this->faker->uuid().'.mp4',
            's3_bucket' => 'storage-manager-videos',
            's3_region' => 'us-east-1',
            'size' => $this->faker->numberBetween(1000000, 100000000), // 1MB to 100MB
            'mime_type' => 'video/mp4',
            'duration' => $this->faker->numberBetween(30, 3600), // 30 seconds to 1 hour
            'status' => $this->faker->randomElement(['processing', 'completed', 'failed']),
            'upload_id' => null,
            'share_uuid' => null,
            'is_public' => false,
            'shared_at' => null,
            'metadata' => [
                'width' => 1920,
                'height' => 1080,
                'codec' => 'h264',
                'bitrate' => '5000000',
            ],
            'uploaded_at' => now(),
        ];
    }

    /**
     * Indicate that the video is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the video is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'upload_id' => $this->faker->uuid(),
        ]);
    }

    /**
     * Indicate that the video is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Indicate that the video is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
            'share_uuid' => $this->faker->uuid(),
            'shared_at' => now(),
        ]);
    }
}
