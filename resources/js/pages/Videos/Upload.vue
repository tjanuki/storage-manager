<template>
  <AppLayout title="Upload Video">
    <div class="mx-auto max-w-4xl space-y-6">
      <div>
        <h2 class="text-2xl font-bold tracking-tight">Upload Video</h2>
        <p class="text-muted-foreground">Upload videos up to 10GB to cloud storage</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Video Details</CardTitle>
          <CardDescription>Provide information about your video</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="title">Title</Label>
            <Input
              id="title"
              v-model="form.title"
              placeholder="Enter video title"
              :disabled="isUploading"
            />
          </div>

          <div class="space-y-2">
            <Label for="description">Description (Optional)</Label>
            <Textarea
              id="description"
              v-model="form.description"
              placeholder="Enter video description"
              rows="3"
              :disabled="isUploading"
            />
          </div>

          <div class="space-y-2">
            <Label>Video File</Label>
            <div
              @drop="handleDrop"
              @dragover.prevent
              @dragenter.prevent
              @dragleave.prevent
              class="relative"
            >
              <input
                ref="fileInput"
                type="file"
                accept="video/*"
                @change="handleFileSelect"
                :disabled="isUploading"
                class="hidden"
              />
              
              <div
                @click="() => fileInput?.click()"
                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 px-6 py-10 transition-colors hover:border-muted-foreground/50"
                :class="{ 'pointer-events-none opacity-50': isUploading }"
              >
                <Upload class="mb-4 h-10 w-10 text-muted-foreground" />
                <p class="mb-2 text-sm font-medium">
                  Drop your video here or click to browse
                </p>
                <p class="text-xs text-muted-foreground">
                  Maximum file size: 10GB
                </p>
              </div>
            </div>
          </div>

          <div v-if="selectedFile" class="rounded-lg bg-muted p-4">
            <div class="flex items-start justify-between">
              <div class="space-y-1">
                <p class="text-sm font-medium">{{ selectedFile.name }}</p>
                <p class="text-xs text-muted-foreground">
                  {{ formatFileSize(selectedFile.size) }}
                </p>
              </div>
              <Button
                v-if="!isUploading"
                @click="removeFile"
                variant="ghost"
                size="sm"
              >
                <X class="h-4 w-4" />
              </Button>
            </div>
          </div>

          <div v-if="isUploading" class="space-y-2">
            <div class="flex items-center justify-between text-sm">
              <span>Uploading...</span>
              <span>{{ uploadProgress }}%</span>
            </div>
            <Progress :value="uploadProgress" />
            <p class="text-xs text-muted-foreground">
              {{ formatFileSize(uploadedBytes) }} / {{ formatFileSize(selectedFile?.size || 0) }}
            </p>
          </div>

          <Alert v-if="error" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>Error</AlertTitle>
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>
        </CardContent>
        <CardFooter class="flex justify-end space-x-2">
          <Button
            @click="cancelUpload"
            variant="outline"
            :disabled="!isUploading && !selectedFile"
          >
            {{ isUploading ? 'Cancel' : 'Clear' }}
          </Button>
          <Button
            @click="startUpload"
            :disabled="!canUpload"
          >
            <Upload class="mr-2 h-4 w-4" />
            Upload Video
          </Button>
        </CardFooter>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Progress } from '@/components/ui/progress'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Upload, X, AlertCircle } from 'lucide-vue-next'

interface Props {
  maxFileSize: number
}

const props = defineProps<Props>()

const fileInput = ref<HTMLInputElement>()
const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const uploadProgress = ref(0)
const uploadedBytes = ref(0)
const error = ref<string | null>(null)
const uploadController = ref<AbortController | null>(null)

const form = ref({
  title: '',
  description: '',
})

const canUpload = computed(() => {
  return selectedFile.value && form.value.title && !isUploading.value
})

