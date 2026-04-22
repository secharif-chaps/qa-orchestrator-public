<template>
  <div class="flex flex-col gap-3">
    <!-- Header: Title + View Toggle -->
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-bold">
        {{ $t('screen.team.employees.title') }}
      </h3>

      <!-- View Mode Toggle -->
      <Toggle v-model="viewMode" :options="viewModeOptions" variant="pill" />
    </div>

    <!-- Grid View -->
    <div
      v-if="flattenedMembers.length > 0 && viewMode === 'grid'"
      class="gap-xs grid md:grid-cols-2"
    >
      <div
        v-for="item in flattenedMembers"
        :key="`grid-${item.member.position}-${item.member.firstName}-${item.member.lastName}`"
      >
        <TeamMemberCard
          :member="item.member"
          :level="item.level"
          @view-in-hierarchy="$emit('viewInHierarchy', $event)"
        />
      </div>
    </div>

    <!-- Table View -->
    <Table
      v-else-if="flattenedMembers.length > 0 && viewMode === 'table'"
      :fields="tableFields"
      :items="flattenedMembers"
      :row-key="
        (item: FlattenedMember) =>
          `${item.member.position}-${item.member.firstName}-${item.member.lastName}`
      "
      striped
    >
      <template #head(action)="{ field }">
        <td class="px-3 py-3.5 text-sm font-medium">
          <div class="flex justify-end">
            {{ field.label }}
          </div>
        </td>
      </template>
      <template #cell(name)="{ item }">
        <td class="px-2 py-2">
          <div class="flex items-center gap-2">
            <Avatar
              :label="`${item.member.firstName?.charAt(0) ?? ''}${item.member.lastName?.charAt(0) ?? ''}`"
              size="md"
              variant="secondary"
            />
            <span class="truncate font-medium">
              {{ item.member.firstName }} {{ item.member.lastName }}
            </span>
          </div>
        </td>
      </template>

      <template #cell(position)="{ item }">
        <td class="truncate px-2 py-2">
          {{ item.member.position }}
        </td>
      </template>

      <template #cell(management)="{ item }">
        <td class="px-2 py-2">
          <div v-if="getSubordinatesCount(item.member) > 0" class="flex">
            <Tag
              color="indigo"
              icon="fa-users"
              :label="
                t('screen.team.table.managingCount', { count: getSubordinatesCount(item.member) })
              "
              size="sm"
              class="shrink-0"
            />
          </div>
        </td>
      </template>

      <template #cell(action)="{ item }">
        <td class="px-2 py-2">
          <div class="flex justify-end">
            <Button
              @click="$emit('viewInHierarchy', item.member)"
              variant="tertiary"
              icon="fa-sitemap"
              :title="t('screen.team.viewInHierarchy')"
              icon-only
              size="sm"
            />
          </div>
        </td>
      </template>
    </Table>

    <!-- No Results -->
    <div v-else class="rounded-block bg-base-100 p-8 text-center">
      <div class="mb-3 text-4xl opacity-40">
        <Icon icon="fa-users" />
      </div>
      <p class="text-sm opacity-60">
        {{ $t('screen.team.noResults') }}
      </p>
    </div>
  </div>
</template>

<script lang="ts" setup>
import type { TeamMember } from '@/types/company'
import { Avatar, Button, Icon, Table, Tag, Toggle } from '@owlint/feathers-vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import TeamMemberCard from './TeamMemberCard.vue'

interface Props {
  team: TeamMember[]
}

const { team } = defineProps<Props>()

interface Emits {
  viewInHierarchy: [member: TeamMember]
}

defineEmits<Emits>()

const { t } = useI18n()

const viewMode = ref<'grid' | 'table'>('grid')

interface FlattenedMember {
  member: TeamMember
  level: number
}

const tableFields = computed(() => [
  { key: 'name', label: t('screen.team.table.name') },
  { key: 'position', label: t('screen.team.table.position') },
  { key: 'management', label: t('screen.team.table.management') },
  { key: 'action', label: t('screen.team.table.action'), class: 'w-1/3 text-right' },
])

const viewModeOptions = computed(() => [
  {
    value: 'grid',
    label: t('screen.team.views.grid'),
    icon: 'fa-grid-2',
  },
  {
    value: 'table',
    label: t('screen.team.views.table'),
    icon: 'fa-table-list',
  },
])

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

  if (team) {
    flatten(team)
  }

  return result
})

const getSubordinatesCount = (member: TeamMember): number => {
  if (!member.subordinates || member.subordinates.length === 0) return 0
  return member.subordinates.reduce((total, sub) => {
    return total + 1 + getSubordinatesCount(sub)
  }, 0)
}
</script>
