<template>
  <div class="flex flex-col gap-4">
    <!-- Header -->
    <div>
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
          <div
            class="w-16 h-16 rounded-lg flex items-center justify-center border border-border-2"
            :class="folderColorClasses"
          >
            <i :class="folderIcon" class="text-3xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-bold">
              {{ folder?.name || $t('folder.loading', 'Loading folder...') }}
            </h1>
            <p class="text-secondary mt-2" v-if="folder">
              {{ folder.items?.length || 0 }} items • created on
              {{ formatDate(folder.created_at) }} by {{ folder.owner_username }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Button
            variant="tertiary"
            icon="fa fa-edit"
            :label="$t('folder.actions.edit', 'Edit')"
            @click="$emit('edit-folder')"
          />
          <Button
            variant="tertiary"
            color="danger"
            icon="fa fa-trash"
            :label="$t('folder.actions.delete', 'Delete')"
            @click="$emit('delete-folder')"
          />
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="flex items-center justify-between gap-4 rounded-lg">
        <!-- Search Input -->
        <div class="flex-1 max-w-md">
          <div class="relative">
            <i
              class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-secondary"
            ></i>
            <input
              v-model="searchTerm"
              type="text"
              :placeholder="$t('folder.search.placeholder', 'Search items...')"
              class="w-full pl-10 pr-4 py-2 border border-border-2 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-bg1"
            />
          </div>
        </div>

        <div class="flex gap-2">
          <div class="relative text-center">
            <Button
              variant="secondary"
              icon="fa fa-plus"
              :label="$t('folder.items.add', 'Add Items')"
              @click="showAddItemsDropdown = !showAddItemsDropdown"
            />

            <!-- Backdrop to close dropdown -->
            <div
              v-if="showAddItemsDropdown"
              class="fixed inset-0 z-40"
              @click="showAddItemsDropdown = false"
            ></div>

            <!-- Dropdown Menu -->
            <div
              v-if="showAddItemsDropdown"
              class="absolute left-1/2 transform -translate-x-1/2 top-full mt-2 w-80 bg-bg2 border border-border-2 rounded-lg shadow-lg z-50"
            >
              <div class="p-2">
                <!-- Company Screen - Enabled -->
                <button
                  class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-bg3 rounded-md transition-colors"
                  @click="$router.push(`/folders/${$route.params.folderId}/create/company`)"
                >
                  <div
                    class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center"
                  >
                    <i class="fas fa-building text-blue-600 dark:text-blue-400 text-sm"></i>
                  </div>
                  <div class="flex-1">
                    <div class="font-medium text-sm">
                      {{ $t('folder.addItems.companyScreen', 'Company Screen') }}
                    </div>
                    <div class="text-xs text-secondary">
                      {{ $t('folder.addItems.companyDescription', 'Add company profiles') }}
                    </div>
                  </div>
                </button>

                <!-- Watchfile - Disabled -->
                <button
                  class="w-full flex items-center gap-3 px-3 py-2 text-left opacity-50 cursor-not-allowed rounded-md"
                  disabled
                >
                  <div
                    class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/20 flex items-center justify-center"
                  >
                    <i class="fas fa-eye text-green-600 dark:text-green-400 text-sm"></i>
                  </div>
                  <div class="flex-1">
                    <div class="font-medium text-sm">
                      {{ $t('folder.addItems.watchfile', 'Watchfile') }}
                    </div>
                    <div class="text-xs text-secondary">
                      {{ $t('folder.addItems.watchfileDescription', 'Monitor company changes') }}
                    </div>
                  </div>
                  <Badge variant="slate" size="xs" label="Soon" />
                </button>

                <!-- GraphRag - Disabled -->
                <button
                  class="w-full flex items-center gap-3 px-3 py-2 text-left opacity-50 cursor-not-allowed rounded-md"
                  disabled
                >
                  <div
                    class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center"
                  >
                    <i
                      class="fas fa-project-diagram text-purple-600 dark:text-purple-400 text-sm"
                    ></i>
                  </div>
                  <div class="flex-1">
                    <div class="font-medium text-sm">
                      {{ $t('folder.addItems.graphrag', 'Knowledge graph') }}
                    </div>
                    <div class="text-xs text-secondary">
                      {{
                        $t('folder.addItems.graphragDescription', 'explore ecosystem with GraphRAG')
                      }}
                    </div>
                  </div>
                  <Badge variant="slate" size="xs" label="Soon" />
                </button>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <!-- Filter Buttons -->
            <ButtonGroup v-model="companyFilter" :options="filterOptions" />

            <!-- View Mode Toggle -->
            <ButtonGroup v-model="viewMode" :options="viewModeOptions" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import ButtonGroup from '@/components/ui/ButtonGroup.vue'
import { useI18n } from 'vue-i18n'
import type { Folder } from '@/types/folder'

interface Props {
  folder?: Folder | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'edit-folder': []
  'delete-folder': []
}>()

const { t } = useI18n()
const showAddItemsDropdown = ref(false)

// v-model for search term
const searchTerm = defineModel<string>('searchTerm', { default: '' })

// v-model for viewMode
const viewMode = defineModel<'table' | 'grid'>('viewMode', { required: true })

// v-model for companyFilter
const companyFilter = defineModel<'all' | 'archived'>('companyFilter', { required: true })

// Filter options for ButtonGroup
const filterOptions = computed(() => [
  {
    value: 'all',
    icon: 'fas fa-building',
    title: 'All companies',
    label: 'All',
  },
  {
    value: 'archived',
    icon: 'fas fa-archive',
    title: 'Archived companies',
    label: 'Archived',
  },
])

// View mode options for ButtonGroup
const viewModeOptions = computed(() => [
  {
    value: 'table',
    label: 'Table',
    icon: 'fa fa-list',
    title: t('folder.view.table', 'Table View'),
  },
  {
    value: 'grid',
    label: 'Grid',
    icon: 'fa fa-th-large',
    title: t('folder.view.grid', 'Grid View'),
  },
])

// Methods
const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

// Compute folder color classes based on the color prop
const folderColorClasses = computed(() => {
  const color = props.folder?.color || 'blue'
  const colorMap: Record<string, string> = {
    blue: 'bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
    green: 'bg-green-100 dark:bg-green-900/20 text-green-600 dark:text-green-400',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-600 dark:text-yellow-400',
    red: 'bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400',
    purple: 'bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
    gray: 'bg-gray-100 dark:bg-gray-900/20 text-gray-600 dark:text-gray-400',
    orange: 'bg-orange-100 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400',
    pink: 'bg-pink-100 dark:bg-pink-900/20 text-pink-600 dark:text-pink-400',
    cyan: 'bg-cyan-100 dark:bg-cyan-900/20 text-cyan-600 dark:text-cyan-400',
    fuchsia: 'bg-fuchsia-100 dark:bg-fuchsia-900/20 text-fuchsia-600 dark:text-fuchsia-400',
    rose: 'bg-rose-100 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400',
    emerald: 'bg-emerald-100 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400',
    teal: 'bg-teal-100 dark:bg-teal-900/20 text-teal-600 dark:text-teal-400',
    sky: 'bg-sky-100 dark:bg-sky-900/20 text-sky-600 dark:text-sky-400',
    indigo: 'bg-indigo-100 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400',
    violet: 'bg-violet-100 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400',
  }
  return colorMap[color] || colorMap.blue
})

// Compute folder icon
const folderIcon = computed(() => {
  return props.folder?.icon || 'fas fa-folder'
})
</script>
