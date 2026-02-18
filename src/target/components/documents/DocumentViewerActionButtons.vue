<template>
  <div
    class="bg-sage-900 relative bottom-15 left-1/2 inline-flex -translate-x-1/2 items-center justify-center rounded-full p-1"
  >
    <DocumentValidationButtons v-if="isUserEditable && document" :document="document" dark-mode />
  </div>
</template>

<script setup lang="ts">
import DocumentValidationButtons from '@target/components/documents/DocumentValidationButtons.vue'
import { useWatchFileStore } from '@target/stores/watchFile'
import type { Document } from '@target/types/document'
import { storeToRefs } from 'pinia'

interface Props {
  document?: Document
}

const { document = undefined } = defineProps<Props>()

const watchFileStore = useWatchFileStore()
const { isUserEditable } = storeToRefs(watchFileStore)
</script>
