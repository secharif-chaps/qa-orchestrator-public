<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State -->
    <SectionErrorState
      v-else-if="company && task?.status === 'error'"
      :error-message="task.error"
      :task="task"
    />

    <!-- No Data State -->
    <div v-else-if="!hasCsrData">no data state</div>

    <!-- Main Content -->
    <div v-else class="flex flex-col gap-6">
      <!-- AI-Generated CSR Insights -->
      <ChapseAlert v-if="company?.csr?.insights" variant="mage">
        {{ company.csr.insights }}
      </ChapseAlert>

      <!-- CSR Responsibility Statement -->
      <div v-if="company?.csr?.responsibility" class="bg-base-100 rounded-lg p-6">
        <h4 class="font-semibold text-primary-light-content mb-3">
          {{ $t('profile.sections.csr.responsibility') }}
        </h4>
        <p class="text-sm text-primary-light-content leading-relaxed">
          {{ company.csr.responsibility }}
        </p>
      </div>

      <!-- CSR Sections Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Responsibility Initiatives -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="font-semibold text-primary-light-content mb-4 flex items-center gap-2">
            <i class="fa fa-handshake"></i>
            {{ $t('profile.sections.csr.responsibility_initiatives') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="flex items-start gap-2 text-primary-light-content"
              v-for="initiative in company?.csr?.responsibility_initiatives || []"
              :key="initiative.value"
            >
              <i class="fa-solid fa-circle text-primary-light-content text-[6px] mt-1.5"></i>
              <div class="flex-1 min-w-0">
                <span class="text-sm">{{ getSourcedValue(initiative) }}</span>
                <Source :source="initiative.sources[0]" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.responsibility_initiatives?.length"
              class="text-sm text-primary-light-content italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Charity Actions -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="font-semibold text-primary-light-content mb-4 flex items-center gap-2">
            <i class="fa fa-heart"></i>
            {{ $t('profile.sections.csr.charity') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="flex items-start gap-2 text-primary-light-content"
              v-for="action in company?.csr?.charity_actions || []"
              :key="action.value"
            >
              <i class="fa-solid fa-circle text-primary-light-content text-[6px] mt-1.5"></i>
              <div class="flex-1 min-w-0">
                <span class="text-sm">{{ getSourcedValue(action) }}</span>
                <Source :source="action.sources[0]" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.charity_actions?.length"
              class="text-sm text-primary-light-content italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Sustainability Programs -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="font-semibold text-primary-light-content mb-4 flex items-center gap-2">
            <i class="fa fa-leaf"></i>
            {{ $t('profile.sections.csr.sustainability') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="flex items-start gap-2 text-primary-light-content"
              v-for="program in company?.csr?.sustainability_programs || []"
              :key="program.value"
            >
              <i class="fa-solid fa-circle text-primary-light-content text-[6px] mt-1.5"></i>
              <div class="flex-1 min-w-0">
                <span class="text-sm">{{ getSourcedValue(program) }}</span>
                <Source :source="program.sources[0]" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.sustainability_programs?.length"
              class="text-sm text-primary-light-content italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Community Involvement -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="font-semibold text-primary-light-content mb-4 flex items-center gap-2">
            <i class="fa fa-users"></i>
            {{ $t('profile.sections.csr.community') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="flex items-start gap-2 text-primary-light-content"
              v-for="involvement in company?.csr?.community_involvement || []"
              :key="involvement.value"
            >
              <i class="fa-solid fa-circle text-primary-light-content text-[6px] mt-1.5"></i>
              <div class="flex-1 min-w-0">
                <span class="text-sm">{{ getSourcedValue(involvement) }}</span>
                <Source :source="involvement.sources[0]" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.community_involvement?.length"
              class="text-sm text-primary-light-content italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Diversity & Inclusion -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="font-semibold text-primary-light-content mb-4 flex items-center gap-2">
            <i class="fa fa-people-group"></i>
            {{ $t('profile.sections.csr.diversity') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="flex items-start gap-2 text-primary-light-content"
              v-for="initiative in company?.csr?.diversity_inclusion || []"
              :key="initiative.value"
            >
              <i class="fa-solid fa-circle text-primary-light-content text-[6px] mt-1.5"></i>
              <div class="flex-1 min-w-0">
                <span class="text-sm">{{ getSourcedValue(initiative) }}</span>
                <Source :source="initiative.sources[0]" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.diversity_inclusion?.length"
              class="text-sm text-primary-light-content italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Ethical Practices -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="font-semibold text-primary-light-content mb-4 flex items-center gap-2">
            <i class="fa fa-scale-balanced"></i>
            {{ $t('profile.sections.csr.ethics') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="flex items-start gap-2 text-primary-light-content"
              v-for="practice in company?.csr?.ethical_practices || []"
              :key="practice.value"
            >
              <i class="fa-solid fa-circle text-primary-light-content text-[6px] mt-1.5"></i>
              <div class="flex-1 min-w-0">
                <span class="text-sm">{{ getSourcedValue(practice) }}</span>
                <Source :source="practice.sources[0]" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.ethical_practices?.length"
              class="text-sm text-primary-light-content italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Awards & Certifications -->
        <div class="bg-base-100 rounded-lg p-6 md:col-span-2">
          <h4 class="font-semibold text-primary-light-content mb-4 flex items-center gap-2">
            <i class="fa fa-award"></i>
            {{ $t('profile.sections.csr.awards') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="flex items-start gap-2 text-primary-light-content"
              v-for="award in company?.csr?.awards_certifications || []"
              :key="award.value"
            >
              <i class="fa-solid fa-circle text-primary-light-content text-[6px] mt-1.5"></i>
              <div class="flex-1 min-w-0">
                <span class="text-sm">{{ getSourcedValue(award) }}</span>
                <Source :source="award.sources[0]" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.awards_certifications?.length"
              class="text-sm text-primary-light-content italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.view
</route>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { useTaskState, hasDataForSection } from '@/composables/useTaskState'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '@/components/company/Source.vue'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: tasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const task = computed(() => tasks.value?.find((t) => t.type === 'csr'))

const { data: company } = useQuery(
  companyByIdQuery,
  () => ({
    id: companyId.value,
  }),
  {
    // Poll every 5 seconds when any task is running
    refetchInterval: () => {
      const hasRunningTasks = company.value?.tasks?.some(
        (t) => t.status === 'running' || t.status === 'pending',
      )
      return hasRunningTasks ? 5000 : false
    },
  },
)

const hasCsrData = computed(() => {
  return hasDataForSection(company.value, 'csr')
})
</script>
