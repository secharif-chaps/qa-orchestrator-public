<template>
  <!-- External APIs Tab -->
  <div class="space-y-6">
    <!-- Header Section -->
    <div>
      <h2 class="text-2xl font-bold text-primary-light-content mb-2">
        {{ $t('team.apis.title', 'External APIs') }}
      </h2>
      <p class="text-primary-light-content max-w-2xl">
        {{
          $t(
            'team.apis.description',
            'Connect external APIs to use as data sources in your workflows. Add your API credentials to integrate third-party services and expand your automation capabilities.',
          )
        }}
      </p>
    </div>

    <!-- APIs List -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Existing APIs -->
      <div
        v-for="api in externalApis"
        :key="api.id"
        class="bg-base-100 p-6 rounded-lg border border-primary-stroke hover:border-primary/50 transition-colors"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
              <i class="fa fa-plug text-primary-light-content"></i>
            </div>
            <div>
              <h4 class="font-semibold text-primary-light-content">{{ api.name }}</h4>
              <p class="text-xs text-primary-light-content">
                {{ $t('team.apis.added', 'Added') }} {{ formatDate(api.createdAt) }}
              </p>
            </div>
          </div>
          <Button
            @click="deleteApi(api.id)"
            variant="ghost-primary"
            icon="fa fa-trash"
            icon-only
            size="sm"
            class="text-error hover:bg-error/10"
          />
        </div>
        <div class="space-y-2">
          <div class="flex items-center gap-2 text-sm">
            <span class="text-primary-light-content">URL:</span>
            <span
              class="text-primary-light-content font-mono text-xs bg-base-200 px-2 py-1 rounded"
              >{{ api.url }}</span
            >
          </div>
          <div class="flex items-center gap-2 text-sm">
            <span class="text-primary-light-content">API Key:</span>
            <span
              class="text-primary-light-content font-mono text-xs bg-base-200 px-2 py-1 rounded"
            >
              {{ showApiKey[api.id] ? api.apiKey : '••••••••' }}
            </span>
            <Button
              @click="toggleApiKey(api.id)"
              variant="ghost-primary"
              :icon="showApiKey[api.id] ? 'fa fa-eye-slash' : 'fa fa-eye'"
              icon-only
              size="sm"
            />
          </div>
          <div class="flex items-center gap-2 mt-3">
            <Badge
              :variant="api.status === 'active' ? 'success' : 'warning'"
              :label="
                api.status === 'active'
                  ? $t('team.apis.active', 'Active')
                  : $t('team.apis.inactive', 'Inactive')
              "
              dot
              size="xs"
            />
            <Badge
              variant="slate"
              :label="`${api.requestCount} ${$t('team.apis.requests', 'requests')}`"
              size="xs"
            />
          </div>
        </div>
      </div>

      <!-- Add New API Card -->
      <div
        v-if="!showAddApiForm"
        @click="showAddApiForm = true"
        class="bg-base-100 p-6 rounded-lg border-2 border-dashed border-primary-stroke hover:border-primary/50 cursor-pointer transition-colors group"
      >
        <div class="flex flex-col items-center justify-center h-full min-h-[200px]">
          <div
            class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-3 group-hover:bg-primary/20 transition-colors"
          >
            <i class="fa fa-plus text-primary-light-content text-lg"></i>
          </div>
          <h4 class="font-semibold text-primary-light-content mb-1">
            {{ $t('team.apis.add_new', 'Add External API') }}
          </h4>
          <p class="text-sm text-primary-light-content text-center">
            {{ $t('team.apis.add_description', 'Connect a new data source') }}
          </p>
        </div>
      </div>

      <!-- Add API Form -->
      <div v-else class="bg-base-100 p-6 rounded-lg border-2 border-primary/50">
        <div class="space-y-4">
          <div class="flex items-center justify-between mb-2">
            <h4 class="font-semibold text-primary-light-content">
              {{ $t('team.apis.new_api', 'New External API') }}
            </h4>
            <Button
              @click="cancelAddApi"
              variant="ghost-primary"
              icon="fa fa-times"
              icon-only
              size="sm"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-primary-light-content mb-1">
              {{ $t('team.apis.name', 'API Name') }}
            </label>
            <Input v-model="newApi.name" placeholder="e.g., Weather API" class="w-full" />
          </div>

          <div>
            <label class="block text-sm font-medium text-primary-light-content mb-1">
              {{ $t('team.apis.url', 'API URL') }}
            </label>
            <Input v-model="newApi.url" placeholder="https://api.example.com/v1" class="w-full" />
          </div>

          <div>
            <label class="block text-sm font-medium text-primary-light-content mb-1">
              {{ $t('team.apis.api_key', 'API Key') }}
            </label>
            <Input
              v-model="newApi.apiKey"
              type="password"
              placeholder="Your API key"
              class="w-full"
            />
          </div>

          <div class="flex gap-2">
            <Button
              @click="saveApi"
              variant="primary"
              icon="fa fa-save"
              :disabled="!newApi.name || !newApi.url || !newApi.apiKey"
              class="flex-1"
            >
              {{ $t('team.apis.save', 'Save API') }}
            </Button>
            <Button @click="cancelAddApi" variant="secondary" class="flex-1">
              {{ $t('common.cancel', 'Cancel') }}
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-if="externalApis.length === 0 && !showAddApiForm"
      class="bg-base-100 p-12 rounded-lg text-center"
    >
      <i class="fa fa-plug text-4xl text-primary-light-content mb-4"></i>
      <h3 class="text-lg font-semibold text-primary-light-content mb-2">
        {{ $t('team.apis.no_apis', 'No External APIs') }}
      </h3>
      <p class="text-primary-light-content mb-6">
        {{ $t('team.apis.no_apis_description', 'Add external APIs to connect new data sources') }}
      </p>
      <Button @click="showAddApiForm = true" variant="primary" icon="fa fa-plus">
        {{ $t('team.apis.add_first', 'Add Your First API') }}
      </Button>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - workspace.read
