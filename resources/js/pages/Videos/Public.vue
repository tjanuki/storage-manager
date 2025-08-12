<template>
  <div class="min-h-screen bg-background">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div class="space-y-6">
        <div class="flex items-center justify-center">
          <h1 class="text-2xl font-semibold text-foreground">Shared Video</h1>
        </div>

        <div class="space-y-6">
          <div class="aspect-video w-full overflow-hidden rounded-lg bg-black shadow-lg">
            <video
              :src="video.s3_url"
              :poster="video.thumbnail_url"
              controls
              controlsList="nodownload"
              class="h-full w-full"
              :autoplay="false"
            >
              Your browser does not support the video tag.
            </video>
          </div>

          <Card>
            <CardHeader>
              <CardTitle class="text-2xl">{{ video.title }}</CardTitle>
              <CardDescription v-if="video.description">
                {{ video.description }}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-1">
                  <p class="text-sm text-muted-foreground">Size</p>
                  <p class="font-medium">{{ video.formatted_size }}</p>
                </div>
                <div v-if="video.formatted_duration" class="space-y-1">
                  <p class="text-sm text-muted-foreground">Duration</p>
                  <p class="font-medium">{{ video.formatted_duration }}</p>
                </div>
                <div class="space-y-1">
                  <p class="text-sm text-muted-foreground">Format</p>
                  <p class="font-medium">{{ video.mime_type }}</p>
                </div>
                <div class="space-y-1">
                  <p class="text-sm text-muted-foreground">Shared on</p>
                  <p class="font-medium">{{ video.shared_at }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <div class="flex justify-center">
            <p class="text-sm text-muted-foreground">
              This video has been shared with you. The link will expire after some time.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

interface VideoData {
  id: number
  title: string
  description: string | null
  size: number
  formatted_size: string
  duration: number | null
  formatted_duration: string | null
  mime_type: string
  uploaded_at: string | null
  shared_at: string | null
  s3_url: string
  thumbnail_url?: string | null
}

interface Props {
  video: VideoData
}

defineProps<Props>()
</script>