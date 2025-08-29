<template>
  <div class="space-y-2">
    <div 
      class="flex min-h-10 flex-wrap gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2"
      :class="{ 'opacity-50': disabled }"
    >
      <Badge
        v-for="(tag, index) in modelValue"
        :key="`tag-${index}`"
        variant="secondary"
        class="gap-1 pr-1"
      >
        <span>{{ tag }}</span>
        <button
          type="button"
          @click="removeTag(index)"
          :disabled="disabled"
          class="ml-1 rounded-full p-0.5 hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
        >
          <X class="h-3 w-3" />
        </button>
      </Badge>
      <input
        ref="inputRef"
        v-model="inputValue"
        @keydown="handleKeydown"
        @blur="handleBlur"
        :placeholder="modelValue.length === 0 ? placeholder : ''"
        :disabled="disabled"
        class="flex-1 bg-transparent outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed min-w-[120px]"
      />
    </div>
    <p v-if="helperText" class="text-xs text-muted-foreground">
      {{ helperText }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Badge } from '@/components/ui/badge'
import { X } from 'lucide-vue-next'

interface Props {
  modelValue: string[]
  placeholder?: string
  disabled?: boolean
  helperText?: string
  allowDuplicates?: boolean
  maxTags?: number
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Add tags...',
  disabled: false,
  helperText: '',
  allowDuplicates: false,
  maxTags: undefined
})

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
}>()

const inputRef = ref<HTMLInputElement>()
const inputValue = ref('')

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' || event.key === ',') {
    event.preventDefault()
    addTag()
  } else if (event.key === 'Backspace' && inputValue.value === '' && props.modelValue.length > 0) {
    removeTag(props.modelValue.length - 1)
  }
}

function handleBlur() {
  if (inputValue.value.trim()) {
    addTag()
  }
}

function addTag() {
  const tag = inputValue.value.trim()
  
  if (!tag) return
  
  // Check for max tags limit
  if (props.maxTags && props.modelValue.length >= props.maxTags) {
    inputValue.value = ''
    return
  }
  
  // Check for duplicates
  if (!props.allowDuplicates && props.modelValue.includes(tag)) {
    inputValue.value = ''
    return
  }
  
  const newTags = [...props.modelValue, tag]
  emit('update:modelValue', newTags)
  inputValue.value = ''
}

function removeTag(index: number) {
  if (props.disabled) return
  
  const newTags = props.modelValue.filter((_, i) => i !== index)
  emit('update:modelValue', newTags)
}
</script>