<template>
  <div
    class="bg-base-100 rounded-lg p-4 hover:shadow-lg transition-all duration-200 border border-primary-stroke hover:border-primary/30"
  >
    <div class="flex items-start justify-between gap-4">
      <!-- Avatar & Basic Info -->
      <div class="flex items-center gap-4">
        <div
          class="w-14 h-14 rounded-full flex items-center justify-center flex-shrink-0"
          :class="[
            isExecutive
              ? 'bg-gradient-to-br from-purple-100 to-purple-200 dark:from-purple-900/30 dark:to-purple-800/30 text-purple-600 dark:text-purple-400'
              : 'bg-gradient-to-br from-orange-100 to-orange-200 dark:from-orange-900/30 dark:to-orange-800/30 text-orange-600 dark:text-orange-400',
          ]"
        >
          <i class="fa fa-user text-xl"></i>
        </div>

        <div class="flex-1">
          <h3 class="font-semibold text-primary-light-content text-lg">
            {{ member.firstName }} {{ member.lastName }}
          </h3>
          <p class="text-primary-light-content text-sm mt-0.5">
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
          variant="ghost-primary"
          icon="fab fa-linkedin"
          :title="$t('team.viewLinkedIn', 'View LinkedIn Profile')"
          icon-only
          size="sm"
          class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
        />

        <!-- View in Hierarchy -->
        <Button
          @click="$emit('viewInHierarchy', member)"
          variant="ghost-primary"
          icon="fa fa-sitemap"
          :title="$t('team.viewInHierarchy', 'View in Hierarchy')"
          icon-only
          size="sm"
        />
      </div>
    </div>

    <!-- Subordinates Count -->
    <div v-if="subordinatesCount > 0" class="mt-3 pt-3 border-t border-primary-stroke">
      <div class="flex items-center gap-2 text-sm text-primary-light-content">
        <i class="fa fa-users"></i>
        <span>{{
          $t(
            'team.managingCount',
            `Managing ${subordinatesCount} ${subordinatesCount === 1 ? 'person' : 'people'}`,
          )
        }}</span>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import type { TeamMember } from '@/types/company'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  member: TeamMember
  level?: number
}>()

const emit = defineEmits<{
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
