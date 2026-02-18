<template>
  <div class="p-6">
    <WatchFileList />
  </div>
</template>

<script setup lang="ts">
import { useHead } from '@unhead/vue'
import { useI18n } from 'vue-i18n'
import WatchFileList from '~/components/watchFiles/WatchFileList.vue'

const { t } = useI18n()

useHead(() => ({
  title: t('watch_files.title'),
}))
</script>
