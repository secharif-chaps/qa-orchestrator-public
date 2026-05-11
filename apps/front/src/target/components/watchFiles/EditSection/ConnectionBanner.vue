<template>
  <div
    v-show="isDisconnected"
    class="bg-error-100 text-error-700 absolute inset-x-0 top-0 z-10 flex items-center justify-center gap-2 px-4 py-2"
    role="alert"
  >
    <Icon icon="fa-circle-exclamation" />
    <span class="text-sm font-medium">{{ t('target.watchFiles.chat.offline') }}</span>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue'
import { useMercureStore } from '@/stores/mercure'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const mercureStore = useMercureStore()
const { isDisconnected } = storeToRefs(mercureStore)
</script>
