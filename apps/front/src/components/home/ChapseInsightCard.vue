<template>
  <section
    class="bg-sage-50 border-primary-stroke flex flex-wrap content-center items-center justify-center gap-6 rounded-[20px] border p-6"
    :aria-label="$t('dashboard.home.chapse.actionPrompt')"
  >
    <!-- Chaps-e Mascot (left) -->
    <img
      :src="chapseSvg"
      alt="Chaps-e"
      class="animate-float shrink-0 object-contain"
      loading="lazy"
    />

    <!-- Content (right) -->
    <div class="flex min-w-56 flex-1 flex-col gap-4">
      <!-- Action prompt + module pills -->
      <div class="flex flex-col gap-2">
        <p class="text-base leading-[18px] font-bold text-[#182021] dark:text-white">
          {{ $t('dashboard.home.chapse.actionPrompt') }}
        </p>

        <div class="flex flex-wrap gap-2">
          <ButtonModule
            v-for="action in moduleActions"
            :key="action.name"
            :label="action.label"
            :icon="action.icon"
            :color="action.color"
            @click="handleActionClick(action)"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import chapseSvg from '@/assets/chapse/chapse.svg'
import ButtonModule from '@/components/home/ButtonModule.vue'
import type { ModuleAction } from '@/types/module'

const { moduleActions = [] } = defineProps<{
  moduleActions?: ModuleAction[]
}>()

const emit = defineEmits<{
  navigate: [action: ModuleAction]
}>()

const handleActionClick = (action: ModuleAction) => {
  emit('navigate', action)
}
</script>
