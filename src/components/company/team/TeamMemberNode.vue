<template>
  <div
    class="w-[300px] rounded-lg bg-white p-4 shadow-lg hover:ring-4 dark:bg-slate-900"
    :class="{
      'ring-purple-600': data.level <= 1,
      'ring-orange-400': data.level > 1,
      'ring-4': selected,
    }"
  >
    <div class="flex items-center gap-2">
      <div
        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-200 dark:bg-slate-700"
      >
        <i class="fa fa-user text-bg1"></i>
      </div>
      <div class="flex flex-col">
        <div class="font-semibold">{{ data.firstName }} {{ data.lastName }}</div>
        <div class="text-secondary text-sm">{{ data.position }}</div>
      </div>
    </div>

    <Handle
      type="target"
      :position="Position.Top"
      class="!border-4 !border-slate-200 !bg-white dark:!border-slate-700 dark:!bg-slate-900"
      :class="{
        'opacity-0': data.level === 0,
        '!bg-purple-600': data.level === 1,
        '!bg-orange-400': data.level > 1,
      }"
    />
    <Handle type="source" :position="Position.Bottom" class="!bg-slate-400 opacity-0" />
  </div>
</template>

<script setup lang="ts">
import { Handle, Position, type NodeProps } from '@vue-flow/core'

// todo check if the team member is the head of the company

interface TeamMemberData {
  position: string
  firstName: string
  lastName: string
  level: number
  selected: boolean
}

defineProps<NodeProps<TeamMemberData>>()
</script>

<style scoped>
.vue-flow__handle {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border-width: 4px;
}
</style>
