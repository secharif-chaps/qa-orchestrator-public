<template>
  <div
    v-for="actor in actors"
    :key="actor.id"
    class="bg-sage-200 text-sage-600 flex items-center gap-1 rounded-sm p-1 text-sm"
  >
    <Icon icon="fa-user" />
    <Logo
      :domain="actor.primaryDomain ?? ''"
      :alt="actor.label"
      :name="actor.label"
      :width="16"
      :height="16"
      class="shrink-0 rounded-full object-contain"
    />
    <span class="shrink">{{ actor.label }}</span>
    <button class="flex items-center justify-center" @click="emit('openEditFilter', 'actors')">
      <Icon icon="fa-pen" />
    </button>
    <button class="flex items-center justify-center" @click="handleRemove(actor.id)">
      <Icon icon="fa-xmark" />
    </button>
  </div>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue'
import Logo from '@/components/ui/Logo.vue'
import type { Actor } from '@target/types/facet'

interface Props {
  actors: Actor[]
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