function formatFileSize(bytes: number): string {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  
  return `${size.toFixed(2)} ${units[unitIndex]}`
}

function handleDrop(event: DragEvent) {
  if (isUploading.value) return
  
  const files = event.dataTransfer?.files
  if (files && files.length > 0) {
    handleFile(files[0])
  }
}

function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    handleFile(target.files[0])
  }
}

function handleFile(file: File) {
  error.value = null
  
  if (!file.type.startsWith('video/')) {
    error.value = 'Please select a valid video file'
    return
  }
  
  if (file.size > props.maxFileSize) {
    error.value = `File size must not exceed ${formatFileSize(props.maxFileSize)}`
    return
  }
  
  selectedFile.value = file
  if (!form.value.title) {
    form.value.title = file.name.replace(/\.[^/.]+$/, '')
  }
}

function removeFile() {
  selectedFile.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

async function startUpload() {
  if (!selectedFile.value || !form.value.title) return
  
  error.value = null
  isUploading.value = true
  uploadProgress.value = 0
  uploadedBytes.value = 0
  uploadController.value = new AbortController()
  
  try {
    // Initiate multipart upload
    const initResponse = await fetch('/videos/initiate-upload', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        filename: selectedFile.value.name,
        filesize: selectedFile.value.size,
        mimetype: selectedFile.value.type,
        title: form.value.title,
        description: form.value.description,
      }),
      signal: uploadController.value.signal,
    })
    
    if (!initResponse.ok) {
      throw new Error('Failed to initiate upload')
    }
    
    const { video_id, upload_id, key } = await initResponse.json()
    
    // Upload file in chunks
    const chunkSize = 100 * 1024 * 1024 // 100MB chunks
    const totalChunks = Math.ceil(selectedFile.value.size / chunkSize)
    const parts: Array<{ PartNumber: number; ETag: string }> = []
    
    for (let i = 0; i < totalChunks; i++) {
      const start = i * chunkSize
      const end = Math.min(start + chunkSize, selectedFile.value.size)
      const chunk = selectedFile.value.slice(start, end)
      const partNumber = i + 1
      
      // Get presigned URL for this part
      const urlResponse = await fetch('/videos/get-upload-url', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          video_id,
          upload_id,
          key,
          part_number: partNumber,
        }),
        signal: uploadController.value.signal,
      })
      
      if (!urlResponse.ok) {
        throw new Error('Failed to get upload URL')
      }
      
      const { url } = await urlResponse.json()
      
      // Upload the chunk
      const uploadResponse = await fetch(url, {
        method: 'PUT',
        body: chunk,
        signal: uploadController.value.signal,
      })
      
      if (!uploadResponse.ok) {
        throw new Error(`Failed to upload part ${partNumber}`)
      }
      
      const etag = uploadResponse.headers.get('ETag')?.replace(/"/g, '')
      if (etag) {
        parts.push({ PartNumber: partNumber, ETag: etag })
      }
      
      uploadedBytes.value = end
      uploadProgress.value = Math.round((uploadedBytes.value / selectedFile.value.size) * 100)
    }
    
    // Complete multipart upload
    const completeResponse = await fetch('/videos/complete-upload', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        video_id,
        upload_id,
        key,
        parts,
      }),
      signal: uploadController.value.signal,
    })
    
    if (!completeResponse.ok) {
      throw new Error('Failed to complete upload')
    }
    
    // Redirect to videos list
    router.visit('/videos')
  } catch (err) {
    if (err instanceof Error && err.name !== 'AbortError') {
      error.value = err.message
    }
  } finally {
    isUploading.value = false
    uploadController.value = null
  }
}

async function cancelUpload() {
  if (isUploading.value && uploadController.value) {
    uploadController.value.abort()
    isUploading.value = false
    uploadProgress.value = 0
    uploadedBytes.value = 0
  } else {
    removeFile()
    form.value.title = ''
    form.value.description = ''
  }
}
</script>