<template>
  <div class="space-y-4">
    <!-- Search and Filter Bar -->
    <div class="flex flex-col sm:flex-row gap-4">
      <div class="flex-1">
        <Input
          v-model="searchQuery"
          :placeholder="$t('team.searchPlaceholder', 'Search by name or position...')"
          icon="fa fa-search"
          clearable
        />
      </div>

      <div class="flex gap-2">
        <!-- Level Filter -->
        <select
          v-model="selectedLevel"
          class="px-4 py-2 rounded-lg bg-base-200 border border-border text-secondary focus:outline-none focus:ring-2 focus:ring-primary/50"
        >
          <option value="">{{ $t('team.allLevels', 'All Levels') }}</option>
          <option value="0">CEO</option>
          <option value="1">Executives</option>
          <option value="2">Managers</option>
          <option value="3">Team Members</option>
        </select>

        <!-- View Mode Toggle -->
        <div class="flex bg-base-200 rounded-lg p-1">
          <Button
            @click="viewMode = 'grid'"
            :variant="viewMode === 'grid' ? 'primary' : 'tertiary'"
            icon="fa fa-th"
            icon-only
            size="sm"
            :title="$t('team.gridView', 'Grid View')"
          />
          <Button
            @click="viewMode = 'list'"
            :variant="viewMode === 'list' ? 'primary' : 'tertiary'"
            icon="fa fa-list"
            icon-only
            size="sm"
            :title="$t('team.listView', 'List View')"
          />
        </div>
      </div>
    </div>

    <!-- Team Members Grid/List -->
    <div v-if="filteredMembers.length > 0">
      <!-- Grid View -->
      <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
      <div class="text-4xl text-secondary mb-3">
        <i class="fa fa-search"></i>
      </div>
      <p class="text-secondary">
        {{ $t('team.noResults', 'No team members found matching your criteria') }}
      </p>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue'
import type { TeamMember } from '@/types/company'
import TeamMemberCard from './TeamMemberCard.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'

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
