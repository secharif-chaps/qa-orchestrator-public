<template>
  <div
    class="bg-sage-900 relative bottom-15 left-1/2 inline-flex -translate-x-1/2 items-center justify-center rounded-full p-1"
  >
    <DocumentValidationButtons
      v-if="isUserEditable && document"
      :document="document"
      dark-mode
    />
  </div>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia';
import DocumentValidationButtons from '~/components/documents/DocumentValidationButtons.vue';
import { useWatchFileStore } from '~/stores/watchFile';
import type { Document } from '~/types/document';

interface Props {
  document?: Document;
}

const { document = undefined } = defineProps<Props>();

const watchFileStore = useWatchFileStore();
const { isUserEditable } = storeToRefs(watchFileStore);
</script>
