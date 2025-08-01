<template>
  <div
    class="bg-white dark:bg-slate-900 p-4 rounded-lg shadow-lg w-[300px] hover:ring-4"
    :class="{
      ' ring-purple-600': data.level <= 1,
      ' ring-orange-400': data.level > 1,
      'ring-4': selected
    }"
  >
    <div class="flex items-center gap-2">
      <div class="size-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center shrink-0">
        <i class="fa fa-user text-bg1"></i>
      </div>
      <div class="flex flex-col">
        <div class="font-semibold">{{ data.firstName }} {{ data.lastName }}</div>
        <div class="text-sm text-secondary">{{ data.position }}</div>
      </div>
    </div>

    <Handle
      type="target"
      :position="Position.Top"
      class="!border-4 !bg-white dark:!bg-slate-900 !border-slate-200 dark:!border-slate-700"
      :class="{
        'opacity-0': data.level === 0,
        '!bg-purple-600': data.level === 1,
        '!bg-orange-400': data.level > 1
      }"
    />
    <Handle type="source" :position="Position.Bottom" class="!bg-slate-400 opacity-0" />
  </div>
</template>

<script setup lang="ts">
import { Handle, Position, useVueFlow, type NodeProps } from '@vue-flow/core'

// todo check if the team member is the head of the company

interface TeamMemberData {
  position: string
  firstName: string
  lastName: string
  level: number
  selected: boolean
}

const props = defineProps<NodeProps<TeamMemberData>>()

const { edges } = useVueFlow()
</script>

<style scoped>
.vue-flow__handle {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border-width: 4px;
}
</style>
