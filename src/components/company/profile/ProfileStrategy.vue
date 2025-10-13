<template>
  <div class="bg-base-100 rounded-lg p-4">
    <div class="flex flex-col gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-secondary">
          <i class="fa fa-chart-sine"></i>
          <span>{{ $t('profile.sections.digital.title') }}</span>
        </h3>
      </div>

      <!-- Digital Insights -->
      <ChapseAlert v-if="company?.digital?.insights" variant="mage">
        {{ company.digital.insights }}
      </ChapseAlert>

      <!-- Digital Strategy - detailed breakdown -->
      <div v-if="company?.digital?.digitalStrategy">
        <h4>{{ $t('profile.sections.digital.strategy') }}</h4>
        <div class="text-sm flex flex-col gap-3">
          <div v-if="getSourcedValue(company.digital.digitalStrategy)?.overallStrategy">
            <h5 class="font-medium text-secondary mb-1">Overall Strategy</h5>
            <p class="text-secondary">
              {{ getSourcedValue(company.digital.digitalStrategy).overallStrategy }}
            </p>
          </div>

          <div v-if="getSourcedValue(company.digital.digitalStrategy)?.digitalTransformation">
            <h5 class="font-medium text-secondary mb-1">Digital Transformation</h5>
            <p class="text-secondary">
              {{ getSourcedValue(company.digital.digitalStrategy).digitalTransformation }}
            </p>
          </div>

          <div v-if="getSourcedValue(company.digital.digitalStrategy)?.eCommerceCapabilities">
            <h5 class="font-medium text-secondary mb-1">E-Commerce Capabilities</h5>
            <p class="text-secondary">
              {{ getSourcedValue(company.digital.digitalStrategy).eCommerceCapabilities }}
            </p>
          </div>

          <div v-if="getSourcedValue(company.digital.digitalStrategy)?.mobileStrategy">
            <h5 class="font-medium text-secondary mb-1">Mobile Strategy</h5>
            <p class="text-secondary">
              {{ getSourcedValue(company.digital.digitalStrategy).mobileStrategy }}
            </p>
          </div>

          <div v-if="getSourcedValue(company.digital.digitalStrategy)?.digitalMarketingApproach">
            <h5 class="font-medium text-secondary mb-1">Digital Marketing Approach</h5>
            <p class="text-secondary">
              {{ getSourcedValue(company.digital.digitalStrategy).digitalMarketingApproach }}
            </p>
          </div>

          <Source :sourced-value="company.digital.digitalStrategy" />
        </div>
      </div>

      <!-- Online Services -->
      <div v-if="company?.digital?.onlineServices">
        <h4>{{ $t('profile.sections.digital.onlineServices') }}</h4>
        <div class="text-sm flex flex-col gap-2">
          <div
            v-for="service in getSourcedValue(company.digital.onlineServices)"
            :key="service.name"
            class="bg-base-200 rounded p-3"
          >
            <h5 class="font-medium text-secondary mb-1">{{ service.name }}</h5>
            <p class="text-secondary">{{ service.description }}</p>
          </div>
          <Source :sourced-value="company.digital.onlineServices" />
        </div>
      </div>

      <!-- Social Media Accounts -->
      <div v-if="company?.digital?.socialMediaAccounts">
        <h4>Social Media Presence</h4>
        <div class="text-sm flex flex-col gap-2">
          <div
            v-for="account in getSourcedValue(company.digital.socialMediaAccounts)"
            :key="account.platform"
            class="flex items-center gap-3 bg-base-200 rounded p-3"
          >
            <div class="font-medium text-secondary">{{ account.platform }}</div>
            <div class="text-secondary">{{ account.handle }}</div>
            <div class="text-secondary text-xs">{{ account.description }}</div>
          </div>
          <Source :sourced-value="company.digital.socialMediaAccounts" />
        </div>
      </div>

      <!-- Loyalty Program - keeping for backward compatibility -->
      <div v-if="company?.digital?.loyaltyProgram">
        <h4>{{ $t('profile.sections.digital.loyaltyProgram') }}</h4>
        <div class="text-sm flex flex-col gap-2">
          <p class="text-secondary">
            {{ getSourcedValue(company.digital.loyaltyProgram) || $t('common.notFound') }}
          </p>
          <Source :sourced-value="company.digital.loyaltyProgram" />
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
