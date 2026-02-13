<template>
  <RouterLink
    :to="disabled ? '' : to"
    class="block h-full"
    :class="{ 'pointer-events-none opacity-50': disabled }"
  >
    <div
      class="bg-base-100 border-primary-stroke hover:ring-primary/70 ring-offset-bg2 flex h-full flex-col gap-2 rounded-lg border p-6 ring-offset-2 transition-shadow duration-300 hover:ring-4"
    >
      <div class="flex items-center justify-between">
        <div
          class="bg-primary/20 text-secondary flex size-10 items-center justify-center rounded-lg"
        >
          <i class="fas text-2xl" :class="loading ? 'fa-spinner-third fa-spin' : icon" />
        </div>
        <i class="fa fa-arrow-right text-secondary ml-1"></i>
      </div>
      <h3 class="text-secondary">{{ title }}</h3>

      <p class="text-secondary flex-grow text-sm">
        <span v-if="loading" class="flex items-center gap-2">
          <span>{{ t('common.loading') }}</span>
        </span>
        <span v-else-if="disabled" class="text-secondary">{{ t('common.comingSoon') }}</span>
        <span v-else>{{ description }}</span>
      </p>
    </div>
  </RouterLink>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    required: true,
  },
  to: {
    type: String,
    required: true,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})
</script>
