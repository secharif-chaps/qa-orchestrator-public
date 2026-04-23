<template>
  <div
    class="bg-neutral-white sticky top-0 z-10 -mx-4 flex items-center gap-4 px-4 py-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
  >
    <div class="flex min-w-0 shrink items-center gap-4">
      <!-- Loading ring around the logo when a task is running -->
      <SquareProgressRing :segments="progressSegments" :show-progress="showProgressRing">
        <div class="relative size-12 shrink-0 overflow-hidden rounded-md bg-white">
          <img
            v-if="getCompanyDomain(company?.website)"
            :src="getLogoUrl(company?.website)"
            :alt="`${company?.name} logo`"
            class="h-full w-full object-contain"
            :class="{ 'opacity-30': showProgressRing }"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <Badge
            v-show="showFallbackIcon || !getCompanyDomain(company?.website)"
            variant="secondary"
            color="sage"
            icon="fa-building"
            size="lg"
            class="h-full w-full rounded-none"
          />
        </div>
      </SquareProgressRing>

      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold">{{ company?.name }}</h1>
        <Transition
          mode="out-in"
          enter-active-class="transition-all duration-300 ease-out"
          leave-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 translate-y-1.5"
          leave-to-class="opacity-0 -translate-y-1.5"
        >
          <div v-if="showProgressRing" key="activity" class="relative h-5 overflow-y-clip">
            <Transition
              enter-active-class="transition-all duration-300 ease-out"
              leave-active-class="transition-all duration-300 ease-out"
              enter-from-class="opacity-0 translate-y-full"
              leave-to-class="opacity-0 -translate-y-full"
            >
              <span
                :key="currentMessage"
                class="text-sage-600 absolute top-0 left-0 text-sm whitespace-nowrap"
              >
                {{ currentMessage }}
              </span>
            </Transition>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Tabs — flex-1 gives the wrapper a stable width for overflow calc -->
    <CompanyHeaderTabs
      class="min-w-0 flex-1"
      :folder-id="folderId"
      :company-id="companyId"
      :job-offers-count="company?.jobs?.offers?.length"
    />

    <!-- Action Buttons -->
    <div class="flex shrink-0 items-center gap-2">
      <CompanyTranslation v-model="selectedLanguage" :company-id="companyId" />
      <Export />
      <span v-if="isOwner" :title="refreshButtonTooltip">
        <Button
          variant="tertiary"
          icon="fa fa-refresh"
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
    </div>
  </div>
</template>

<script lang="ts" setup>
import CompanyDeleteButton from '@/components/company/CompanyDeleteButton.vue'
import CompanyHeaderTabs from '@/components/company/CompanyHeaderTabs.vue'
import CompanyTranslation from '@/components/company/CompanyTranslation.vue'
import Export from '@/components/company/Export.vue'
import SquareProgressRing from '@/components/ui/SquareProgressRing.vue'
import type { ProgressSegment } from '@/composables/useTaskProgress'
import type { Company } from '@/types/company'
import { Badge, Button } from '@owlint/feathers-vue'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  company: Company | undefined
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

const emit = defineEmits<{
  openRefreshModal: []
  openTasksModal: []
}>()

const selectedLanguage = defineModel<string | undefined>('selectedLanguage')

const { t } = useI18n()

const showFallbackIcon = ref(false)

// Reset fallback icon when company changes
watch(
  () => company,
  () => {
    showFallbackIcon.value = false
  },
)

const getLogoUrl = (website?: string) => {
  const domain = getCompanyDomain(website)
  if (!domain) return ''
  return `https://img.logo.dev/${domain}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
}

const getCompanyDomain = (website?: string) => {
  if (!website) return null
  try {
    let domain = website.replace(/^https?:\/\//, '').replace(/^www\./, '')
    domain = domain.split('/')[0]
    return domain
  } catch {
    return null
  }
}
</script>
