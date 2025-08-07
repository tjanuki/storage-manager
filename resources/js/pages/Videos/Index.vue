<template>
  <AppLayout title="Videos">
    <div class="mx-auto max-w-7xl space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold tracking-tight">Videos</h2>
          <p class="text-muted-foreground">Manage your uploaded videos</p>
        </div>
        <Button @click="() => router.visit('/videos/upload')">
          <Upload class="mr-2 h-4 w-4" />
          Upload Video
        </Button>
      </div>

      <div v-if="videos.data.length === 0" class="rounded-lg border border-dashed p-12 text-center">
        <Video class="mx-auto h-12 w-12 text-muted-foreground" />
        <h3 class="mt-4 text-lg font-medium">No videos uploaded</h3>
        <p class="mt-2 text-sm text-muted-foreground">
          Get started by uploading your first video
        </p>
        <Button @click="() => router.visit('/videos/upload')" class="mt-4">
          <Upload class="mr-2 h-4 w-4" />
          Upload Video
        </Button>
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card v-for="video in videos.data" :key="video.id">
          <CardHeader>
            <div class="flex items-start justify-between">
              <div class="space-y-1">
                <CardTitle class="line-clamp-1">{{ video.title }}</CardTitle>
                <CardDescription class="line-clamp-2">
                  {{ video.description || 'No description' }}
                </CardDescription>
              </div>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="sm">
                    <MoreVertical class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem
                    v-if="video.status === 'completed' && video.s3_url"
                    @click="() => router.visit(`/videos/${video.id}`)"
                  >
                    <Play class="mr-2 h-4 w-4" />
                    View
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    v-if="video.status === 'completed' && video.s3_url"
                    @click="downloadVideo(video.s3_url, video.title)"
                  >
                    <Download class="mr-2 h-4 w-4" />
                    Download
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    @click="deleteVideo(video.id)"
                    class="text-destructive"
                  >
                    <Trash2 class="mr-2 h-4 w-4" />
                    Delete
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </CardHeader>
          <CardContent>
            <div class="space-y-2 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Size</span>
                <span>{{ video.formatted_size }}</span>
              </div>
              <div v-if="video.formatted_duration" class="flex items-center justify-between">
                <span class="text-muted-foreground">Duration</span>
                <span>{{ video.formatted_duration }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Status</span>
                <Badge :variant="getStatusVariant(video.status)">
                  {{ video.status }}
                </Badge>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Uploaded</span>
                <span>{{ video.uploaded_at || video.created_at }}</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div v-if="videos.links.length > 3" class="flex justify-center">
        <nav class="flex space-x-2">
          <Button
            v-for="link in videos.links"
            :key="link.label"
            @click="() => link.url && router.visit(link.url)"
            :variant="link.active ? 'default' : 'outline'"
            :disabled="!link.url"
            size="sm"
            v-html="link.label"
          />
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Upload, Video, MoreVertical, Play, Download, Trash2 } from 'lucide-vue-next'

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
  s3_url: string | null
}

interface Props {
  videos: {
    data: VideoData[]
    links: Array<{
      url: string | null
      label: string
      active: boolean
    }>
  }
}

defineProps<Props>()

function getStatusVariant(status: string) {
  switch (status) {
    case 'completed':
      return 'default' as const
    case 'uploading':
    case 'processing':
      return 'secondary' as const
    case 'failed':
      return 'destructive' as const
    default:
      return 'outline' as const
  }
}

function downloadVideo(url: string, title: string) {
  const a = document.createElement('a')
  a.href = url
  a.download = title
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

async function deleteVideo(id: number) {
  if (!confirm('Are you sure you want to delete this video?')) {
    return
  }

  try {
    const response = await fetch(`/videos/${id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })

    if (response.ok) {
      router.reload()
    } else {
      alert('Failed to delete video')
    }
  } catch (error) {
    alert('Failed to delete video')
  }
}
</script>