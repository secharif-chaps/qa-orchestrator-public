<template>
  <div
    class="relative p-6 rounded-xl"
    :class="{
      'bg-red-100 text-red-600': type === 'competitor',
      'bg-green-100 text-green-600': type === 'partner',
      'bg-blue-100 text-blue-600': type === 'company',
    }"
  >
    <div class="flex items-center space-x-4">
      <div>
        {{ data.company }}
      </div>
    </div>

    <Handle
      type="source"
      class="absolute top-1/4 opacity-0"
      :position="Position.Bottom"
    />
  </div>
</template>

<script setup lang="ts">
import { Handle, Position, type NodeProps } from '@vue-flow/core'

interface CustomData {
  company: string
}

const props = defineProps<NodeProps<CustomData>>()
</script>

<style scoped>
.vue-flow__handle {
  background-color: var(--color-white);
  border-color: var(--color-slate-200);
  border-width: 2px;
  border-radius: 20px;
}

.vue-flow__handle-connected {
  background-color: var(--color-red-600);
}

.vue-flow__handle:hover {
  background-color: var(--color-orange-600);
}

.vue-flow__handle {
  height: 15px;
  width: 15px;
  right: 1px;
}
</style>
