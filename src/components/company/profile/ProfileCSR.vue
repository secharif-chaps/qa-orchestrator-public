<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="flex flex-col gap-6">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-hand-holding-heart"></i>
          <span>{{ $t('profile.sections.csr.title') }}</span>
        </h3>
      </div>

      <!-- AI-Generated CSR Insights -->
      <div v-if="company?.csr?.insights">
        <Alert
          variant="info"
          icon="fa fa-robot"
          decoration-icon="fa fa-sparkles"
          :title="$t('profile.sections.csr.insights.title')"
          :message="company.csr.insights"
          :dismissible="false"
        >
          <template #status>
            <div class="flex items-center space-x-1 text-xs text-info">
              <i class="fa fa-brain"></i>
              <span>AI Generated</span>
            </div>
          </template>
        </Alert>
      </div>

      <!-- CSR Responsibility Statement -->
      <div v-if="company?.csr?.responsibility">
        <h4 class="font-semibold text-primary mb-2">{{ $t('profile.sections.csr.responsibility') }}</h4>
        <p class="text-sm text-secondary">{{ company.csr.responsibility }}</p>
      </div>

      <!-- Responsibility Initiatives -->
      <div>
        <h4 class="font-semibold text-primary mb-2">{{ $t('profile.sections.csr.responsibility_initiatives') }}</h4>
        <div class="ml-4">
          <ul class="list-disc space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="initiative in company?.csr?.responsibility_initiatives || []"
              :key="initiative.value"
            >
              <span class="text-sm flex-1">
                {{ getSourcedValue(initiative) }}
              </span>
              <Source :sourced-value="initiative" />
            </li>
            <li
              v-if="!company?.csr?.responsibility_initiatives?.length"
              class="text-sm text-secondary italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Charity Actions -->
      <div>
        <h4 class="font-semibold text-primary mb-2">{{ $t('profile.sections.csr.charity') }}</h4>
        <div class="ml-4">
          <ul class="list-disc space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="action in company?.csr?.charity_actions || []"
              :key="action.value"
            >
              <span class="text-sm flex-1">
                {{ getSourcedValue(action) }}
              </span>
              <Source :sourced-value="action" />
            </li>
            <li
              v-if="!company?.csr?.charity_actions?.length"
              class="text-sm text-secondary italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Sustainability Programs -->
      <div>
        <h4 class="font-semibold text-primary mb-2">{{ $t('profile.sections.csr.sustainability') }}</h4>
        <div class="ml-4">
          <ul class="list-disc space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="program in company?.csr?.sustainability_programs || []"
              :key="program.value"
            >
              <span class="text-sm flex-1">
                {{ getSourcedValue(program) }}
              </span>
              <Source :sourced-value="program" />
            </li>
            <li
              v-if="!company?.csr?.sustainability_programs?.length"
              class="text-sm text-secondary italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Community Involvement -->
      <div>
        <h4 class="font-semibold text-primary mb-2">{{ $t('profile.sections.csr.community') }}</h4>
        <div class="ml-4">
          <ul class="list-disc space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="involvement in company?.csr?.community_involvement || []"
              :key="involvement.value"
            >
              <span class="text-sm flex-1">
                {{ getSourcedValue(involvement) }}
              </span>
              <Source :sourced-value="involvement" />
            </li>
            <li
              v-if="!company?.csr?.community_involvement?.length"
              class="text-sm text-secondary italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Diversity & Inclusion -->
      <div>
        <h4 class="font-semibold text-primary mb-2">{{ $t('profile.sections.csr.diversity') }}</h4>
        <div class="ml-4">
          <ul class="list-disc space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="initiative in company?.csr?.diversity_inclusion || []"
              :key="initiative.value"
            >
              <span class="text-sm flex-1">
                {{ getSourcedValue(initiative) }}
              </span>
              <Source :sourced-value="initiative" />
            </li>
            <li
              v-if="!company?.csr?.diversity_inclusion?.length"
              class="text-sm text-secondary italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Ethical Practices -->
      <div>
        <h4 class="font-semibold text-primary mb-2">{{ $t('profile.sections.csr.ethics') }}</h4>
        <div class="ml-4">
          <ul class="list-disc space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="practice in company?.csr?.ethical_practices || []"
              :key="practice.value"
            >
              <span class="text-sm flex-1">
                {{ getSourcedValue(practice) }}
              </span>
              <Source :sourced-value="practice" />
            </li>
            <li
              v-if="!company?.csr?.ethical_practices?.length"
              class="text-sm text-secondary italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Awards & Certifications -->
      <div>
        <h4 class="font-semibold text-primary mb-2">{{ $t('profile.sections.csr.awards') }}</h4>
        <div class="ml-4">
          <ul class="list-disc space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="award in company?.csr?.awards_certifications || []"
              :key="award.value"
            >
              <span class="text-sm flex-1">
                {{ getSourcedValue(award) }}
              </span>
              <Source :sourced-value="award" />
            </li>
            <li
              v-if="!company?.csr?.awards_certifications?.length"
              class="text-sm text-secondary italic"
            >
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '../Source.vue'
import Alert from '@/components/ui/Alert.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))
</script>
