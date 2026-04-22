<template>
  <div
    class="gap-2xs bg-absolute-pure-white shadow-2 p-xl flex h-full flex-col justify-center rounded-xl"
  >
    <div class="flex items-center gap-4">
      <Avatar :label="initials" size="md" variant="secondary" />

      <!-- Name & Position -->
      <div class="flex min-w-0 flex-1 flex-col text-base">
        <span class="truncate font-bold"> {{ member.firstName }} {{ member.lastName }} </span>
        <span>
          {{ member.position }}
        </span>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <!-- LinkedIn -->
        <Button
          v-if="member.linkedinUrl"
          @click="openLinkedIn"
          variant="tertiary"
          icon="fab fa-linkedin"
          :title="$t('screen.team.viewLinkedIn')"
          icon-only
          size="sm"
          class="shrink-0"
        />
        <Button
          @click="$emit('viewInHierarchy', member)"
          variant="tertiary"
          icon="fa-sitemap"
          :title="$t('screen.team.viewInHierarchy')"
          icon-only
          size="sm"
        />
      </div>
    </div>

    <!-- Subordinates Badge -->
    <div v-if="subordinatesCount > 0" class="border-primary-lighter-stroke pt-2xs border-t">
      <Tag icon="fa-users" color="indigo" size="sm">{{
        $t('screen.team.managingCount', { count: subordinatesCount })
      }}</Tag>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useInitials } from '@/composables/useInitials'
import type { TeamMember } from '@/types/company'
import { Avatar, Button, Tag } from '@owlint/feathers-vue'
import { computed } from 'vue'

interface Props {
  member: TeamMember
  level?: number
}

const { member } = defineProps<Props>()

defineEmits<{
  viewInHierarchy: [member: TeamMember]
}>()

const { getInitials } = useInitials()
const initials = computed(() => getInitials(member.firstName, member.lastName))

const subordinatesCount = computed(() => {
  const countSubordinates = (member: TeamMember): number => {
    if (!member.subordinates || member.subordinates.length === 0) return 0
    return member.subordinates.reduce((total, sub) => {
      return total + 1 + countSubordinates(sub)
    }, 0)
  }
  return countSubordinates(member)
})

const openLinkedIn = () => {
  if (member.linkedinUrl) {
    window.open(member.linkedinUrl, '_blank', 'noopener,noreferrer')
  }
}
</script>
