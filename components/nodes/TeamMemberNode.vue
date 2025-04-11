<template>
  <div class="bg-white p-4 rounded-lg shadow border border-slate-200">
    <div class="flex flex-col">
      <div class="font-semibold text-primary">{{ data.position }}</div>
      <div class="text-sm text-slate-600">{{ data.firstName }} {{ data.lastName }}</div>
    </div>
    <Handle
      type="target"
      :position="Position.Top"
      class="!bg-slate-400"
    />
    <Handle
      type="source"
      :position="Position.Bottom"
      class="!bg-slate-400"
    />
  </div>
</template>

<script setup lang="ts">
import { Handle, Position, type NodeProps } from '@vue-flow/core'

interface TeamMemberData {
  position: string
  firstName: string
  lastName: string
}

const props = defineProps<NodeProps<TeamMemberData>>()
</script>

<style scoped>
.vue-flow__handle {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
</style> 