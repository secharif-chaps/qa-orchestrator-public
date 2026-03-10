<template>
  <div class="h-full">
    <div class="flex h-full gap-4 px-6">
      <div class="w-[70%] pt-4">
        <SourcesList :watch-file-id="watchFileId" />
      </div>

      <div class="w-[30%] pb-4">
        <WatchFileMonitoring :watch-file-id="watchFileId" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import SourcesList from '@target/components/watchFiles/ActivitySection/SourceMonitoring/SourcesListGrouped.vue'
import WatchFileMonitoring from '@target/components/watchFiles/ActivitySection/WatchFileMonitoring.vue'
import { useRoute } from 'vue-router'

const route = useRoute('/target/(watch_files)/[id]/audit')
const watchFileId = route.params.id
</script>
