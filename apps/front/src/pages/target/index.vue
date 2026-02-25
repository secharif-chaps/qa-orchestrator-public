<template>
  <div class="p-6">
    <WatchFileList />
  </div>
</template>

<script setup lang="ts">
import WatchFileList from '@target/components/watchFiles/WatchFileList.vue'
import { useHead } from '@unhead/vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

useHead(() => ({
  title: t('watch_files.title'),
}))
</script>
