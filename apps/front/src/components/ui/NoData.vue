<template>
  <div class="flex flex-col items-center justify-center px-6 py-12">
    <img src="@/assets/chapse/error_light.svg" alt="No data" class="mb-6 h-24 w-24 opacity-80" />
    <div class="text-center">
      <slot>
        <p class="text-neutral-black-font text-lg">{{ t('common.noData') }}</p>
      </slot>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
