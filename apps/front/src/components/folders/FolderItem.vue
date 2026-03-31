<template>
  <!-- Card View -->
  <Card
    @click="handleCardClick"
    clickable
    @mouseenter="isParentHovered = true"
    @mouseleave="isParentHovered = false"
    class="relative"
  >
    <div class="flex flex-col gap-2">
      <div class="flex items-start justify-between">
        <div class="flex w-full items-center gap-3">
          <div
            :class="folderColorClasses"
            class="flex h-12 w-12 items-center justify-center rounded-lg"
          >
            <i :class="[folderIcon]" class="text-xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <div class="mb-1 flex items-center gap-2">
              <h3
                class="group-hover:text-secondary truncate text-lg font-semibold transition-colors"
              >
                {{ folder.name }}
              </h3>
              <!-- Shared with me badge -->
              <Tag
                v-if="isSharedWithMe"
                :label="$t('common.folder.shared.badge')"
                intent="info"
                class="mr-0 ml-auto"
                size="xs"
              />
              <!-- Favorite Toggle Button / Indicator -->
              <button
                @click.stop="toggleFavorite"
                class="z-10 flex size-8 items-center justify-center rounded-full transition-all duration-200"
                :class="[
                  folder.is_favorite ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'hover:bg-base-200',
                  isSharedWithMe ? 'ml-0' : 'ml-auto',
                ]"
                :title="
                  folder.is_favorite
                    ? $t('common.folder.actions.removeFromFavorites')
                    : $t('common.folder.actions.addToFavorites')
                "
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
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-secondary text-sm">
                {{ $t('common.folder.itemCount', itemCount) }}
              </span>
              <!-- Share role indicator -->
              <Tag
                v-if="isSharedWithMe && shareRoleLabel"
                :label="shareRoleLabel"
                variant="secondary"
                size="xs"
              />
              <div v-if="folder.tags && folder.tags.length > 0" class="flex items-center gap-1">
                <Tag
                  v-for="tag in folder.tags.slice(0, 2)"
                  :key="tag"
                  :label="tag"
                  variant="secondary"
                  size="xs"
                />
                <span v-if="folder.tags.length > 2" class="text-secondary text-xs">
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
        class="relative mb-4 overflow-hidden rounded-xl"
      >
        <div
          class="bg-base-200 flex h-64 flex-col justify-start gap-2 overflow-y-auto rounded-xl p-4"
          @mouseenter="isChildHovered = true"
          @mouseleave="isChildHovered = false"
        >
          <!-- Show first 4 items or first 3 + overflow indicator -->
          <CompanyCardItem
            v-for="(item, index) in previewItems"
            :key="item.id"
            :name="item.name"
            :website="item.website"
            @click="handleItemClick(item.id, index)"
          />
          <div
            class="from-bg2 absolute top-0 left-0 z-10 h-6 w-full bg-gradient-to-b to-transparent"
          ></div>
          <div
            class="to-bg2 absolute bottom-0 left-0 z-10 h-4 w-full bg-gradient-to-b from-transparent"
          ></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-base-200 mb-4 h-64 rounded-xl p-4">
        <div class="flex flex-col justify-start gap-2">
          <!-- Only show add button if user can create items -->
          <div
            v-if="canCreateItems"
            @click.prevent="$router.push(`/folders/${folder.id}/create/company`)"
            class="group border-primary-stroke bg-base-200 dark:bg-base-100 hover:bg-base-300 h-16 rounded-md border-2 border-dashed"
          >
            <div class="flex h-full items-center justify-center">
              <div class="flex h-full items-center justify-center gap-2">
                <span
                  class="bg-sage-100 group-hover:bg-sage-200 dark:bg-sage-800 group-hover:dark:bg-sage-700 flex h-8 w-8 items-center justify-center rounded-lg"
                >
                  <i class="fas fa-plus text-secondary text-sm"></i>
                </span>
                <span class="text-secondary text-sm">{{
                  $t('common.folder.addItems.company')
                }}</span>
              </div>
            </div>
          </div>
          <!-- Read-only empty state for readers -->
          <div
            v-else
            class="border-primary-stroke bg-base-200 dark:bg-base-100 h-16 rounded-md border-2 border-dashed"
          >
            <div class="flex h-full items-center justify-center">
              <span class="text-secondary text-sm">{{ $t('common.folder.empty.readOnly') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer with creation date and owner -->
    <div>
      <div class="text-secondary flex items-center justify-between text-xs">
        <span>{{ $t('common.folder.grid.created') }} {{ formatDate(folder.created_at) }}</span>
        <span>
          <!-- Show "by @owner" for shared folders, or just owner for owned folders -->
          <template v-if="isSharedWithMe">
            {{ $t('common.folder.grid.owner') }} @{{ folder.owner }}
          </template>
          <template v-else> {{ $t('common.folder.grid.by') }} @{{ folder.owner }} </template>
        </span>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { Tag } from '@owlint/feathers-vue'
import type { Folder } from '@/types/folder'
import { useToggleFolderFavorite } from '@/mutations/folders'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import { computed, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { formatDate } from '@/utils/time'
import Card from '../ui/Card.vue'
import CompanyCardItem from './CompanyCardItem.vue'

const { t } = useI18n()
const router = useRouter()

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

// Folder permissions
const folderRef = toRef(props, 'folder')
const { isSharedWithMe, canCreateItems } = useFolderPermissions(folderRef)

// Use mutation for optimistic UI
const { toggleFavorite: toggleFavoriteMutation, isLoading: isTogglingFavorite } =
  useToggleFolderFavorite()

// Compute share role label for display
const shareRoleLabel = computed(() => {
  if (!props.folder.share_role) return ''
  return props.folder.share_role === 'writer'
    ? t('common.folder.share.writer')
    : t('common.folder.share.reader')
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
    amber: 'bg-amber-100 dark:bg-amber-400/20 text-amber-600 dark:text-amber-400',
    lime: 'bg-lime-100 dark:bg-lime-400/20 text-lime-600 dark:text-lime-400',
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

function handleCardClick() {
  // Only emit viewFolder if not clicking on the favorite button
  emit('viewFolder', props.folder.id)
}

function handleItemClick(itemId: string, index: number) {
  if (index < 3 || (props.folder.items && props.folder.items.length <= 4)) {
    router.push(`/folders/${props.folder.id}/companies/${itemId}`)
  } else {
    router.push(`/folders/${props.folder.id}`)
  }
}

async function toggleFavorite() {
  if (isTogglingFavorite.value) return

  const shouldBeFavorite = !props.folder.is_favorite
  await toggleFavoriteMutation({
    folderId: props.folder.id,
    shouldBeFavorite,
  })
}

// Suppress unused variable warnings for hover refs used only in template bindings
void isParentHovered.value
void isChildHovered.value
</script>
