<template>
  <div
    class="bg-absolute-pure-white w-[300px] rounded-3xl p-4 shadow-lg hover:ring-4 dark:bg-slate-900"
    :class="{
      'ring-focus-stroke': data.level <= 1,
      'ring-orange-400': data.level > 1,
      'ring-4': selected,
    }"
  >
    <div class="gap-2xs text-neutral-black-font flex items-center">
      <Avatar
        :label="getInitials(data.firstName, data.lastName)"
        variant="secondary"
        class="shrink-0"
      />
      <div class="flex flex-col">
        <div class="text-xs font-bold">{{ data.firstName }} {{ data.lastName }}</div>
        <div class="text-xs">{{ data.position }}</div>
      </div>
    </div>

    <Handle
      type="target"
      :position="Position.Top"
      class="border-4! border-indigo-600! bg-indigo-600! dark:border-slate-700! dark:bg-slate-900!"
      :class="{
        'opacity-0': data.level === 0,
        'bg-focus-stroke!': data.level === 1,
        'bg-orange-400!': data.level > 1,
      }"
    />
    <Handle type="source" :position="Position.Bottom" class="bg-indigo-600! opacity-0" />
  </div>
</template>

<script setup lang="ts">
import { useInitials } from '@/composables/useInitials'
import { Avatar } from '@owlint/feathers-vue'
import { Handle, Position, type NodeProps } from '@vue-flow/core'

const { getInitials } = useInitials()

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
