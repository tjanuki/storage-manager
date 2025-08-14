<template>
  <AppLayout title="Upload Video">
    <div class="mx-auto max-w-4xl space-y-6">
      <div class="flex items-center gap-4">
        <Button
          @click="() => router.visit('/videos')"
          variant="ghost"
          size="sm"
          class="h-8 w-8 p-0"
        >
          <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
          <h2 class="text-2xl font-bold tracking-tight">Upload Video</h2>
          <p class="text-muted-foreground">Upload videos up to 10GB to cloud storage</p>
        </div>
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

          <div v-if="isUploading" class="space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Loader2 class="h-4 w-4 animate-spin text-primary" />
                <span class="text-sm font-medium">
                  {{ uploadPhase === 'initiating' ? 'Preparing upload...' :
                     uploadPhase === 'uploading' ? 'Uploading video...' :
                     uploadPhase === 'completing' ? 'Finalizing upload...' : 'Processing...' }}
                </span>
              </div>
              <span class="text-sm font-semibold">{{ uploadProgress }}%</span>
            </div>
            
            <div class="relative">
              <Progress :value="uploadProgress" class="h-3" />
              <div 
                class="absolute inset-0 h-3 overflow-hidden rounded-full"
                v-if="uploadPhase === 'uploading'"
              >
                <div class="h-full animate-pulse bg-primary/20" />
              </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-xs">
              <div class="space-y-1">
                <p class="text-muted-foreground">Uploaded</p>
                <p class="font-medium">
                  {{ formatFileSize(uploadedBytes) }} / {{ formatFileSize(selectedFile?.size || 0) }}
                </p>
              </div>
              <div class="space-y-1">
                <p class="text-muted-foreground">Speed</p>
                <p class="font-medium flex items-center gap-1">
                  <TrendingUp class="h-3 w-3" />
                  {{ formatSpeed(uploadSpeed) }}
                </p>
              </div>
            </div>
            
            <div v-if="uploadPhase === 'uploading' && uploadSpeed > 0" class="rounded-lg bg-muted/50 p-3">
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-1 text-muted-foreground">
                  <Clock class="h-3 w-3" />
                  <span>Time remaining</span>
                </div>
                <span class="font-medium">{{ formatTime(estimatedTimeRemaining) }}</span>
              </div>
            </div>
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
            :variant="isUploading ? 'destructive' : 'outline'"
            :disabled="!isUploading && !selectedFile"
          >
            <X v-if="isUploading" class="mr-2 h-4 w-4" />
            {{ isUploading ? 'Cancel Upload' : 'Clear' }}
          </Button>
          <Button
            @click="startUpload"
            :disabled="!canUpload || isUploading"
          >
            <Upload v-if="!isUploading" class="mr-2 h-4 w-4" />
            <Loader2 v-else class="mr-2 h-4 w-4 animate-spin" />
            {{ isUploading ? 'Uploading...' : 'Upload Video' }}
          </Button>
        </CardFooter>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Progress } from '@/components/ui/progress'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Upload, X, AlertCircle, Loader2, Clock, TrendingUp, ArrowLeft } from 'lucide-vue-next'

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

// Upload speed tracking
const uploadStartTime = ref<number>(0)
const uploadSpeed = ref(0) // bytes per second
const estimatedTimeRemaining = ref(0) // seconds
const uploadPhase = ref<'idle' | 'initiating' | 'uploading' | 'completing'>('idle')
const lastBytesUpdate = ref(0)
const lastTimeUpdate = ref(0)

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

function formatSpeed(bytesPerSecond: number): string {
  if (bytesPerSecond === 0) return '0 MB/s'
  const mbps = bytesPerSecond / (1024 * 1024)
  return `${mbps.toFixed(2)} MB/s`
}

function formatTime(seconds: number): string {
  if (!isFinite(seconds) || seconds <= 0) return 'Calculating...'
  
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const secs = Math.floor(seconds % 60)
  
  if (hours > 0) {
    return `${hours}h ${minutes}m ${secs}s`
  } else if (minutes > 0) {
    return `${minutes}m ${secs}s`
  } else {
    return `${secs}s`
  }
}