</route>

<script setup lang="ts">
import { ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Input from '@/components/ui/Input.vue'

// External APIs management (mock data)
interface ExternalApi {
  id: string
  name: string
  url: string
  apiKey: string
  status: 'active' | 'inactive'
  createdAt: Date
  requestCount: number
}

const externalApis = ref<ExternalApi[]>([
  {
    id: '1',
    name: 'Weather API',
    url: 'https://api.openweathermap.org/data/2.5',
    apiKey: 'sk_test_4eC39HqLyjWDarjtT1zdp7dc',
    status: 'active',
    createdAt: new Date('2024-01-15'),
    requestCount: 1250,
  },
  {
    id: '2',
    name: 'Geocoding Service',
    url: 'https://api.mapbox.com/geocoding/v5',
    apiKey: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ',
    status: 'active',
    createdAt: new Date('2024-02-01'),
    requestCount: 850,
  },
])

const showAddApiForm = ref(false)
const showApiKey = ref<Record<string, boolean>>({})
const newApi = ref({
  name: '',
  url: '',
  apiKey: '',
})

const toggleApiKey = (id: string) => {
  showApiKey.value[id] = !showApiKey.value[id]
}

const saveApi = () => {
  // Mock save - in real implementation, this would call an API
  const api: ExternalApi = {
    id: Date.now().toString(),
    name: newApi.value.name,
    url: newApi.value.url,
    apiKey: newApi.value.apiKey,
    status: 'active',
    createdAt: new Date(),
    requestCount: 0,
  }

  externalApis.value.push(api)

  // Reset form
  newApi.value = {
    name: '',
    url: '',
    apiKey: '',
  }
  showAddApiForm.value = false
}

const cancelAddApi = () => {
  newApi.value = {
    name: '',
    url: '',
    apiKey: '',
  }
  showAddApiForm.value = false
}

const deleteApi = (id: string) => {
  // Mock delete - in real implementation, this would call an API
  const index = externalApis.value.findIndex((api) => api.id === id)
  if (index > -1) {
    externalApis.value.splice(index, 1)
  }
}

const formatDate = (date: Date) => {
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(date)
}
</script>
