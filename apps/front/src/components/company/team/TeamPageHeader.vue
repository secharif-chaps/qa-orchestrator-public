<template>
  <div class="bg-base-100 rounded-lg p-6">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h2 class="text-secondary flex items-center gap-3 text-2xl font-bold">
          <i class="fa fa-users"></i>
          <span>{{ $t('screen.team.title') }}</span>
        </h2>
        <p class="text-secondary mt-1">
          {{ $t('screen.team.subtitle') }}
        </p>
      </div>

      <!-- Export Button -->
      <Button
        @click="$emit('export')"
        variant="secondary"
        icon="fa fa-download"
        :label="$t('screen.team.export')"
      />
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Total Members -->
      <div class="bg-base-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-secondary text-sm">
              {{ $t('screen.team.totalMembers') }}
            </p>
            <p class="text-secondary mt-1 text-2xl font-bold">{{ totalMembers }}</p>
          </div>
          <div class="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-full">
            <i class="fa fa-users text-secondary"></i>
          </div>
        </div>
      </div>

      <!-- Executives -->
      <div class="bg-base-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-secondary text-sm">
              {{ $t('screen.team.executives') }}
            </p>
            <p class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">
              {{ executivesCount }}
            </p>
          </div>
          <div
            class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30"
          >
            <i class="fa fa-user-tie text-purple-600 dark:text-purple-400"></i>
          </div>
        </div>
      </div>

      <!-- Managers -->
      <div class="bg-base-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-secondary text-sm">
              {{ $t('screen.team.managers') }}
            </p>
            <p class="mt-1 text-2xl font-bold text-orange-600 dark:text-orange-400">
              {{ managersCount }}
            </p>
          </div>
          <div
            class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30"
          >
            <i class="fa fa-user-cog text-orange-600 dark:text-orange-400"></i>
          </div>
        </div>
      </div>

      <!-- Departments -->
      <div class="bg-base-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-secondary text-sm">
              {{ $t('screen.team.departments') }}
            </p>
            <p class="text-secondary mt-1 text-2xl font-bold">{{ departmentsCount }}</p>
          </div>
          <div
            class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
          >
            <i class="fa fa-building text-blue-600 dark:text-blue-400"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Insights Alert -->
    <Alert
      v-if="hasInsights"
      variant="info"
      :title="$t('screen.team.insights.title')"
      :description="teamInsights"
      icon="fa-lightbulb"
      class="mt-6"
    />
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import type { TeamMember } from '@/types/company'
import { Alert, Button } from '@owlint/feathers-vue'

const props = defineProps<{
  team: TeamMember[]
  teamInsights?: string
}>()

defineEmits<{
  export: []
}>()

// Calculate total members recursively
const totalMembers = computed(() => {
  const countMembers = (members: TeamMember[]): number => {
    if (!members) return 0
    return members.reduce((total, member) => {
      const subordinatesCount = member.subordinates ? countMembers(member.subordinates) : 0
      return total + 1 + subordinatesCount
    }, 0)
  }
  return countMembers(props.team || [])
})

// Count executives (level 0 and 1)
const executivesCount = computed(() => {
  const countByLevel = (members: TeamMember[], targetLevel: number, currentLevel = 0): number => {
    if (!members) return 0
    return members.reduce((total, member) => {
      const isTarget = currentLevel <= targetLevel ? 1 : 0
      const subordinatesCount = member.subordinates
        ? countByLevel(member.subordinates, targetLevel, currentLevel + 1)
        : 0
      return total + isTarget + subordinatesCount
    }, 0)
  }
  return countByLevel(props.team || [], 1)
})

// Count managers (level 2)
const managersCount = computed(() => {
  const countManagers = (members: TeamMember[], currentLevel = 0): number => {
    if (!members) return 0
    return members.reduce((total, member) => {
      const isManager = currentLevel === 2 ? 1 : 0
      const subordinatesCount = member.subordinates
        ? countManagers(member.subordinates, currentLevel + 1)
        : 0
      return total + isManager + subordinatesCount
    }, 0)
  }
  return countManagers(props.team || [])
})

// Count unique departments
const departmentsCount = computed(() => {
  const departments = new Set<string>()

  const collectDepartments = (members: TeamMember[]) => {
    if (!members) return
    members.forEach((member) => {
      // Extract department from position (e.g., "VP of Sales" -> "Sales")
      if (member.position) {
        const dept = member.position.replace(/^(VP of |Head of |Director of |Manager of )/i, '')
        if (dept && !dept.includes('CEO') && !dept.includes('Chief')) {
          departments.add(dept)
        }
      }
      if (member.subordinates) {
        collectDepartments(member.subordinates)
      }
    })
  }

  collectDepartments(props.team || [])
  return departments.size
})

const hasInsights = computed(() => {
  return !!props.teamInsights && props.teamInsights.trim().length > 0
})
</script>
