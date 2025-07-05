<template>
  <Card class="h-full">
    <div class="flex flex-col gap-2">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-hand-holding-heart"></i>
          <span>{{ title }}</span>
        </h3>
      </div>

      <!-- CSR Initiatives - individual property loading -->
      <h4>{{ $t('profile.sections.csr.responsibility') }}</h4>
      <div class="p-4">
        <ul class="list-disc">
          <li
            class="space-x-2 text-secondary"
            v-for="initiative in company?.csr?.responsibility_initiatives || []"
          >
            <span class="text-sm">
              {{ getSourcedValue(initiative) }}
            </span>
            <Source :sourced-value="initiative" />
          </li>
          <li
            v-if="company?.csr?.responsibility_initiatives?.length === 0"
            class="text-sm text-secondary italic"
          >
            {{ $t('common.notFound') }}
          </li>
        </ul>
      </div>

      <!-- Charity Actions - individual property loading -->
      <h4>{{ $t('profile.sections.csr.charity') }}</h4>
      <div class="p-4">
        <span class="text-sm text-secondary" v-if="company?.csr?.charity_actions">
          {{
            (company?.csr?.charity_actions.map((action: { value: string }) => action.value) || []).join(', ') ||
            $t('common.notFound')
          }}
        </span>
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { company, getSourcedValue } = useCompanyData()

defineProps<{
  title: string
}>()
</script>
