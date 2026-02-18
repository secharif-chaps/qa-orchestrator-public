<template>
  <WatchFileConfiguration />
</template>

<script setup lang="ts">
import WatchFileConfiguration from '~/components/watchFiles/EditSection/Main.vue'

//Need to redirect after creation???

definePage({
  meta: {
    layout: 'watch-file',
  },
})
</script>
