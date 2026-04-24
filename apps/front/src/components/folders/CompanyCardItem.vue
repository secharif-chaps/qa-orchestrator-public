<template>
  <div
    @click="$emit('click')"
    class="border-primary-lighter-stroke ring-primary/50 ring-offset-bg2 flex h-16 cursor-pointer items-center rounded-sm border bg-white p-2 hover:ring-2"
  >
    <div class="flex min-w-0 items-center gap-2">
      <!-- Company Logo -->
      <div
        class="ring-primary-stroke flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded bg-white ring-1"
      >
        <Logo
          :website="props.website"
          :name="props.name"
          :alt="props.name"
          :width="40"
          :height="40"
        />
      </div>

      <!-- Company Name -->
      <div class="min-w-0 flex-1">
        <div class="truncate text-sm font-medium">{{ name }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Logo from '@/components/ui/Logo.vue'

interface Props {
  name: string
  website?: string
}

const props = defineProps<Props>()

defineEmits<{
  click: []
}>()
</script>
