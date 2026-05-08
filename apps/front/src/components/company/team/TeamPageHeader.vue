<template>
  <div class="gap-2xs flex flex-wrap">
    <StatCard
      v-for="stat in stats"
      :key="stat.label"
      :icon="stat.icon"
      :label="stat.label"
      :value="stat.value"
      :color="stat.color"
    />
  </div>
</template>

<script lang="ts" setup>
import StatCard from '@/components/ui/StatCard.vue'
import type { TeamMember } from '@/types/company'
import type { StatCardColor } from '@/types/ui'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  team: TeamMember[]
}

const { team } = defineProps<Props>()

// Calculate total members recursively
const totalMembers = computed(() => {
  const countMembers = (members: TeamMember[]): number => {
    if (!members) return 0
    return members.reduce((total, member) => {
      const subordinatesCount = member.subordinates ? countMembers(member.subordinates) : 0
      return total + 1 + subordinatesCount
    }, 0)
  }
  return countMembers(team)
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
  return countByLevel(team, 1)
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
  return countManagers(team)
})

// Count unique departments
const departmentsCount = computed(() => {
  const departments = new Set<string>()

  const collectDepartments = (members: TeamMember[]) => {
    if (!members) return
    members.forEach((member) => {
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

  collectDepartments(team)
  return departments.size
})

const stats = computed<{ icon: string; label: string; value: number; color: StatCardColor }[]>(
  () => [
    {
      icon: 'fa-users',
      label: t('screen.team.totalMembers'),
      value: totalMembers.value,
      color: 'sage',
    },
    {
      icon: 'fa-user-tie',
      label: t('screen.team.executives'),
      value: executivesCount.value,
      color: 'indigo',
    },
    {
      icon: 'fa-user-cog',
      label: t('screen.team.managers'),
      value: managersCount.value,
      color: 'blue',
    },
    {
      icon: 'fa-building',
      label: t('screen.team.departments'),
      value: departmentsCount.value,
      color: 'cherry',
    },
  ],
)
</script>
