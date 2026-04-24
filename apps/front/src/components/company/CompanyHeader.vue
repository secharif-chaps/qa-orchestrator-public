<template>
  <div
    class="bg-neutral-white gap-3xs py-xs sticky top-0 z-10 -mx-4 flex flex-col px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
  >
    <!-- Row 1: Back link + Privacy tag + Actions -->
    <div class="flex items-center justify-between">
      <!-- Left: Back to folder -->
      <Button
        size="sm"
        :as="RouterLink"
        :to="{ name: '/folders/[folderId]/(folderId)', params: { folderId } }"
        icon="fa-chevron-left"
        variant="neutral"
      >
        {{ t('screen.company.header.backToFolder') }}
      </Button>

      <!-- Right: Privacy tag + Action buttons -->
      <div class="flex shrink-0 items-center gap-2">
        <CompanyTranslation v-model="selectedLanguage" :company-id="companyId" />
        <Export />
        <span v-if="isOwner" :title="refreshButtonTooltip">
          <Button
            variant="tertiary"
            icon="fa-refresh"
            size="sm"
            :label="t('screen.company.refresh.button')"
            :disabled="hasRunningTasks || !allTasksSucceeded || !hasEnoughTokens"
            :loading="isRefreshing"
            @click="emit('openRefreshModal')"
          />
        </span>
        <CompanyDeleteButton :company="company" />
        <Button
          v-if="isDebugUser"
          variant="tertiary"
          size="sm"
          icon="fa-bug"
          icon-only
          :title="t('screen.company.debug.workflowTitle')"
          @click="emit('openTasksModal')"
        />
        <Tag
          v-if="folder && folder.is_owner"
          intent="neutral"
          icon="fa-lock"
          :label="t('common.folder.privacy.private')"
          size="xs"
          rounded
        />
        <Tag
          v-else-if="folder && !folder.is_owner && folder.share_role != null"
          intent="info"
          icon="fa-share-nodes"
          :label="t('common.folder.privacy.shared')"
          size="xs"
          rounded
        />
      </div>
    </div>

    <!-- Separator line -->
    <div class="border-primary-lighter-stroke border-b" />

    <!-- Row 2: Logo + Name + Tabs -->
    <div class="gap-xl pt-3xs flex items-center">
      <div class="gap-2xs flex min-w-0 shrink items-center">
        <!-- Loading ring around the logo when a task is running -->
        <SquareProgressRing :segments="progressSegments" :show-progress="showProgressRing">
          <div class="relative size-12 shrink-0 overflow-hidden rounded-md bg-white">
            <div :class="{ 'opacity-30': showProgressRing }">
              <Logo
                :website="company?.website"
                :name="company?.name"
                :alt="company?.name"
                :width="48"
                :height="48"
              />
            </div>
          </div>
        </SquareProgressRing>

        <h1 class="truncate text-2xl font-bold">{{ company?.name }}</h1>

        <!-- Activity messages when screening -->
        <Transition
          mode="out-in"
          enter-active-class="transition-all duration-300 ease-out"
          leave-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 translate-y-1.5"
          leave-to-class="opacity-0 -translate-y-1.5"
        >
          <span
            v-if="showProgressRing"
            :key="currentMessage"
            class="text-sage-600 shrink-0 text-sm whitespace-nowrap"
          >
            {{ currentMessage }}
          </span>
        </Transition>
      </div>

      <!-- Tabs — flex-1 gives the wrapper a stable width for overflow calc -->
      <CompanyHeaderTabs
        class="min-w-0 flex-1"
        :folder-id="folderId"
        :company-id="companyId"
        :job-offers-count="company?.jobs?.offers?.length"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import CompanyDeleteButton from '@/components/company/CompanyDeleteButton.vue'
import CompanyHeaderTabs from '@/components/company/CompanyHeaderTabs.vue'
import CompanyTranslation from '@/components/company/CompanyTranslation.vue'
import Export from '@/components/company/Export.vue'
import Logo from '@/components/ui/Logo.vue'
import SquareProgressRing from '@/components/ui/SquareProgressRing.vue'
import type { ProgressSegment } from '@/composables/useTaskProgress'
import type { Company } from '@/types/company'
import type { Folder } from '@/types/folder'
import { Button, Tag } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

interface Props {
  company: Company | undefined
  folder: Folder | undefined
  folderId: string
  companyId: string
  progressSegments: ProgressSegment[]
  showProgressRing: boolean
  currentMessage: string
  isOwner: boolean
  refreshButtonTooltip: string
  hasRunningTasks: boolean
  allTasksSucceeded: boolean
  hasEnoughTokens: boolean
  isRefreshing: boolean
  isDebugUser: boolean
}

const {
  company,
  folder,
  folderId,
  companyId,
  progressSegments,
  showProgressRing,
  currentMessage,
  isOwner,
  refreshButtonTooltip,
  hasRunningTasks,
  allTasksSucceeded,
  hasEnoughTokens,
  isRefreshing,
  isDebugUser,
} = defineProps<Props>()

interface Emits {
  openRefreshModal: []
  openTasksModal: []
}

const emit = defineEmits<Emits>()

const selectedLanguage = defineModel<string | undefined>('selectedLanguage')

const { t } = useI18n()
</script>
