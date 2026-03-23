<script setup lang="ts">
import Blob from './Blob.vue'
import BlobContainer from './BlobContainer.vue'
</script>

<template>
  <div
    class="pointer-events-none absolute z-0 grid h-screen w-screen grid-cols-2 grid-rows-2 overflow-hidden"
  >
    <BlobContainer class="relative col-span-2 col-start-2 row-span-1">
      <Blob class="blob blob-top dark:opacity-20ap absolute bottom-0" />
    </BlobContainer>
    <BlobContainer class="col-span-1 row-span-2">
      <Blob class="blob self-start justify-self-end dark:opacity-10" />
    </BlobContainer>
  </div>
</template>

<style>
.blob {
  background: var(--blob-gradient);
}

.dark .blob-top {
  background: var(--blob-gradient-top);
}
</style>
