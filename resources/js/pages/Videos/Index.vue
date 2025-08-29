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

      <div v-else class="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Title</TableHead>
              <TableHead>Description</TableHead>
              <TableHead>Tags</TableHead>
              <TableHead>Size</TableHead>
              <TableHead>Duration</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Uploaded</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="video in videos.data" :key="video.id">
              <TableCell class="font-medium">{{ video.title }}</TableCell>
              <TableCell>
                <span class="line-clamp-1 max-w-xs">
                  {{ video.description || 'No description' }}
                </span>
              </TableCell>
              <TableCell>
                <div class="flex flex-wrap gap-1">
                  <Badge
                    v-for="tag in video.tags"
                    :key="tag"
                    variant="secondary"
                    class="text-xs"
                  >
                    {{ tag }}
                  </Badge>
                  <span v-if="!video.tags || video.tags.length === 0" class="text-muted-foreground text-sm">
                    -
                  </span>
                </div>
              </TableCell>
              <TableCell>{{ video.formatted_size }}</TableCell>
              <TableCell>{{ video.formatted_duration || '-' }}</TableCell>
              <TableCell>
                <Badge :variant="getStatusVariant(video.status)">
                  {{ video.status }}
                </Badge>
              </TableCell>
              <TableCell>{{ video.uploaded_at || video.created_at }}</TableCell>
              <TableCell class="text-right">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="sm">
                      <MoreVertical class="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem
                      v-if="video.status === 'completed' && video.s3_url"
                      @click="() => router.visit(`/videos/${video.id}/edit`)"
                    >
                      <Play class="mr-2 h-4 w-4" />
                      Edit
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
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
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
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
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
  tags?: string[]
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