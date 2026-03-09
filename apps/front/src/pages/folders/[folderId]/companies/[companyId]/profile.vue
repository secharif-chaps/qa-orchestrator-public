<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State - Show if ANY profile tasks are loading -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State - Show if ALL profile tasks failed -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State - Show if all tasks completed but no data -->
    <NoData v-else-if="task?.status === 'succeeded' && !hasAnyProfileData">
      <p class="text-secondary text-lg font-medium">
        {{ $t('profile.sections.profile.noData', 'No profile data available for this company') }}
      </p>
    </NoData>

    <!-- Partial Data Layout - Show profile sections as they become available -->
    <div v-else class="grid grid-cols-12 gap-4">
      <div class="col-span-9">
        <ProfileHeader />
      </div>

      <div class="col-span-3">
        <div class="flex h-full flex-col gap-2">
          <ProfileGroup />
          <ProfileBusinessLine />
        </div>
      </div>

      <div class="col-span-12 grid grid-cols-3 gap-4">
        <ProfileEstablishment />
        <ProfileEmployees />
        <ProfileRevenue />
      </div>

      <!-- Tab Section -->
      <Card class="col-span-12">
        <div class="flex flex-col gap-4">
          <!-- Header Row: Title on left, Tabs on right -->
          <div class="flex items-center justify-between">
            <h3 class="text-secondary flex items-center gap-2 font-bold">
              <div
                class="bg-base-300 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg"
              >
                <i class="text-secondary text-md" :class="currentTabIcon"></i>
              </div>
              <span>{{ currentTabTitle }}</span>
            </h3>
            <Toggle v-model="currentTab" :options="tabOptions" />
          </div>

          <!-- Tab Content with transition -->
          <Transition name="tab-fade" mode="out-in">
            <ProfileTabProductsOverview v-if="currentTab === 'products-overview'" key="products" />
            <ProfileTabPartnersLabels v-else-if="currentTab === 'partners'" key="partners" />
            <ProfileTabDigitalStrategy v-else-if="currentTab === 'strategy'" key="strategy" />
          </Transition>
        </div>
      </Card>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.view
</route>

<script lang="ts" setup>
import { computed, ref, inject, type Ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { useI18n } from 'vue-i18n'
import { Toggle } from '@owlint/feathers-vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import NoData from '@/components/ui/NoData.vue'
import ProfileHeader from '@/components/company/profile/ProfileHeader.vue'
import ProfileGroup from '@/components/company/profile/ProfileGroup.vue'
import ProfileBusinessLine from '@/components/company/profile/ProfileBusinessLine.vue'
import ProfileEstablishment from '@/components/company/profile/ProfileEstablishment.vue'
import ProfileEmployees from '@/components/company/profile/ProfileEmployees.vue'
import ProfileRevenue from '@/components/company/profile/ProfileRevenue.vue'
import ProfileTabProductsOverview from '@/components/company/profile/ProfileTabProductsOverview.vue'
import ProfileTabPartnersLabels from '@/components/company/profile/ProfileTabPartnersLabels.vue'
import ProfileTabDigitalStrategy from '@/components/company/profile/ProfileTabDigitalStrategy.vue'
import Card from '@/components/ui/Card.vue'

const route = useRoute('/folders/[folderId]/companies/[companyId]/profile')
const router = useRouter()
const { t } = useI18n()

const companyId = computed(() => route.params.companyId)

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'profile'))

const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: company } = useQuery(
  // Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
  // No polling needed - cache is invalidated automatically when tasks update.
  () =>
    companyByIdQuery({
      id: companyId.value,
      language: selectedLanguage.value,
    }),
)

// Check if we have any profile data to show
const hasAnyProfileData = computed(() => {
  const comp = company.value
  if (!comp) return false

  // Check if any profile-related data exists (excluding CSR as it has its own section)
  // Profile contains nested fields like establishmentYear, employeeCount, revenue
  return !!(comp.profile || comp.digital)
})

// Tab configuration
const tabOptions = computed(() => [
  {
    value: 'products-overview',
    icon: 'fa fa-box-open',
    label: t('profile.tabs.productsOverview', 'Products Overview'),
  },
  {
    value: 'partners',
    icon: 'fa fa-handshake',
    label: t('profile.tabs.partnersLabels', 'Partners & Labels'),
  },
  {
    value: 'strategy',
    icon: 'fa fa-chart-line',
    label: t('profile.tabs.digitalStrategy', 'Digital Strategy'),
  },
])

// Current tab based on query param - synced with Toggle
const currentTab = computed({
  get: () => {
    const tab = route.query.tab as string | undefined
    if (tab === 'partners') return 'partners'
    if (tab === 'strategy') return 'strategy'
    return 'products-overview'
  },
  set: (value: string) => {
    const query = { ...route.query }
    if (value === 'products-overview') {
      delete query.tab
    } else {
      query.tab = value
    }
    router.push({ query })
  },
})

// Current tab title and icon for header
const currentTabTitle = computed(() => {
  const option = tabOptions.value.find((opt) => opt.value === currentTab.value)
  return option?.label ?? ''
})

const currentTabIcon = computed(() => {
  const option = tabOptions.value.find((opt) => opt.value === currentTab.value)
  return option?.icon ?? ''
})
</script>

<style scoped>
.tab-fade-enter-active,
.tab-fade-leave-active {
  transition: opacity 0.15s ease;
}

.tab-fade-enter-from,
.tab-fade-leave-to {
  opacity: 0;
}
</style>
