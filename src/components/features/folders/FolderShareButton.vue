<template>
  <div v-if="showButton">
    <Button
      variant="tertiary"
      icon="fa fa-share-alt"
      :label="$t('folder.actions.share', 'Share')"
      @click="showModal = true"
    />

    <FolderShareModal
      v-model="showModal"
      :folder-id="folderId"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, toRef } from 'vue'
import { Button } from '@owlint/feathers-vue'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import type { Folder } from '@/types/folder'
import FolderShareModal from './FolderShareModal.vue'

interface Props {
  folder: Folder
}

const props = defineProps<Props>()

const folderRef = toRef(props, 'folder')
const { canManageSharing } = useFolderPermissions(folderRef)

const showModal = ref(false)

// Only show button if user can manage sharing (is owner)
const showButton = computed(() => canManageSharing.value)

// Expose folder ID for the modal
const folderId = computed(() => props.folder?.id || '')
</script>
