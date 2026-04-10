<template>
  <div class="relative flex-1 rounded-sm bg-white p-4">
    <div class="flex flex-col gap-2">
      <h4>{{ t('screen.profile.sections.businessLine.title') }}</h4>
      <!-- Business line - individual property loading -->
      <div class="flex flex-col gap-2 text-sm">
        <p class="text-neutral-black-font">
          {{
            getSourcedValue(company?.profile?.businessLine) ??
            t('screen.profile.sections.businessLine.notFound')
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
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import { companyByIdQuery } from '@/queries/companies'
import { useQuery } from '@pinia/colada'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
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
