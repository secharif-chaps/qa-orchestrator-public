<template>
  <div class="border rounded-lg p-4">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-secondary">{{ member.firstName }} {{ member.lastName }}</h3>
        <p class="font-semibold text-lg">{{ member.position }}</p>
      </div>
    </div>

    <div v-if="member.subordinates && member.subordinates.length > 0" class="mt-4 pl-4 border-l-2 border-slate-200">
      <TeamMemberCard
        v-for="subordinate in member.subordinates"
        :key="subordinate.position"
        :member="subordinate"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { TeamMember } from '~/types'

defineProps<{
  member: TeamMember
}>()
</script> 