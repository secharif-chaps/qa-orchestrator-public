<template>
  <WatchFileConfiguration :watch-file-id="watchFileId" />
</template>

<script setup lang="ts">
import WatchFileConfiguration from '@target/components/watchFiles/EditSection/Main.vue'
import { useRoute } from 'vue-router'

const route = useRoute('/target/(watch_files)/[id]/scope')
const watchFileId = route.params.id
</script>
