<template>
  <!-- Card View -->
  <div
    :class="[
      'bg-bg1 rounded-lg p-4 border border-border-2 ring-offset-2 ring-offset-bg3 transition-all duration-200 group flex flex-col justify-between gap-2 relative',
      { 'hover:ring-4 hover:ring-primary/70': !isChildHovered },
      { 'cursor-auto': folder.is_deleted, 'cursor-pointer': !folder.is_deleted }
    ]"
    @click="!folder.is_deleted && handleCardClick()"
    @mouseenter="isParentHovered = true"
    @mouseleave="isParentHovered = false"
  >
    <!-- Favorite Toggle Button -->
    <button
      v-if="!folder.is_deleted"
      @click.stop="toggleFavorite"
      class="absolute top-3 right-3 z-10 p-2 rounded-lg hover:bg-bg2 transition-colors"
      :title="folder.is_favorite ? 'Remove from favorites' : 'Add to favorites'"
      :disabled="isTogglingFavorite"
    >
      <i
        v-if="!isTogglingFavorite"
        :class="[
          folder.is_favorite
            ? 'fa-jelly-fill fa-regular fa-star text-amber-500'
            : 'fa-jelly fa-regular fa-star text-secondary hover:text-amber-500',
        ]"
      ></i>
      <i v-else class="fas fa-spinner fa-spin text-secondary"></i>
    </button>

    <!-- Restore + Deleted Tag Container -->
    <div
      v-if="folder.is_deleted"
      class="absolute top-3 right-3 z-10 flex items-center gap-2"
    >
      <!-- Restore Button -->
      <button
        @click.stop="emitRestore"
        class="p-2 rounded-lg hover:bg-bg2 transition-colors"
        title="Restore folder"
      >
        <i class="fa-solid fa-undo text-secondary hover:text-primary"></i>
      </button>

      <!-- Deleted Tag -->
      <span
        class="h-fit inline-flex items-center text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded"
      >
        {{ $t('folder.item.deleted', 'Deleted') }}
      </span>
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
          <div
            class="w-12 h-12 rounded-lg flex items-center justify-center"
            :class="folderColorClasses"
          >
            <i :class="folderIcon" class="text-xl"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="text-lg font-semibold group-hover:text-primary transition-colors truncate">
                {{ folder.name }}
              </h3>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm text-secondary">
                {{ $t('folder.itemCount', '{count} items', { count: itemCount }) }}
              </span>
              <div v-if="folder.tags && folder.tags.length > 0" class="flex items-center gap-1">
                <Badge
                  v-for="tag in folder.tags.slice(0, 2)"
                  :key="tag"
                  :label="tag"
                  variant="slate"
                  size="xs"
                />
                <span v-if="folder.tags.length > 2" class="text-xs text-secondary">
                  +{{ folder.tags.length - 2 }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Folder Item Previews -->
      <div v-if="folder.items && folder.items.length > 0" class="mb-4">
        <div
          class="grid grid-cols-2 gap-2"
          @mouseenter="isChildHovered = true"
          @mouseleave="isChildHovered = false"
        >
          <!-- Show first 4 items or first 3 + overflow indicator -->
          <div
            @click.prevent="
              index < 3 || folder.items.length <= 4
                ? $router.push(`/folders/${folder.id}/companies/${item.id}`)
                : $router.push(`/folders/${folder.id}`)
            "
            v-for="(item, index) in previewItems"
            :key="item.id"
            class="bg-bg2 h-24 rounded-md p-2 border border-border-2 min-h-[60px] flex flex-col items-center justify-center hover:ring-2 ring-primary/50 ring-offset-bg2"
          >
            <div
              v-if="index < 3 || folder.items.length <= 4"
              class="flex flex-col justify-center items-center gap-2 min-w-0"
            >
              <div
                class="w-10 h-10 rounded bg-white ring-1 ring-border-2 overflow-hidden flex items-center justify-center flex-shrink-0"
              >
                <img
                  v-if="item.type === 'company' && getCompanyDomain(item.website)"
                  :src="getLogoUrl(item.website)"
                  :alt="`${item.name} logo`"
                  class="w-full h-full object-contain p-0.5"
                  @error="item.showFallbackIcon = true"
                  v-show="!item.showFallbackIcon"
                />
                <div
                  v-show="
                    item.showFallbackIcon ||
                    !getCompanyDomain(item.website) ||
                    item.type !== 'company'
                  "
                  class="w-full h-full flex items-center justify-center bg-primary/10 dark:bg-primary/20"
                >
                  <i class="fas fa-building text-primary text-xs"></i>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate">{{ item.name }}</div>
              </div>
            </div>
            <div v-else class="flex items-center justify-center h-full">
              <div class="text-center">
                <div class="text-lg font-semibold text-secondary">
                  +{{ folder.items.length - 3 }}
                </div>
                <div class="text-xs text-secondary">{{ $t('folder.moreItems', 'more') }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="grid grid-cols-2 gap-2">
        <div
          v-for="i in 4"
          :key="i"
          class="rounded-md border-2 border-dashed border-border-2 bg-bg2 h-24"
        ></div>
      </div>
    </div>

    <!-- Footer with creation date and owner -->
    <div>
      <div class="flex justify-between items-center text-xs text-secondary">
        <span>Created {{ formatDate(folder.created_at) }}</span>
        <span>by {{ folder.owner }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import type { Folder } from '@/types/folder'
import { toggleFolderFavorite } from '@/api/folders'
import { computed, ref } from 'vue'
import { toast } from '@/utils/toast'

interface Props {
  folder: Folder
}

const props = defineProps<Props>()

const emit = defineEmits<{
  viewFolder: [id: string]
  deleteFolder: [folder: Folder]
  restoreFolder: [folder: Folder]
  favoriteToggled: [folder: Folder]
}>()

const isParentHovered = ref(false)
const isChildHovered = ref(false)
const isTogglingFavorite = ref(false)

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
  return props.folder.icon || 'fas fa-folder'
})

// Compute item count
const itemCount = computed(() => {
  return props.folder.items?.length || props.folder.items_count || 0
})

// Compute preview items (show up to 4 items)
const previewItems = computed(() => {
  if (!props.folder.items || props.folder.items.length === 0) return []

  // Always show first 3, then if there are exactly 4 items, show all 4
  // Otherwise show first 3 and use the 4th slot for overflow indicator
  if (props.folder.items.length <= 4) {
    return props.folder.items.slice(0, 4)
  } else {
    return props.folder.items.slice(0, 4) // We'll handle the overflow in template
  }
})

// Helper function to extract domain from website URL
const getCompanyDomain = (website?: string) => {
  if (!website) return null
  try {
    // Remove protocol and www
    let domain = website.replace(/^https?:\/\//, '').replace(/^www\./, '')
    // Remove trailing slash and any path
    domain = domain.split('/')[0]
    return domain
  } catch {
    return null
  }
}

// Helper function to get logo URL from logo.dev
const getLogoUrl = (website?: string) => {
  const domain = getCompanyDomain(website)
  if (!domain) return ''
  return `https://img.logo.dev/${domain}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
}

// Methods
const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const handleCardClick = () => {
  // Only emit viewFolder if not clicking on the favorite button
  emit('viewFolder', props.folder.id)
}

const emitRestore = () => {
  emit('restoreFolder', props.folder)
}

const toggleFavorite = async () => {
  if (isTogglingFavorite.value) return

  isTogglingFavorite.value = true
  try {
    const newFavoriteStatus = !props.folder.is_favorite
    const updatedFolder = await toggleFolderFavorite(props.folder.id, newFavoriteStatus)

    // Update the local folder object
    props.folder.is_favorite = newFavoriteStatus

    // Emit event for parent to handle
    emit('favoriteToggled', updatedFolder)

    toast.success(newFavoriteStatus ? 'Folder added to favorites' : 'Folder removed from favorites')
  } catch (error) {
    console.error('Failed to toggle favorite:', error)
    toast.error('Failed to update favorite status')
  } finally {
    isTogglingFavorite.value = false
  }
}
</script>
