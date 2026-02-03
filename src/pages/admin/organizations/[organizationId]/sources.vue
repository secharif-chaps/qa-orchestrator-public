<template>
  <div class="flex flex-col gap-6">
    <Card>
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-xl font-semibold">
            {{ $t('dataSources.title', 'Data Sources') }}
          </h2>
          <p class="text-secondary mt-1">
            {{ $t('dataSources.description', 'Configure external data providers for company screening') }}
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
    name: t('dataSources.pappers.name', 'Pappers'),
    description: t('dataSources.pappers.description', 'French company data provider (legal info, financials, officers)'),
    logo: '/src/assets/logos/pappers.svg',
  },
])
</script>