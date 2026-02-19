<template>
  <div
    v-for="source in sources"
    :key="source.id"
    class="bg-sage-200 text-sage-600 flex items-center gap-1 rounded-sm p-1 text-sm"
  >
    <Icon icon="fa-link" />
    <Logo
      :domain="source.primaryDomain ?? ''"
      :alt="source.name"
      :name="source.name"
      :width="16"
      :height="16"
      class="shrink-0 rounded-full object-contain"
    />
    <span class="shrink">{{ source.name }}</span>
    <button class="flex items-center justify-center" @click="emit('openEditFilter', 'sources')">
      <Icon icon="fa-pen" />
    </button>
    <button class="flex items-center justify-center" @click="handleRemove(source.id)">
      <Icon icon="fa-xmark" />
    </button>
  </div>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue';
import Logo from '@target/components/global/Logo.vue';
import type { Source } from '@target/types/facet';

interface Props {
  sources: Source[]
}

defineProps<Props>()

const emit = defineEmits<{
  openEditFilter: [filterType: string]
  remove: [id: string]
}>()

const handleRemove = (id: string) => {
  emit('remove', id)
}
</script>
