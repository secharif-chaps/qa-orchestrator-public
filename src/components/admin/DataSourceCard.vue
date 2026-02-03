<template>
  <div class="border border-base-300 rounded-lg p-4">
    <div class="flex items-start justify-between gap-4">
      <!-- Source Info -->
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-base-200 rounded-lg flex items-center justify-center">
          <img
            v-if="source.logo"
            :src="source.logo"
            :alt="source.name"
            class="w-8 h-8 object-contain"
          />
          <Icon v-else icon="fa-database" class="text-xl text-secondary" />
        </div>
        <div>
          <h3 class="font-semibold">{{ source.name }}</h3>
          <p class="text-sm text-secondary">{{ source.description }}</p>
        </div>
      </div>

      <!-- Status Badge -->
      <span
        :class="[
          'px-2 py-1 text-xs font-medium rounded-full',
          config?.enabled
            ? 'bg-success/10 text-success'
            : 'bg-base-200 text-secondary'
        ]"
      >
        {{ config?.enabled ? $t('dataSources.enabled', 'Enabled') : $t('dataSources.disabled', 'Disabled') }}
      </span>
    </div>

    <!-- API Key Section -->
    <div class="mt-4 pt-4 border-t border-base-300">
      <div class="flex flex-col gap-3">
        <label class="text-sm font-medium text-secondary">
          {{ $t('dataSources.apiKey.label', 'API Key') }}
        </label>

        <!-- Display Mode -->
        <div v-if="!isEditing" class="flex items-center justify-between">
          <code class="text-sm bg-base-200 px-3 py-2 rounded flex-1">
            {{ config?.api_key_masked || $t('dataSources.apiKey.notConfigured', 'Not configured') }}
          </code>
          <Button
            variant="secondary"
            size="sm"
            :label="$t('dataSources.edit', 'Edit')"
            icon="fas fa-pencil"
            class="ml-3"
            @click="startEditing"
          />
        </div>

        <!-- Edit Mode -->
        <div v-else class="flex flex-col gap-3">
          <Input
            id="input-api-key"
            v-model="newApiKey"
            type="password"
            :placeholder="$t('dataSources.apiKey.placeholder', 'Enter API key...')"
          />
          <div class="flex items-center gap-2">
            <Button
              variant="primary"
              size="sm"
              :label="$t('dataSources.save', 'Save')"
              :loading="updateMutation.isLoading.value"
              :disabled="!newApiKey.trim()"
              @click="saveApiKey"
            />
            <Button
              variant="tertiary"
              size="sm"
              :label="$t('dataSources.cancel', 'Cancel')"
              :disabled="updateMutation.isLoading.value"
              @click="cancelEditing"
            />
          </div>
        </div>

        <!-- Timestamps -->
        <div v-if="config?.enabled_at || config?.updated_at" class="text-xs text-secondary">
          <span v-if="config?.enabled_at">
            {{ $t('dataSources.enabledAt', 'Enabled') }}: {{ formatDateTime(config.enabled_at) }}
          </span> 
          <span v-if="config?.updated_at" class="ml-3">
            {{ $t('dataSources.lastUpdated', 'Updated') }}: {{ formatDateTime(config.updated_at) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useQuery } from '@pinia/colada'
import { Button, Input, Icon } from '@owlint/feathers-vue'
import { dataSourceConfigQuery } from '@/queries/data-sources'
import { useUpdateDataSourceConfig } from '@/mutations/data-sources'
import { formatDateTime } from '@/utils/time'
import type { DataSourceInfo } from '@/types/data-source'

const props = defineProps<{
  source: DataSourceInfo
  organizationId: string
}>()

const isEditing = ref(false)
const newApiKey = ref('')

// Query for source config
const { data: config, refetch } = useQuery({
  ...dataSourceConfigQuery({
    organizationId: props.organizationId,
    source: props.source.source,
  }),
  enabled: () => !!props.organizationId,
})

// Mutation for updating config
const updateMutation = useUpdateDataSourceConfig()

function startEditing() {
  isEditing.value = true
  newApiKey.value = ''
}

function cancelEditing() {
  isEditing.value = false
  newApiKey.value = ''
}

async function saveApiKey() {
  if (!newApiKey.value.trim()) return

  updateMutation.organizationId.value = props.organizationId
  updateMutation.source.value = props.source.source
  updateMutation.apiKey.value = newApiKey.value

  updateMutation.updateConfig()
  
  isEditing.value = false
  newApiKey.value = ''
  refetch()
}
</script>