function updateUploadSpeed(currentBytes: number) {
  const now = Date.now()
  
  // Only update speed calculation every 100ms to avoid too frequent updates
  if (now - lastTimeUpdate.value < 100) {
    return
  }
  
  if (lastTimeUpdate.value > 0 && uploadStartTime.value > 0) {
    const timeDiff = (now - lastTimeUpdate.value) / 1000 // seconds
    const bytesDiff = currentBytes - lastBytesUpdate.value
    
    if (timeDiff > 0 && bytesDiff > 0) {
      // Calculate instantaneous speed
      const instantSpeed = bytesDiff / timeDiff
      
      // Also calculate overall average speed
      const totalTime = (now - uploadStartTime.value) / 1000
      const averageSpeed = currentBytes / totalTime
      
      // Weighted average between instant and overall speed
      uploadSpeed.value = uploadSpeed.value === 0 
        ? instantSpeed 
        : (instantSpeed * 0.3 + averageSpeed * 0.3 + uploadSpeed.value * 0.4)
      
      // Calculate ETA
      const remainingBytes = (selectedFile.value?.size || 0) - currentBytes
      estimatedTimeRemaining.value = uploadSpeed.value > 0 
        ? remainingBytes / uploadSpeed.value 
        : 0
    }
  }
  
  lastBytesUpdate.value = currentBytes
  lastTimeUpdate.value = now
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
  uploadPhase.value = 'initiating'
  uploadStartTime.value = Date.now()
  uploadSpeed.value = 0
  estimatedTimeRemaining.value = 0
  lastBytesUpdate.value = 0
  lastTimeUpdate.value = Date.now()
  
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
    uploadPhase.value = 'uploading'
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
      
      // Upload the chunk using XMLHttpRequest for progress tracking
      const etag = await new Promise<string>((resolve, reject) => {
        const xhr = new XMLHttpRequest()
        
        // Track upload progress for this chunk
        const chunkStartBytes = start // Use the actual start position of this chunk
        xhr.upload.onprogress = (event) => {
          if (event.lengthComputable) {
            // Calculate total uploaded bytes: previous chunks + current chunk progress
            const currentChunkProgress = event.loaded
            const totalUploaded = chunkStartBytes + currentChunkProgress
            uploadedBytes.value = Math.min(totalUploaded, selectedFile.value.size)
            uploadProgress.value = Math.round((uploadedBytes.value / selectedFile.value.size) * 100)
            updateUploadSpeed(uploadedBytes.value)
          }
        }
        
        xhr.onload = () => {
          if (xhr.status === 200) {
            const etag = xhr.getResponseHeader('ETag')?.replace(/"/g, '') || ''
            resolve(etag)
          } else {
            reject(new Error(`Failed to upload part ${partNumber}`))
          }
        }
        
        xhr.onerror = () => reject(new Error(`Network error uploading part ${partNumber}`))
        xhr.onabort = () => reject(new Error('Upload cancelled'))
        
        xhr.open('PUT', url, true)
        xhr.send(chunk)
        
        // Store xhr for potential cancellation
        if (uploadController.value) {
          uploadController.value.signal.addEventListener('abort', () => xhr.abort())
        }
      })
      
      if (etag) {
        parts.push({ PartNumber: partNumber, ETag: etag })
      }
    }
    
    // Complete multipart upload
    uploadPhase.value = 'completing'
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
    uploadPhase.value = 'idle'
    uploadSpeed.value = 0
    estimatedTimeRemaining.value = 0
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

// Prevent accidental navigation during upload
function handleBeforeUnload(event: BeforeUnloadEvent) {
  if (isUploading.value) {
    event.preventDefault()
    event.returnValue = 'You have an upload in progress. Are you sure you want to leave?'
    return event.returnValue
  }
}

onMounted(() => {
  window.addEventListener('beforeunload', handleBeforeUnload)
})

onUnmounted(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
})
</script>