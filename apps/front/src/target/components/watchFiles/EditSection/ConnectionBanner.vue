<template>
  <div
    v-show="!isConnected"
    class="flex items-center justify-center gap-2 px-4 py-2"
    :class="{
      'bg-warning-100 text-warning-700': isReconnecting,
      'bg-error-100 text-error-700': isDisconnected,
    }"
    role="alert"
  >
    <Icon
      :icon="isReconnecting ? 'fa-spinner-third' : 'fa-circle-exclamation'"
      :class="{ 'animate-spin': isReconnecting }"
    />
    <span class="text-sm font-medium">
      {{ isReconnecting ? t('watch_files.chat.reconnecting') : t('watch_files.chat.offline') }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue'
import { useMercureStore } from '@target/stores/mercure'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const mercureStore = useMercureStore()
const { isConnected, isDisconnected, isReconnecting } = storeToRefs(mercureStore)
</script>
