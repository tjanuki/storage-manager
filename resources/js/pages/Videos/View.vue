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
          <CardFooter>
            <div class="flex w-full flex-col gap-4">
              <div v-if="isSharing" class="flex items-center gap-2 rounded-lg border p-3">
                <input
                  :value="shareUrl"
                  readonly
                  class="flex-1 bg-transparent text-sm outline-none"
                />
                <Button
                  @click="copyLink"
                  size="sm"
                  variant="ghost"
                >
                  <Check v-if="copied" class="h-4 w-4 text-green-600" />
                  <Copy v-else class="h-4 w-4" />
                </Button>
              </div>
              <div class="flex gap-2">
                <Button
                  @click="downloadVideo"
                  variant="outline"
                >
                  <Download class="mr-2 h-4 w-4" />
                  Download
                </Button>
                <Button
                  @click="toggleSharing"
                  :variant="isSharing ? 'secondary' : 'outline'"
                >
                  <Share2 class="mr-2 h-4 w-4" />
                  {{ isSharing ? 'Stop Sharing' : 'Share' }}
                </Button>
                <Dialog v-if="isSharing" v-model:open="emailDialogOpen">
                  <DialogTrigger asChild>
                    <Button variant="outline">
                      <Mail class="mr-2 h-4 w-4" />
                      Send Email
                    </Button>
                  </DialogTrigger>
                  <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                      <DialogTitle>Share Video via Email</DialogTitle>
                      <DialogDescription>
                        Send this video link to up to 5 email addresses
                      </DialogDescription>
                    </DialogHeader>
                    <form @submit.prevent="sendEmail" class="space-y-4">
                      <div class="space-y-2">
                        <Label for="emails">Email Addresses</Label>
                        <Textarea
                          id="emails"
                          v-model="emailForm.emails"
                          placeholder="Enter email addresses separated by commas"
                          :disabled="isSending"
                          class="min-h-[80px]"
                        />
                        <p class="text-xs text-muted-foreground">
                          Separate multiple emails with commas (max 5)
                        </p>
                      </div>
                      <div class="space-y-2">
                        <Label for="sender_name">Your Name (Optional)</Label>
                        <Input
                          id="sender_name"
                          v-model="emailForm.sender_name"
                          placeholder="Enter your name"
                          :disabled="isSending"
                        />
                      </div>
                      <div class="space-y-2">
                        <Label for="message">Personal Message (Optional)</Label>
                        <Textarea
                          id="message"
                          v-model="emailForm.message"
                          placeholder="Add a personal note to the email"
                          :disabled="isSending"
                          class="min-h-[100px]"
                          maxlength="500"
                        />
                        <p class="text-xs text-muted-foreground">
                          {{ emailForm.message.length }}/500 characters
                        </p>
                      </div>
                      <Alert v-if="emailStatus.type" :class="emailStatus.type === 'success' ? 'border-green-500' : 'border-red-500'">
                        <CheckCircle v-if="emailStatus.type === 'success'" class="h-4 w-4 text-green-500" />
                        <AlertCircle v-else class="h-4 w-4 text-red-500" />
                        <AlertDescription>
                          {{ emailStatus.message }}
                        </AlertDescription>
                      </Alert>
                      <DialogFooter>
                        <Button type="button" variant="outline" @click="emailDialogOpen = false" :disabled="isSending">
                          Cancel
                        </Button>
                        <Button type="submit" :disabled="isSending || !emailForm.emails.trim()">
                          <Send v-if="!isSending" class="mr-2 h-4 w-4" />
                          <Loader2 v-else class="mr-2 h-4 w-4 animate-spin" />
                          {{ isSending ? 'Sending...' : 'Send Email' }}
                        </Button>
                      </DialogFooter>
                    </form>
                  </DialogContent>
                </Dialog>
                <Button
                  @click="deleteVideo"
                  variant="destructive"
                >
                  <Trash2 class="mr-2 h-4 w-4" />
                  Delete
                </Button>
              </div>
            </div>
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { ArrowLeft, Download, Trash2, Share2, Copy, Check, Mail, Send, Loader2, CheckCircle, AlertCircle } from 'lucide-vue-next'
import { ref } from 'vue'

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
  is_public: boolean
  share_uuid: string | null
  public_url: string | null
  shared_at: string | null
}

interface Props {
  video: VideoData
}

const props = defineProps<Props>()

const isSharing = ref(props.video.is_public)
const shareUrl = ref(props.video.public_url)
const copied = ref(false)
const emailDialogOpen = ref(false)
const isSending = ref(false)
const emailForm = ref({
  emails: '',
  sender_name: '',
  message: ''
})
const emailStatus = ref<{ type: 'success' | 'error' | null; message: string }>({
  type: null,
  message: ''
})

function downloadVideo() {
  const a = document.createElement('a')
  a.href = props.video.s3_url
  a.download = props.video.title
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

async function toggleSharing() {
  try {
    const response = await fetch(`/videos/${props.video.id}/toggle-sharing`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Content-Type': 'application/json',
      },
    })

    if (response.ok) {
      const data = await response.json()
      isSharing.value = data.is_public
      shareUrl.value = data.public_url
    } else {
      alert('Failed to toggle sharing')
    }
  } catch {
    alert('Failed to toggle sharing')
  }
}

async function copyLink() {
  if (shareUrl.value) {
    try {
      await navigator.clipboard.writeText(shareUrl.value)
      copied.value = true
      setTimeout(() => {
        copied.value = false
      }, 2000)
    } catch {
      alert('Failed to copy link')
    }
  }
}

async function sendEmail() {
  if (!emailForm.value.emails.trim() || !props.video.share_uuid) return

  isSending.value = true
  emailStatus.value = { type: null, message: '' }

  try {
    const response = await fetch(`/share/${props.video.share_uuid}/email`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify(emailForm.value)
    })

    const data = await response.json()

    if (response.ok) {
      emailStatus.value = {
        type: 'success',
        message: data.message || 'Email sent successfully!'
      }
      emailForm.value = { emails: '', sender_name: '', message: '' }
      setTimeout(() => {
        emailDialogOpen.value = false
        emailStatus.value = { type: null, message: '' }
      }, 2000)
    } else {
      emailStatus.value = {
        type: 'error',
        message: data.error || 'Failed to send email'
      }
    }
  } catch {
    emailStatus.value = {
      type: 'error',
      message: 'Network error. Please try again.'
    }
  } finally {
    isSending.value = false
  }
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
  } catch {
    alert('Failed to delete video')
  }
}
</script>