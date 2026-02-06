<template>
  <div class="flex flex-col items-center justify-center py-12 px-6">
    <img
      src="@/assets/chapse/error_light.svg"
      alt="No data"
      class="w-24 h-24 mb-6 opacity-80"
    />
    <div class="text-center">
      <slot>
        <p class="text-secondary text-lg">{{ t('common.noData') }}</p>
      </slot>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
