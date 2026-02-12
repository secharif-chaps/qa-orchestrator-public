<template>
  <div class="space-y-4">
    <!-- Search and Filter Bar -->
    <div class="flex flex-col gap-4 sm:flex-row">
      <div class="flex-1">
        <Input
          id="team-search"
          v-model="searchQuery"
          :placeholder="$t('team.searchPlaceholder')"
          icon="fa-search"
        />
      </div>

      <div class="flex gap-2">
        <!-- Level Filter -->
        <select
          v-model="selectedLevel"
          class="bg-base-200 border-border text-secondary focus:ring-primary/50 rounded-lg border px-4 py-2 focus:ring-2 focus:outline-none"
        >
          <option value="">{{ $t('team.levels.all') }}</option>
          <option value="0">{{ $t('team.levels.ceo') }}</option>
          <option value="1">{{ $t('team.levels.executives') }}</option>
          <option value="2">{{ $t('team.levels.managers') }}</option>
          <option value="3">{{ $t('team.levels.teamMembers') }}</option>
        </select>

        <!-- View Mode Toggle -->
        <div class="bg-base-200 flex rounded-lg p-1">
          <Button
            @click="viewMode = 'grid'"
            :variant="viewMode === 'grid' ? 'primary' : 'tertiary'"
            icon="fa fa-th"
            icon-only
            size="sm"
            :title="$t('team.views.grid')"
          />
          <Button
            @click="viewMode = 'list'"
            :variant="viewMode === 'list' ? 'primary' : 'tertiary'"
            icon="fa fa-list"
            icon-only
            size="sm"
            :title="$t('team.views.list')"
          />
        </div>
      </div>
    </div>

    <!-- Team Members Grid/List -->
    <div v-if="filteredMembers.length > 0">
      <!-- Grid View -->
      <div v-if="viewMode === 'grid'" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <TeamMemberCard
          v-for="item in filteredMembers"
          :key="`${item.member.position}-${item.member.firstName}-${item.member.lastName}`"
          :member="item.member"
          :level="item.level"
          @view-in-hierarchy="$emit('viewInHierarchy', $event)"
        />
      </div>

      <!-- List View -->
      <div v-else class="space-y-2">
        <TeamMemberCard
          v-for="item in filteredMembers"
          :key="`${item.member.position}-${item.member.firstName}-${item.member.lastName}`"
          :member="item.member"
          :level="item.level"
          @view-in-hierarchy="$emit('viewInHierarchy', $event)"
        />
      </div>
    </div>

    <!-- No Results -->
    <div v-else class="bg-base-100 rounded-lg p-8 text-center">
      <div class="text-secondary mb-3 text-4xl">
        <i class="fa fa-search"></i>
      </div>
      <p class="text-secondary">
        {{ $t('team.noResults') }}
      </p>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue'
import type { TeamMember } from '@/types/company'
import TeamMemberCard from './TeamMemberCard.vue'
import { Button, Input } from '@owlint/feathers-vue'

const props = defineProps<{
  team: TeamMember[]
}>()

const emit = defineEmits<{
  viewInHierarchy: [member: TeamMember]
}>()

const searchQuery = ref('')
const selectedLevel = ref('')
const viewMode = ref<'grid' | 'list'>('grid')

// Flatten team hierarchy with levels
const flattenedMembers = computed(() => {
  const result: { member: TeamMember; level: number }[] = []

  const flatten = (members: TeamMember[], level = 0) => {
    members.forEach((member) => {
      result.push({ member, level })
      if (member.subordinates && member.subordinates.length > 0) {
        flatten(member.subordinates, level + 1)
      }
    })
  }

  if (props.team) {
    flatten(props.team)
  }

  return result
})

// Filter members based on search and level
const filteredMembers = computed(() => {
  let filtered = [...flattenedMembers.value]

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter((item) => {
      const fullName = `${item.member.firstName} ${item.member.lastName}`.toLowerCase()
      const position = item.member.position.toLowerCase()
      return fullName.includes(query) || position.includes(query)
    })
  }

  // Level filter
  if (selectedLevel.value !== '') {
    const level = parseInt(selectedLevel.value)
    filtered = filtered.filter((item) => item.level === level)
  }

  return filtered
})
</script>
