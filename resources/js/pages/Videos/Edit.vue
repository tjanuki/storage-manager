<template>
  <AppLayout :title="`Edit: ${video.title}`">
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
        <!-- Video Player Section -->
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

        <!-- Edit Form Section -->
        <Card>
          <CardHeader>
            <CardTitle>Edit Video Details</CardTitle>
            <CardDescription>
              Update the title and description of your video
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form @submit.prevent="saveChanges" class="space-y-4">
              <div class="space-y-2">
                <Label for="title">Title</Label>
                <Input
                  id="title"
                  v-model="editForm.title"
                  placeholder="Enter video title"
                  :disabled="isSaving"
                />
              </div>
              <div class="space-y-2">
                <Label for="description">Description</Label>
                <Textarea
                  id="description"
                  v-model="editForm.description"
                  placeholder="Enter video description (optional)"
                  :disabled="isSaving"
                  class="min-h-[100px]"
                />
              </div>
              <div class="flex gap-2">
                <Button type="submit" :disabled="isSaving || !hasChanges">
                  <Save v-if="!isSaving" class="mr-2 h-4 w-4" />
                  <Loader2 v-else class="mr-2 h-4 w-4 animate-spin" />
                  {{ isSaving ? 'Saving...' : 'Save Changes' }}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  @click="resetForm"
                  :disabled="isSaving || !hasChanges"
                >
                  Cancel
                </Button>
              </div>
              <Alert v-if="saveStatus.type" :class="saveStatus.type === 'success' ? 'border-green-500' : 'border-red-500'">
                <CheckCircle v-if="saveStatus.type === 'success'" class="h-4 w-4 text-green-500" />
                <AlertCircle v-else class="h-4 w-4 text-red-500" />
                <AlertDescription>
                  {{ saveStatus.message }}
                </AlertDescription>
              </Alert>
            </form>
          </CardContent>
        </Card>

        <!-- Sharing Section -->
        <Card>
          <CardHeader>
            <CardTitle>Sharing Options</CardTitle>
            <CardDescription>
              Manage public sharing and send the video link via email
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div class="space-y-1">
                  <Label>Public Sharing</Label>
                  <p class="text-sm text-muted-foreground">
                    {{ isSharing ? 'Your video is publicly accessible' : 'Enable to create a shareable link' }}
                  </p>
                </div>
                <Button
                  @click="toggleSharing"
                  :variant="isSharing ? 'secondary' : 'outline'"
                  size="sm"
                >
                  <Share2 class="mr-2 h-4 w-4" />
                  {{ isSharing ? 'Disable Sharing' : 'Enable Sharing' }}
                </Button>
              </div>

              <div v-if="isSharing" class="space-y-4">
                <div class="flex items-center gap-2 rounded-lg border p-3">
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

                <Dialog v-model:open="emailDialogOpen">
                  <DialogTrigger asChild>
                    <Button variant="outline" class="w-full">
                      <Mail class="mr-2 h-4 w-4" />
                      Send via Email
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
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Video Information -->
        <Card>
          <CardHeader>
            <CardTitle>Video Information</CardTitle>
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
            <Button
              @click="deleteVideo"
              variant="destructive"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Delete Video
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { ArrowLeft, Save, Trash2, Share2, Copy, Check, Mail, Send, Loader2, CheckCircle, AlertCircle } from 'lucide-vue-next'
import { ref, computed } from 'vue'

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

// Edit form state
const editForm = ref({
  title: props.video.title,
  description: props.video.description || ''
})

const originalForm = {
  title: props.video.title,
  description: props.video.description || ''
}

const isSaving = ref(false)
const saveStatus = ref<{ type: 'success' | 'error' | null; message: string }>({
  type: null,
  message: ''
})

const hasChanges = computed(() => {
  return editForm.value.title !== originalForm.title ||
         editForm.value.description !== originalForm.description
})

// Sharing state
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

async function saveChanges() {
  if (!hasChanges.value) return

  isSaving.value = true
  saveStatus.value = { type: null, message: '' }

  try {
    const response = await fetch(`/videos/${props.video.id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify(editForm.value)
    })

    const data = await response.json()

    if (response.ok) {
      saveStatus.value = {
        type: 'success',
        message: 'Changes saved successfully!'
      }
      originalForm.title = editForm.value.title
      originalForm.description = editForm.value.description
      setTimeout(() => {
        saveStatus.value = { type: null, message: '' }
      }, 3000)
    } else {
      saveStatus.value = {
        type: 'error',
        message: data.message || 'Failed to save changes'
      }
    }
  } catch {
    saveStatus.value = {
      type: 'error',
      message: 'Network error. Please try again.'
    }
  } finally {
    isSaving.value = false
  }
}

function resetForm() {
  editForm.value.title = originalForm.title
  editForm.value.description = originalForm.description
  saveStatus.value = { type: null, message: '' }
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
  if (!confirm('Are you sure you want to delete this video? This action cannot be undone.')) {
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