<template>
  <AppLayout :title="video.title">
    <div class="mx-auto max-w-7xl space-y-6">
      <div class="flex items-center justify-between">
        <Button
          @click="() => router.visit('/videos')"
          variant="ghost"
          size="sm"
        >
          <ArrowLeft class="mr-2 h-4 w-4" />
          Back to Videos
        </Button>
      </div>

      <div class="space-y-6">
        <div class="aspect-video w-full overflow-hidden rounded-lg bg-black">
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
                <p class="text-sm text-muted-foreground">Uploaded</p>
                <p class="font-medium">{{ video.uploaded_at || video.created_at }}</p>
              </div>
            </div>
          </CardContent>
          <CardFooter class="flex gap-2">
            <Button
              @click="downloadVideo"
              variant="outline"
            >
              <Download class="mr-2 h-4 w-4" />
              Download
            </Button>
            <Button
              @click="deleteVideo"
              variant="destructive"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </Button>
          </CardFooter>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { ArrowLeft, Download, Trash2 } from 'lucide-vue-next'

interface VideoData {
  id: number
  title: string
  description: string | null
  size: number
  formatted_size: string
  duration: number | null
  formatted_duration: string | null
  status: string
  mime_type: string
  uploaded_at: string | null
  created_at: string
  s3_url: string
  thumbnail_url?: string | null
}

interface Props {
  video: VideoData
}

const props = defineProps<Props>()

function downloadVideo() {
  const a = document.createElement('a')
  a.href = props.video.s3_url
  a.download = props.video.title
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

async function deleteVideo() {
  if (!confirm('Are you sure you want to delete this video?')) {
    return
  }

  try {
    const response = await fetch(`/videos/${props.video.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })

    if (response.ok) {
      router.visit('/videos')
    } else {
      alert('Failed to delete video')
    }
  } catch (error) {
    alert('Failed to delete video')
  }
}
</script>