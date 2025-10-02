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
      <ChapseAlert v-if="company?.csr?.insights" variant="mage">
        {{ company.csr.insights }}
      </ChapseAlert>

      <!-- CSR Responsibility Statement -->
      <div v-if="company?.csr?.responsibility">
        <h4 class="font-semibold text-primary mb-2">
          {{ $t('profile.sections.csr.responsibility') }}
        </h4>
        <p class="text-sm text-secondary">{{ company.csr.responsibility }}</p>
      </div>

      <!-- Responsibility Initiatives -->
      <div>
        <h4 class="font-semibold text-primary mb-2">
          {{ $t('profile.sections.csr.responsibility_initiatives') }}
        </h4>
        <div class="">
          <ul class="space-y-1">
            <li
              class="flex items-center space-x-1 text-secondary"
              v-for="initiative in company?.csr?.responsibility_initiatives || []"
              :key="initiative.value"
            >
              <i class="fa-solid fa-dot text-secondary"></i>
              <span class="text-sm">
                {{ getSourcedValue(initiative) }}
              </span>
              <Source :source="initiative.sources[0]" />
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
        <div class="">
          <ul class="space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="action in company?.csr?.charity_actions || []"
              :key="action.value"
            >
              <i class="fa-solid fa-dot text-secondary"></i>
              <span class="text-sm">
                {{ getSourcedValue(action) }}
              </span>
              <Source :source="action.sources[0]" />
            </li>
            <li v-if="!company?.csr?.charity_actions?.length" class="text-sm text-secondary italic">
              {{ $t('common.notFound') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Sustainability Programs -->
      <div>
        <h4 class="font-semibold text-primary mb-2">
          {{ $t('profile.sections.csr.sustainability') }}
        </h4>
        <div class="">
          <ul class="space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="program in company?.csr?.sustainability_programs || []"
              :key="program.value"
            >
              <i class="fa-solid fa-dot text-secondary"></i>
              <span class="text-sm">
                {{ getSourcedValue(program) }}
              </span>
              <Source :source="program.sources[0]" />
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
        <div class="">
          <ul class="space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="involvement in company?.csr?.community_involvement || []"
              :key="involvement.value"
            >
              <i class="fa-solid fa-dot text-secondary"></i>
              <span class="text-sm">
                {{ getSourcedValue(involvement) }}
              </span>
              <Source :source="involvement.sources[0]" />
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
        <div class="">
          <ul class="space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="initiative in company?.csr?.diversity_inclusion || []"
              :key="initiative.value"
            >
              <i class="fa-solid fa-dot text-secondary"></i>
              <span class="text-sm">
                {{ getSourcedValue(initiative) }}
              </span>
              <Source :source="initiative.sources[0]" />
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
        <div class="">
          <ul class="space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="practice in company?.csr?.ethical_practices || []"
              :key="practice.value"
            >
              <i class="fa-solid fa-dot text-secondary"></i>
              <span class="text-sm">
                {{ getSourcedValue(practice) }}
              </span>
              <Source :source="practice.sources[0]" />
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
        <div class="">
          <ul class="space-y-1">
            <li
              class="flex items-start space-x-2 text-secondary"
              v-for="award in company?.csr?.awards_certifications || []"
              :key="award.value"
            >
              <i class="fa-solid fa-dot text-secondary"></i>
              <span class="text-sm">
                {{ getSourcedValue(award) }}
              </span>
              <Source :source="award.sources[0]" />
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
import ChapseAlert from '@/components/ui/ChapseAlert.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))
</script>
