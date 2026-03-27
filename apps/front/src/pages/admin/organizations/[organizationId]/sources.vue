<template>
  <div class="flex flex-col gap-6">
    <Card>
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="text-xl font-semibold">
            {{ $t('screen.dataSources.title', 'Data Sources') }}
          </h2>
          <p class="text-secondary mt-1">
            {{
              $t(
                'screen.dataSources.description',
                'Configure external data providers for company screening',
              )
            }}
          </p>
        </div>
      </div>

      <!-- Data Source Cards -->
      <div class="flex flex-col gap-4">
        <DataSourceCard
          v-for="source in availableSources"
          :key="source.source"
          :source="source"
          :organization-id="organizationIdValue"
        />
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import DataSourceCard from '@/components/admin/DataSourceCard.vue'
import type { DataSourceInfo } from '@/types/data-source'

const { t } = useI18n()

const organizationId = inject<ReturnType<typeof computed<string>>>('organizationId')
const organizationIdValue = computed(() => organizationId?.value || '')

const availableSources = computed<DataSourceInfo[]>(() => [
  {
    source: 'pappers',
    name: t('screen.dataSources.pappers.name', 'Pappers'),
    description: t(
      'screen.dataSources.pappers.description',
      'French company data provider (legal info, financials, officers)',
    ),
    logo: '/src/assets/logos/pappers.svg',
  },
  {
    source: 'worldcheck',
    name: t('screen.dataSources.worldcheck.name', 'WorldCheck'),
    description: t(
      'screen.dataSources.worldcheck.description',
      'LSEG WorldCheck screening for sanctions, PEP, and adverse media',
    ),
    logo: '/src/assets/logos/worldcheck.svg',
    isDualCredential: true,
  },
])
</script>
