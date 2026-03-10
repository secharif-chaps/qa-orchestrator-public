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
    <NoData v-else-if="!hasCsrData">
      <p class="text-secondary text-lg font-medium">
        {{ $t('profile.sections.csr.noData') }}
      </p>
    </NoData>

    <!-- Main Content -->
    <div v-else class="flex flex-col gap-6">
      <!-- AI-Generated CSR Insights -->
      <ChapseAlert v-if="company?.csr?.insights" variant="mage">
        {{ company.csr.insights }}
      </ChapseAlert>

      <!-- CSR Responsibility Statement -->
      <div v-if="responsibilityValue" class="bg-base-100 rounded-lg p-6">
        <h4 class="text-secondary mb-3 font-semibold">
          {{ $t('profile.sections.csr.responsibility') }}
        </h4>
        <p class="text-secondary text-sm leading-relaxed">
          {{ responsibilityValue }}
          <Source
            v-if="company?.csr?.responsibility && typeof company.csr.responsibility !== 'string'"
            :source="getSourcedSource(company.csr.responsibility)"
          />
        </p>
      </div>

      <!-- CSR Sections Grid -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <!-- Responsibility Initiatives -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="text-secondary mb-4 flex items-center gap-2 font-semibold">
            <i class="fa fa-handshake"></i>
            {{ $t('profile.sections.csr.responsibility_initiatives') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="text-secondary flex items-start gap-2"
              v-for="initiative in company?.csr?.responsibility_initiatives || []"
              :key="initiative.value"
            >
              <i class="fa-solid fa-circle text-secondary mt-1.5 text-[6px]"></i>
              <div class="min-w-0 flex-1">
                <span class="text-sm">{{ getSourcedValue(initiative) }}</span>
                <Source :source="initiative.source" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.responsibility_initiatives?.length"
              class="text-secondary text-sm italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Charity Actions -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="text-secondary mb-4 flex items-center gap-2 font-semibold">
            <i class="fa fa-heart"></i>
            {{ $t('profile.sections.csr.charity') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="text-secondary flex items-start gap-2"
              v-for="action in company?.csr?.charity_actions || []"
              :key="action.value"
            >
              <i class="fa-solid fa-circle text-secondary mt-1.5 text-[6px]"></i>
              <div class="min-w-0 flex-1">
                <span class="text-sm">{{ getSourcedValue(action) }}</span>
                <Source :source="action.source" />
              </div>
            </li>
            <li v-if="!company?.csr?.charity_actions?.length" class="text-secondary text-sm italic">
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Sustainability Programs -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="text-secondary mb-4 flex items-center gap-2 font-semibold">
            <i class="fa fa-leaf"></i>
            {{ $t('profile.sections.csr.sustainability') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="text-secondary flex items-start gap-2"
              v-for="program in company?.csr?.sustainability_programs || []"
              :key="program.value"
            >
              <i class="fa-solid fa-circle text-secondary mt-1.5 text-[6px]"></i>
              <div class="min-w-0 flex-1">
                <span class="text-sm">{{ getSourcedValue(program) }}</span>
                <Source :source="program.source" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.sustainability_programs?.length"
              class="text-secondary text-sm italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Community Involvement -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="text-secondary mb-4 flex items-center gap-2 font-semibold">
            <i class="fa fa-users"></i>
            {{ $t('profile.sections.csr.community') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="text-secondary flex items-start gap-2"
              v-for="involvement in company?.csr?.community_involvement || []"
              :key="involvement.value"
            >
              <i class="fa-solid fa-circle text-secondary mt-1.5 text-[6px]"></i>
              <div class="min-w-0 flex-1">
                <span class="text-sm">{{ getSourcedValue(involvement) }}</span>
                <Source :source="involvement.source" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.community_involvement?.length"
              class="text-secondary text-sm italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Diversity & Inclusion -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="text-secondary mb-4 flex items-center gap-2 font-semibold">
            <i class="fa fa-people-group"></i>
            {{ $t('profile.sections.csr.diversity') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="text-secondary flex items-start gap-2"
              v-for="initiative in company?.csr?.diversity_inclusion || []"
              :key="initiative.value"
            >
              <i class="fa-solid fa-circle text-secondary mt-1.5 text-[6px]"></i>
              <div class="min-w-0 flex-1">
                <span class="text-sm">{{ getSourcedValue(initiative) }}</span>
                <Source :source="initiative.source" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.diversity_inclusion?.length"
              class="text-secondary text-sm italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Ethical Practices -->
        <div class="bg-base-100 rounded-lg p-6">
          <h4 class="text-secondary mb-4 flex items-center gap-2 font-semibold">
            <i class="fa fa-scale-balanced"></i>
            {{ $t('profile.sections.csr.ethics') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="text-secondary flex items-start gap-2"
              v-for="practice in company?.csr?.ethical_practices || []"
              :key="practice.value"
            >
              <i class="fa-solid fa-circle text-secondary mt-1.5 text-[6px]"></i>
              <div class="min-w-0 flex-1">
                <span class="text-sm">{{ getSourcedValue(practice) }}</span>
                <Source :source="practice.source" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.ethical_practices?.length"
              class="text-secondary text-sm italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>

        <!-- Awards & Certifications -->
        <div class="bg-base-100 rounded-lg p-6 md:col-span-2">
          <h4 class="text-secondary mb-4 flex items-center gap-2 font-semibold">
            <i class="fa fa-award"></i>
            {{ $t('profile.sections.csr.awards') }}
          </h4>
          <ul class="space-y-2">
            <li
              class="text-secondary flex items-start gap-2"
              v-for="award in company?.csr?.awards_certifications || []"
              :key="award.value"
            >
              <i class="fa-solid fa-circle text-secondary mt-1.5 text-[6px]"></i>
              <div class="min-w-0 flex-1">
                <span class="text-sm">{{ getSourcedValue(award) }}</span>
                <Source :source="award.source" />
              </div>
            </li>
            <li
              v-if="!company?.csr?.awards_certifications?.length"
              class="text-secondary text-sm italic"
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
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import Source from '@/components/company/Source.vue'
import { getSourcedValue, getSourcedSource } from '@/components/helpers/sourcedValues'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import NoData from '@/components/ui/NoData.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import { computed, inject, ref } from 'vue'
import type { Ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/csr')

const companyId = computed(() => route.params.companyId)

// Inject selected language from parent [companyId].vue
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const task = computed(() => tasks.value?.find((t) => t.type === 'csr'))

// Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
// No polling needed - cache is invalidated automatically when tasks update.
const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
  language: selectedLanguage.value,
}))

// Extract responsibility value (handles both string and SourcedValue)
const responsibilityValue = computed(() => {
  const resp = company.value?.csr?.responsibility
  if (!resp) return null
  if (typeof resp === 'string') return resp
  return getSourcedValue(resp)
})

const hasCsrData = computed(() => {
  const csr = company.value?.csr
  if (!csr) return false

  // Check if there's any meaningful content
  return !!(
    csr.insights ||
    csr.responsibility ||
    (csr.awards_certifications && csr.awards_certifications.length > 0) ||
    (csr.charity_actions && csr.charity_actions.length > 0) ||
    (csr.community_involvement && csr.community_involvement.length > 0) ||
    (csr.diversity_inclusion && csr.diversity_inclusion.length > 0) ||
    (csr.ethical_practices && csr.ethical_practices.length > 0) ||
    (csr.responsibility_initiatives && csr.responsibility_initiatives.length > 0) ||
    (csr.sustainability_programs && csr.sustainability_programs.length > 0)
  )
})
</script>
