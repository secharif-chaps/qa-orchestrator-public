<template>
  <div class="flex flex-col gap-4">
    <!-- Header -->
    <div>
      <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div
            class="flex h-16 w-16 items-center justify-center rounded-lg"
            :class="folderColorClasses"
          >
            <i :class="folderIcon" class="text-3xl"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-3xl font-bold">
                {{ folder?.name || $t('common.folder.loading') }}
              </h1>
              <!-- Shared badge if not owner -->
              <Tag
                v-if="isSharedWithMe"
                :label="$t('common.folder.shared.badge')"
                intent="info"
                size="sm"
              />
              <!-- Role badge if shared -->
              <Tag
                v-if="isSharedWithMe && shareRoleLabel"
                :label="shareRoleLabel"
                variant="secondary"
                size="sm"
              />
            </div>
            <p class="text-secondary mt-2" v-if="folder">
              {{ $t('common.folder.header.itemsCount', itemsCount) }} |
              {{ $t('common.folder.header.createdOn', { date: formatDate(folder.created_at) }) }}
              <!-- Show owner info differently for shared vs owned folders -->
              <template v-if="isSharedWithMe">
                | {{ $t('common.folder.grid.owner') }} @{{ folder.owner }}
              </template>
              <template v-else> {{ $t('common.folder.header.by') }} @{{ folder.owner }} </template>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Favorite Toggle Button -->
          <Button
            variant="tertiary"
            :icon="folder?.is_favorite ? 'fas fa-star' : 'far fa-star'"
            :class="folder?.is_favorite ? 'text-amber-600' : ''"
            :label="
              folder?.is_favorite
                ? $t('common.folder.actions.unfavorite')
                : $t('common.folder.actions.favorite')
            "
            :loading="isTogglingFavorite"
            @click="toggleFavorite"
          />

          <!-- Share Button (only for owners) -->
          <FolderShareButton v-if="folder" :folder="folder" />

          <!-- Edit Button (only for owners) -->
          <Button
            v-if="canEditFolder"
            variant="tertiary"
            icon="fa fa-edit"
            :label="$t('common.folder.actions.edit')"
            @click="handleEditFolder"
          />

          <!-- Delete Button (only for owners) -->
          <Button
            v-if="canDeleteFolder"
            variant="tertiary"
            color="danger"
            icon="fa fa-trash"
            :label="$t('common.folder.actions.delete')"
            @click="handleDeleteFolder"
          />
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="flex items-center justify-between gap-4 rounded-lg">
        <!-- Search Input -->
        <div class="max-w-md flex-1">
          <Searchbar
            id="folder-search-input"
            v-model="searchTerm"
            :placeholder="$t('common.folder.search.placeholder')"
          />
        </div>

        <div class="flex items-center gap-2">
          <!-- Add Items Dropdown (only if user can create items) -->
          <Dropdown v-if="canCreateItems" align="left" width="xl">
            <template #trigger>
              <Button
                variant="secondary"
                icon="fa fa-plus"
                :label="$t('common.folder.items.add')"
              />
            </template>

            <template #content>
              <!-- Company Screen - Only visible when Screen module is enabled -->
              <DropdownItem
                v-if="isScreenEnabled"
                icon="fas fa-building"
                color="blue"
                :label="$t('common.folder.addItems.companyScreen')"
                :description="$t('common.folder.addItems.companyDescription')"
                @click="
                  $router.push(
                    `/folders/${($route.params as Record<string, string>).folderId}/create/company`,
                  )
                "
              />

              <!-- Watchfile - Disabled -->
              <DropdownItem
                disabled
                icon="fas fa-eye"
                color="green"
                :label="$t('common.folder.addItems.watchfile')"
                :description="$t('common.folder.addItems.watchfileDescription')"
              >
                <template #suffix>
                  <Tag variant="secondary" size="xs" :label="$t('common.soon')" />
                </template>
              </DropdownItem>

              <!-- GraphRag - Disabled -->
              <DropdownItem
                disabled
                icon="fas fa-project-diagram"
                color="purple"
                :label="$t('common.folder.addItems.graphrag')"
                :description="$t('common.folder.addItems.graphragDescription')"
              >
                <template #suffix>
                  <Tag variant="secondary" size="xs" :label="$t('common.soon')" />
                </template>
              </DropdownItem>
            </template>
          </Dropdown>

          <div class="flex items-center gap-4">
            <!-- Filter Buttons -->
            <Toggle v-model="companyFilter" :options="filterOptions" variant="pill" />

            <!-- View Mode Toggle -->
            <Toggle v-model="viewMode" :options="viewModeOptions" variant="pill" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, toRef } from 'vue'
