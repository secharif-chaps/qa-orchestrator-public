<template>
  <div class="border-primary-lighter-stroke rounded-sm border p-4">
    <div class="flex items-start justify-between gap-4">
      <!-- Source Info -->
      <div class="flex items-center gap-4">
        <div class="bg-primary-lightest flex h-12 w-12 items-center justify-center rounded-sm">
          <img
            v-if="source.logo"
            :src="source.logo"
            :alt="source.name"
            class="h-8 w-8 object-contain"
          />
          <Icon v-else icon="fa-database" class="text-neutral-black-font text-xl" />
        </div>
        <div>
          <h3 class="font-semibold">{{ source.name }}</h3>
          <p class="text-neutral-black-font text-sm">{{ source.description }}</p>
        </div>
      </div>

      <!-- Status Badge -->
      <span
        :class="[
          'rounded-full px-2 py-1 text-xs font-medium',
          config?.enabled
            ? 'bg-success/10 text-success'
            : 'bg-primary-lightest text-neutral-black-font',
        ]"
      >
        {{ config?.enabled ? $t('screen.dataSources.enabled') : $t('screen.dataSources.disabled') }}
      </span>
    </div>

    <!-- Credentials Section -->
    <div class="border-primary-lighter-stroke mt-4 border-t pt-4">
      <div class="flex flex-col gap-3">
        <!-- Display Mode -->
        <div v-if="!isEditing" class="flex flex-col gap-3">
          <!-- API Key row -->
          <div class="flex flex-col gap-1">
            <label class="text-neutral-black-font text-sm font-medium">
              {{ $t('screen.dataSources.apiKey.label') }}
            </label>
            <code class="bg-primary-lightest rounded px-3 py-2 text-sm">
              {{ config?.api_key_masked || $t('screen.dataSources.apiKey.notConfigured') }}
            </code>
          </div>

          <!-- API Secret row (dual credential only) -->
          <div v-if="source.isDualCredential" class="flex flex-col gap-1">
            <label class="text-neutral-black-font text-sm font-medium">
              {{ $t('screen.dataSources.apiSecret.label') }}
            </label>
            <code class="bg-primary-lightest rounded px-3 py-2 text-sm">
              {{ config?.api_secret_masked || $t('screen.dataSources.apiSecret.notConfigured') }}
            </code>
          </div>

          <div class="flex">
            <Button
              variant="secondary"
              size="sm"
              :label="$t('screen.dataSources.edit')"
              icon="fas fa-pencil"
              @click="startEditing"
            />
          </div>
        </div>

        <!-- Edit Mode -->
        <div v-else class="flex flex-col gap-3">
          <div class="flex flex-col gap-1">
            <label class="text-neutral-black-font text-sm font-medium">
              {{ $t('screen.dataSources.apiKey.label') }}
            </label>
            <Input
              id="input-api-key"
              v-model="newApiKey"
              type="password"
              :placeholder="$t('screen.dataSources.apiKey.placeholder')"
            />
          </div>

          <div v-if="source.isDualCredential" class="flex flex-col gap-1">
            <label class="text-neutral-black-font text-sm font-medium">
              {{ $t('screen.dataSources.apiSecret.label') }}
            </label>
            <Input
              id="input-api-secret"
              v-model="newApiSecret"
              type="password"
              :placeholder="$t('screen.dataSources.apiSecret.placeholder')"
            />
          </div>

          <div class="flex items-center gap-2">
            <Button
              variant="primary"
              size="sm"
              :label="$t('screen.dataSources.save')"
              :loading="updateMutation.isLoading.value"
              :disabled="isSaveDisabled"
              @click="saveCredentials"
            />
            <Button
              variant="tertiary"
              size="sm"
              :label="$t('screen.dataSources.cancel')"
              :disabled="updateMutation.isLoading.value"
              @click="cancelEditing"
            />
          </div>
        </div>

        <!-- Timestamps -->
        <div
          v-if="config?.enabled_at || config?.updated_at"
          class="text-neutral-black-font text-xs"
        >
          <span v-if="config?.enabled_at">
            {{ $t('screen.dataSources.enabledAt') }}:
            {{ formatDateTime(config.enabled_at) }}
          </span>
          <span v-if="config?.updated_at" class="ml-3">
            {{ $t('screen.dataSources.lastUpdated') }}:
            {{ formatDateTime(config.updated_at) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useUpdateDataSourceConfig } from '@/mutations/data-sources'
import { dataSourceConfigQuery } from '@/queries/data-sources'
import type { DataSourceInfo } from '@/types/data-source'
import { formatDateTime } from '@/utils/time'
import { Button, Icon, Input } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, ref } from 'vue'

const props = defineProps<{
  source: DataSourceInfo
  organizationId: string
}>()

const isEditing = ref(false)
const newApiKey = ref('')
const newApiSecret = ref('')

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

const isSaveDisabled = computed(() => {
  if (!newApiKey.value.trim()) return true
  if (props.source.isDualCredential && !newApiSecret.value.trim()) return true
  return false
})

const startEditing = () => {
  isEditing.value = true
  newApiKey.value = ''
  newApiSecret.value = ''
}

const cancelEditing = () => {
  isEditing.value = false
  newApiKey.value = ''
  newApiSecret.value = ''
}

const saveCredentials = async () => {
  if (isSaveDisabled.value) return

  updateMutation.organizationId.value = props.organizationId
  updateMutation.source.value = props.source.source
  updateMutation.apiKey.value = newApiKey.value
  updateMutation.apiSecret.value = props.source.isDualCredential ? newApiSecret.value : ''

  updateMutation.updateConfig()

  isEditing.value = false
  newApiKey.value = ''
  newApiSecret.value = ''
  refetch()
}
</script>
