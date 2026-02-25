<template>
  <div class="border-sage-100 space-y-4 rounded border p-6 shadow">
    <SourcesListTable :watch-file-id="watchFileId" :readonly="readonly" @add="isModalOpen = true" />

    <SourceSelectionModal v-model:is-open="isModalOpen" :watch-file-id="watchFileId" />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import SourceSelectionModal from './SourceSelectionModal.vue'
import SourcesListTable from './SourcesListTable.vue'

interface Props {
  watchFileId: string
  readonly?: boolean
}

const { watchFileId, readonly = false } = defineProps<Props>()

const isModalOpen = ref(false)
</script>

<style>
div[data-reka-popper-content-wrapper] {
  z-index: 40 !important;
}
</style>