import { Button, Searchbar, Tag, Toggle } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import type { Folder } from '@/types/folder'
import { useToggleFolderFavorite } from '@/mutations/folders'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import { useScreenModule } from '@/composables/useScreenModule'
import FolderShareButton from '@/components/features/folders/FolderShareButton.vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import { formatDate } from '@/utils/time'

interface Props {
  folder?: Folder | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'edit-folder': []
  'delete-folder': []
}>()

const { t } = useI18n()

// Folder permissions
const folderRef = toRef(props, 'folder')
const { canEditFolder, canDeleteFolder, canCreateItems, isSharedWithMe } =
  useFolderPermissions(folderRef)

const { isScreenEnabled } = useScreenModule()

// Use mutation for optimistic UI
const { toggleFavorite: toggleFavoriteMutation, isLoading: isTogglingFavorite } =
  useToggleFolderFavorite()

// Toggle favorite status
async function toggleFavorite() {
  if (!props.folder || isTogglingFavorite.value) return

  const shouldBeFavorite = !props.folder.is_favorite
  await toggleFavoriteMutation({
    folderId: props.folder.id,
    shouldBeFavorite,
  })
}

// Emit handlers to satisfy eslint
function handleEditFolder() {
  emit('edit-folder')
}

function handleDeleteFolder() {
  emit('delete-folder')
}

// Compute share role label for display
const shareRoleLabel = computed(() => {
  if (!props.folder?.share_role) return ''
  return props.folder.share_role === 'writer'
    ? t('common.folder.share.writer')
    : t('common.folder.share.reader')
})

// v-model for search term
const searchTerm = defineModel<string>('searchTerm', { default: '' })

// v-model for viewMode
const viewMode = defineModel<'table' | 'grid'>('viewMode', { required: true })

// v-model for companyFilter
const companyFilter = defineModel<'all' | 'archived'>('companyFilter', { required: true })

// Filter options for Toggle
const filterOptions = computed(() => [
  {
    value: 'all',
    icon: 'fas fa-building',
    label: t('common.folder.filter.allLabel'),
  },
  {
    value: 'archived',
    icon: 'fas fa-archive',
    label: t('common.folder.filter.archivedLabel'),
  },
])

// View mode options for Toggle
const viewModeOptions = computed(() => [
  {
    value: 'table',
    label: t('common.folder.viewMode.table'),
    icon: 'fa fa-list',
  },
  {
    value: 'grid',
    label: t('common.folder.viewMode.grid'),
    icon: 'fa fa-th-large',
  },
])

// Computed for item count
const itemsCount = computed(() => {
  return props.folder?.items?.length || 0
})

// Compute folder color classes based on the color prop
const folderColorClasses = computed(() => {
  const color = props.folder?.color || 'blue'
  const colorMap: Record<string, string> = {
    blue: 'bg-blue-100 dark:bg-blue-400/20 text-blue-600 dark:text-blue-400',
    green: 'bg-green-100 dark:bg-green-400/20 text-green-600 dark:text-green-400',
    yellow: 'bg-yellow-100 dark:bg-yellow-400/20 text-yellow-600 dark:text-yellow-400',
    red: 'bg-red-100 dark:bg-red-400/20 text-red-600 dark:text-red-400',
    purple: 'bg-purple-100 dark:bg-purple-400/20 text-purple-600 dark:text-purple-400',
    gray: 'bg-gray-100 dark:bg-gray-400/20 text-gray-600 dark:text-gray-400',
    orange: 'bg-orange-100 dark:bg-orange-400/20 text-orange-600 dark:text-orange-400',
    pink: 'bg-pink-100 dark:bg-pink-400/20 text-pink-600 dark:text-pink-400',
    cyan: 'bg-cyan-100 dark:bg-cyan-400/20 text-cyan-600 dark:text-cyan-400',
    fuchsia: 'bg-fuchsia-100 dark:bg-fuchsia-400/20 text-fuchsia-600 dark:text-fuchsia-400',
    rose: 'bg-rose-100 dark:bg-rose-400/20 text-rose-600 dark:text-rose-400',
    emerald: 'bg-emerald-100 dark:bg-emerald-400/20 text-emerald-600 dark:text-emerald-400',
    teal: 'bg-teal-100 dark:bg-teal-400/20 text-teal-600 dark:text-teal-400',
    sky: 'bg-sky-100 dark:bg-sky-400/20 text-sky-600 dark:text-sky-400',
    indigo: 'bg-indigo-100 dark:bg-indigo-400/20 text-indigo-600 dark:text-indigo-400',
    violet: 'bg-violet-100 dark:bg-violet-400/20 text-violet-600 dark:text-violet-400',
  }
  return colorMap[color] || colorMap.blue
})

// Compute folder icon
const folderIcon = computed(() => {
  return props.folder?.icon || 'fas fa-folder'
})
</script>
