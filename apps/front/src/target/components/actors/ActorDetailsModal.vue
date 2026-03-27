<template>
  <Modal v-model:display-modal="isOpen" size="7xl" @close="closeModal">
    <template #title>
      <div class="flex justify-between">
        <span class="text-lg text-gray-900">
          {{ $t('target.watchFiles.actors.details_modal.title') }}
        </span>
        <button
          class="flex cursor-pointer items-center p-1 text-gray-500 transition-colors hover:text-gray-700"
          @click="closeModal"
        >
          <Icon icon="fa-xmark" class="h-5 w-5" />
        </button>
      </div>
    </template>
    <template #description>
      <div class="relative h-[620px] overflow-hidden overflow-y-auto">
        <ActorDetails :actor="actor" :watch-file-id="watchFileId" />
      </div>
    </template>
    <template #footer>
      <div class="flex justify-end">
        <Button @click="closeModal">
          {{ $t('common.button.close') }}
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Button, Icon, Modal } from '@owlint/feathers-vue'
import type { WatchFileActor } from '@target/types/watchFile'
import ActorDetails from './ActorDetails.vue'

interface Props {
  actor: WatchFileActor
  watchFileId?: string
}

const { actor, watchFileId = undefined } = defineProps<Props>()

const isOpen = defineModel<boolean>('isOpen', {
  required: true,
})

const closeModal = () => {
  isOpen.value = false
}
</script>
