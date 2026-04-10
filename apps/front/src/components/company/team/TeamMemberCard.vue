<template>
  <div
    class="border-primary-lighter-stroke hover:border-primary/30 rounded-sm border bg-white p-4 transition-all duration-200 hover:shadow-lg"
  >
    <div class="flex items-start justify-between gap-4">
      <!-- Avatar & Basic Info -->
      <div class="flex items-center gap-4">
        <div
          class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full"
          :class="[
            isExecutive
              ? 'bg-gradient-to-br from-purple-100 to-purple-200 text-purple-600 dark:from-purple-900/30 dark:to-purple-800/30 dark:text-purple-400'
              : 'bg-gradient-to-br from-orange-100 to-orange-200 text-orange-600 dark:from-orange-900/30 dark:to-orange-800/30 dark:text-orange-400',
          ]"
        >
          <i class="fa fa-user text-xl"></i>
        </div>

        <div class="flex-1">
          <h3 class="text-neutral-black-font text-lg font-semibold">
            {{ member.firstName }} {{ member.lastName }}
          </h3>
          <p class="text-neutral-black-font mt-0.5 text-sm">
            {{ member.position }}
          </p>
        </div>
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
          class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
        />

        <!-- View in Hierarchy -->
        <Button
          @click="$emit('viewInHierarchy', member)"
          variant="tertiary"
          icon="fa fa-sitemap"
          :title="$t('screen.team.viewInHierarchy')"
          icon-only
          size="sm"
        />
      </div>
    </div>

    <!-- Subordinates Count -->
    <div v-if="subordinatesCount > 0" class="border-primary-lighter-stroke mt-3 border-t pt-3">
      <div class="text-neutral-black-font flex items-center gap-2 text-sm">
        <i class="fa fa-users"></i>
        <span>{{
          $t(
            'screen.team.managingCount',
            `Managing ${subordinatesCount} ${subordinatesCount === 1 ? 'person' : 'people'}`,
          )
        }}</span>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import type { TeamMember } from '@/types/company'
import { Button } from '@owlint/feathers-vue'
import { computed } from 'vue'

const props = defineProps<{
  member: TeamMember
  level?: number
}>()

defineEmits<{
  viewInHierarchy: [member: TeamMember]
}>()

const isExecutive = computed(() => {
  return props.level === 0 || props.level === 1
})

const subordinatesCount = computed(() => {
  const countSubordinates = (member: TeamMember): number => {
    if (!member.subordinates || member.subordinates.length === 0) return 0
    return member.subordinates.reduce((total, sub) => {
      return total + 1 + countSubordinates(sub)
    }, 0)
  }
  return countSubordinates(props.member)
})

const openLinkedIn = () => {
  if (props.member.linkedinUrl) {
    window.open(props.member.linkedinUrl, '_blank', 'noopener,noreferrer')
  }
}
</script>
