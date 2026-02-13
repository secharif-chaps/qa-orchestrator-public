<template>
  <WatchFileConfiguration :watch-file-id="watchFileId" />
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import WatchFileConfiguration from '~/components/watchFiles/EditSection/Main.vue';

const route = useRoute();
const watchFileId = route.params.id as string;

definePage({
  meta: {
    layout: 'watch-file',
  },
});
</script>
