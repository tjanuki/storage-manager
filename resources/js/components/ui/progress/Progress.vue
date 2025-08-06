<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { cn } from '@/lib/utils'

export interface ProgressProps {
  value?: number
  max?: number
  class?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<ProgressProps>(), {
  max: 100,
  value: 0,
})

const progressValue = computed(() => {
  const percentage = (props.value / props.max) * 100
  return Math.min(100, Math.max(0, percentage))
})
</script>

<template>
  <div
    role="progressbar"
    :aria-valuemax="max"
    :aria-valuemin="0"
    :aria-valuenow="value"
    :aria-label="`${progressValue}%`"
    :class="cn('relative h-2 w-full overflow-hidden rounded-full bg-primary/20', props.class)"
  >
    <div
      class="h-full w-full flex-1 bg-primary transition-all"
      :style="`transform: translateX(-${100 - progressValue}%)`"
    />
  </div>
</template>