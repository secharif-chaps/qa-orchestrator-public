<template>
  <!-- Card View -->
  <Card
    @click="handleCardClick"
    clickable
    @mouseenter="isParentHovered = true"
    @mouseleave="isParentHovered = false"
    class="relative"
  >
    <!-- Favorite Toggle Button / Indicator -->
    <button
      @click.stop="toggleFavorite"
      class="absolute top-3 right-3 size-8 z-10 flex items-center justify-center rounded-full transition-all duration-200"
      :class="[
        folder.is_favorite
          ? 'bg-yellow-100 dark:bg-yellow-900/30'
          : 'hover:bg-base-200',
      ]"
      :title="folder.is_favorite ? 'Remove from favorites' : 'Add to favorites'"
      :disabled="isTogglingFavorite"
    >
      <i
        v-if="!isTogglingFavorite"
        :class="[
          folder.is_favorite
            ? 'fas fa-star text-yellow-500'
            : 'far fa-star text-secondary hover:text-yellow-500',
        ]"
        class="text-sm"
      ></i>
      <i v-else class="fas fa-spinner fa-spin text-secondary text-sm"></i>
    </button>

    <div class="flex flex-col gap-2">
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
          <div
            class="w-12 h-12 rounded-lg flex items-center justify-center bg-sage-50 dark:bg-sage-900 text-sage-600 dark:text-sage-400"
          >
            <i :class="folderIcon" class="text-xl"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <h3
                class="text-lg font-semibold group-hover:text-secondary transition-colors truncate"
              >
                {{ folder.name }}
              </h3>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm text-secondary">
                {{ $t('folder.itemCount', itemCount) }}
              </span>
              <div v-if="folder.tags && folder.tags.length > 0" class="flex items-center gap-1">
                <Tag
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
      <div
        v-if="folder.items && folder.items.length > 0"
        class="mb-4 relative rounded-xl overflow-hidden"
      >
        <div
          class="grid grid-cols-1 gap-2 bg-base-200 p-4 rounded-xl max-h-64 overflow-y-auto"
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
            class="bg-base-100 rounded-md p-2 border border-primary-stroke min-h-[60px] flex items-center hover:ring-2 ring-primary/50 ring-offset-bg2"
          >
            <div class="flex items-center gap-2 min-w-0">
              <div
                class="w-10 h-10 rounded bg-white ring-1 ring-primary-stroke overflow-hidden flex items-center flex-shrink-0"
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
                  <i class="fas fa-building text-secondary text-xs"></i>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate">{{ item.name }}</div>
              </div>
            </div>
          </div>
          <div
            class="absolute top-0 left-0 h-6 w-full bg-gradient-to-b from-bg2 to-transparent z-10"
          ></div>
          <div
            class="absolute bottom-0 left-0 h-4 w-full bg-gradient-to-b from-transparent to-bg2 z-10"
          ></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="grid grid-cols-1 gap-2">
        <div
          v-for="i in 3"
          :key="i"
          class="rounded-md border-2 border-dashed border-primary-stroke bg-base-200 dark:bg-base-100 h-16"
        ></div>
      </div>
    </div>

    <!-- Footer with creation date and owner -->
    <div>
      <div class="flex justify-between items-center text-xs text-secondary">
        <span>{{ $t('folder.grid.created') }} {{ formatDate(folder.created_at) }}</span>
        <span>{{ $t('folder.grid.by') }} @{{ folder.owner }}</span>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import Tag from '@/components/ui/Tag.vue'
import type { Folder } from '@/types/folder'
import { useToggleFolderFavorite } from '@/mutations/folders'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '../ui/Card.vue'

const { t, locale } = useI18n()

interface Props {
  folder: Folder
}

const props = defineProps<Props>()

const emit = defineEmits<{
  viewFolder: [id: string]
  deleteFolder: [folder: Folder]
  restoreFolder: [folder: Folder]
}>()

const isParentHovered = ref(false)
const isChildHovered = ref(false)

// Use mutation for optimistic UI
const { toggleFavorite: toggleFavoriteMutation, isLoading: isTogglingFavorite } = useToggleFolderFavorite()

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

  return props.folder.items
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
function formatDate(dateString: string): string {
  if (!dateString) return t('common.na')
  const localeCode = locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'
  return new Date(dateString).toLocaleDateString(localeCode)
}

const handleCardClick = (event: MouseEvent) => {
  // Only emit viewFolder if not clicking on the favorite button
  emit('viewFolder', props.folder.id)
}

async function toggleFavorite() {
  if (isTogglingFavorite.value) return

  const shouldBeFavorite = !props.folder.is_favorite
  await toggleFavoriteMutation({
    folderId: props.folder.id,
    shouldBeFavorite,
  })
}
</script>
