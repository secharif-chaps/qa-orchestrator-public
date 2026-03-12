<template>
  <div class="bg-base-100 relative flex-1 rounded-lg p-4">
    <div class="flex flex-col gap-2">
      <h4>{{ t('profile.sections.businessLine.title') }}</h4>
      <!-- Business line - individual property loading -->
      <div class="flex flex-col gap-2 text-sm">
        <p class="text-secondary">
          {{
            getSourcedValue(company?.profile?.businessLine) ??
            t('profile.sections.businessLine.notFound')
          }}
        </p>
      </div>
      <div class="absolute top-2 right-2">
        <Source :sourced-value="company?.profile?.businessLine" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '../Source.vue'

const { t } = useI18n()
const route = useRoute()

const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
  }),
)
</script>
