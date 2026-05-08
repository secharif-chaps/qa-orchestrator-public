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

    <div class="text-neutral-black-font mb-12 text-sm">
      <p>{{ $t('target.watchFiles.assistant.empty_view.description') }}</p>
      <div class="mt-4">
        <p>{{ $t('target.watchFiles.assistant.empty_view.listIntro') }}</p>
        <ul class="mt-2 list-none text-left">
          <li class="flex items-start gap-2">
            <Icon icon="fa-check" class="text-success mt-1 shrink-0" />
            <span>{{ $t('target.watchFiles.assistant.empty_view.listItemDefineObjectives') }}</span>
          </li>
          <li class="flex items-start gap-2">
            <Icon icon="fa-check" class="text-success mt-1 shrink-0" />
            <span>{{ $t('target.watchFiles.assistant.empty_view.listItemIdentifyPlayers') }}</span>
          </li>
          <li class="flex items-start gap-2">
            <Icon icon="fa-check" class="text-success mt-1 shrink-0" />
            <span>{{ $t('target.watchFiles.assistant.empty_view.listItemConfigureSources') }}</span>
          </li>
          <li class="flex items-start gap-2">
            <Icon icon="fa-check" class="text-success mt-1 shrink-0" />
            <span>{{ $t('target.watchFiles.assistant.empty_view.listItemCollectCriteria') }}</span>
          </li>
          <li class="flex items-start gap-2">
            <Icon icon="fa-check" class="text-success mt-1 shrink-0" />
            <span>{{ $t('target.watchFiles.assistant.empty_view.listItemScheduleCollect') }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue'
import chapse_full from '@target/assets/images/chapse_full.svg'
</script>
