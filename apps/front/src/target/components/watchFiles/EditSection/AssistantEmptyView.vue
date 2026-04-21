<template>
  <div class="mx-auto flex h-full flex-col items-center justify-center py-4 text-center">
    <div class="my-8">
      <div class="mx-auto flex h-20 w-20 items-center justify-center">
        <img :src="chapse_full" alt="Chapse" />
      </div>
    </div>

    <h3 class="mb-4 text-3xl">
      {{ $t('target.watchFiles.assistant.empty_view.title') }}
    </h3>

    <div class="text-neutral-black-font-font mb-12 text-sm">
      <p>{{ $t('target.watchFiles.assistant.empty_view.description') }}</p>
      <div v-sanitize-html="$t('target.watchFiles.assistant.empty_view.list')" class="mt-4"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import chapse_full from '@target/assets/images/chapse_full.svg'
</script>
