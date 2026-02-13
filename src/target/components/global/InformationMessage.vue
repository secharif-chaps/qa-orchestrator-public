<template>
  <div
    class="flex items-center justify-center"
    :class="{
      'flex-1': verticalAlign === 'center',
    }"
  >
    <div
      class="flex gap-4 rounded-lg"
      :class="[
        widthClass,
        { [borderColorClass]: !transparent },
        { 'shadow-1': !transparent },
        bgColorClass,
        isChatMessage ? 'items-start p-4' : 'items-center p-2',
      ]"
    >
      <div
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
        :class="iconBackgroundClass"
      >
        <Icon
          v-if="icon"
          :icon="icon"
          class="h-4 w-4 shrink-0"
          :class="[iconColorClass, { 'animate-spin': icon === 'fa-spinner' }]"
          :lib="fill ? 'fa-solid' : 'fa-regular'"
        />
      </div>
      <div :class="textColorClass" class="min-w-0 flex-1">
        <div
          class="text-left"
          :class="isChatMessage ? 'pb-1 text-sm font-bold' : 'font-medium'"
        >
          {{ title }}
        </div>
        <div
          v-if="description"
          v-sanitize-html="description"
          class="text-left text-sm"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue';
import { computed } from 'vue';

interface Props {
  title: string;
  description?: string;
  color?: 'error' | 'info' | 'success';
  icon?: string;
  width?: 'md' | 'lg' | 'full';
  verticalAlign?: 'top' | 'center';
  fill?: boolean;
  transparent?: boolean;
  isChatMessage?: boolean;
}

const {
  color = 'info',
  width = 'md',
  icon = 'fa-circle-info',
  verticalAlign = 'top',
  fill = false,
  description = '',
  transparent = false,
  isChatMessage = false,
} = defineProps<Props>();

const widthClass = computed(() => {
  return 'w-' + width;
});

const borderColorClass = computed(() => {
  const colorMap = {
    error: 'border rounded-xs border-red-800',
    info: 'border rounded-xs border-sage-800',
    success: 'border rounded-xs border-green-800',
  };
  return colorMap[color];
});
const bgColorClass = computed(() => {
  const colorMap = {
    error: 'bg-red-50',
    info: 'bg-sage-50',
    success: 'bg-green-50',
  };
  return colorMap[color];
});

const iconBackgroundClass = computed(() => {
  if (isChatMessage) {
    return 'bg-red-600';
  }
  const colorMap = {
    error: transparent ? 'bg-red-600' : 'bg-red-100',
    info: transparent ? 'bg-sage-600' : 'bg-sage-100',
    success: transparent ? 'bg-green-600' : 'bg-green-100',
  };
  return colorMap[color];
});

const iconColorClass = computed(() => {
  if (isChatMessage) {
    return 'text-white';
  }
  const colorMap = {
    error: transparent ? 'text-white' : 'text-red-800',
    info: transparent ? 'text-white' : 'text-sage-800',
    success: transparent ? 'text-white' : 'text-green-800',
  };
  return colorMap[color];
});

const textColorClass = computed(() => {
  const colorMap = {
    error: 'text-red-700',
    info: 'text-sage-800',
    success: 'text-green-700',
  };
  return colorMap[color];
});
</script>
