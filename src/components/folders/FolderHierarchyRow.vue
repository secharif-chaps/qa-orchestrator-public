<template>
  <div>
    <!-- Folder Row -->
    <div
      class="px-6 py-4 hover:bg-base-200 transition-colors cursor-pointer"
      @click="toggleExpanded"
    >
      <div
        :class="globalView ? 'grid grid-cols-14 gap-4 items-center' : 'grid grid-cols-12 gap-4 items-center'"
      >
        <!-- Name with expand/collapse icon -->
        <div class="col-span-5 flex items-center gap-3">
          <button
            class="w-6 h-6 flex items-center justify-center text-secondary hover:text-secondary transition-colors"
            @click.stop="toggleExpanded"
          >
            <i
              :class="isExpanded ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
              class="text-xs"
            ></i>
          </button>

          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center border border-primary-stroke"
            :class="folderColorClasses"
          >
            <i :class="folderIcon" class="text-lg"></i>
          </div>

          <div class="flex-1 flex items-center gap-2">
            <h3 class="font-medium">{{ folder.name }}</h3>
            <!-- Privacy Tags (Global View Only) -->
            <template v-if="globalView">
              <UiTag
                v-if="folder.is_owner && !hasShares"
                variant="slate"
                :label="$t('folder.privacy.private')"
                size="xs"
                rounded
              />
              <UiTag
                v-else-if="hasShares"
                variant="info"
                icon="fa fa-share-nodes"
                :label="$t('folder.privacy.shared')"
                size="xs"
                rounded
              />
            </template>
            <!-- Shared badge (non-global view) -->
            <UiTag
              v-else-if="isSharedWithMe"
              :label="$t('folder.shared.badge', 'Shared')"
              variant="info"
              size="xs"
            />
            <!-- Role badge -->
            <UiTag
              v-if="!globalView && isSharedWithMe && shareRoleLabel"
              :label="shareRoleLabel"
              variant="slate"
              size="xs"
            />
          </div>
        </div>

        <!-- Owner Column (Global View Only) -->
        <div v-if="globalView" class="col-span-2 flex items-center gap-2">
          <div
            class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium"
            :class="folder.is_owner ? 'bg-primary text-white' : 'bg-secondary text-white'"
          >
            {{ ownerInitials }}
          </div>
          <span class="text-sm text-secondary">
            {{ folder.is_owner ? $t('folder.owner.you') : folder.owner_username }}
          </span>
        </div>

        <!-- Items count -->
        <div class="col-span-2">
          <UiTag
            variant="slate"
            :label="$t('folder.itemsChip', folder.items?.length || 0)"
            size="sm"
          />
        </div>

        <!-- Created date -->
        <div class="col-span-1">
          <span class="text-sm text-secondary">{{ formatDate(folder.created_at) }}</span>
        </div>

        <!-- Actions -->
        <div class="col-span-2 text-right">
          <div class="flex items-center justify-end gap-2">
            <Button
              variant="tertiary"
              size="sm"
              icon="fa fa-external-link-alt"
              :label="$t('folder.actions.view', 'View')"
              @click.stop="$emit('view-folder', folder.id)"
            />
            <!-- Delete button only for owners -->
            <Button
              v-if="canDeleteFolder"
              variant="tertiary"
              size="sm"
              color="danger"
              icon="fa fa-trash"
              icon-only
              @click.stop="$emit('delete-folder', folder)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Expanded Items -->
    <div v-if="isExpanded && folder.items && folder.items.length > 0" class="bg-base-200/30">
      <div
        v-for="item in folder.items"
        :key="item.id"
        class="px-6 py-3 hover:bg-base-200/50 transition-colors cursor-pointer border-l-4 border-primary/20 ml-12"
        @click="$emit('view-item', { itemId: item.id, folderId: folder.id })"
      >
        <div class="grid grid-cols-12 gap-4 items-center">
          <!-- Item name with indentation -->
          <div class="col-span-5 flex items-center gap-3 pl-8">
            <div
              class="w-8 h-8 rounded-lg bg-white ring-1 ring-primary-stroke overflow-hidden flex items-center justify-center flex-shrink-0"
            >
              <img
                v-if="item.type === 'company' && getCompanyDomain(item.website)"
                :src="getLogoUrl(item.website)"
                :alt="`${item.name} logo`"
                class="w-full h-full object-contain p-1"
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
                <i class="fas fa-building text-secondary text-sm"></i>
              </div>
            </div>
            <div class="flex-1">
              <h4 class="font-medium text-sm">{{ item.name }}</h4>
            </div>
          </div>

          <!-- Item type -->
          <div class="col-span-2">
            <UiTag variant="accent" :label="formatItemType(item.type)" size="xs" />
          </div>

          <!-- Item owner -->
          <div class="col-span-2">
            <span class="text-xs text-secondary">{{ item.owner || '' }}</span>
          </div>

          <!-- Item created date -->
          <div class="col-span-1">
            <span class="text-xs text-secondary">{{ formatDate(item.created_at) }}</span>
          </div>

          <!-- Item actions -->
          <div class="col-span-2 text-right">
            <Button
              variant="tertiary"
              size="sm"
              icon="fa fa-external-link-alt"
              icon-only
              @click.stop="$emit('view-item', { itemId: item.id, folderId: folder.id })"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state for expanded folder -->
    <div
      v-else-if="isExpanded"
      class="px-6 py-8 text-center bg-base-200/30 border-l-4 border-primary/20 ml-12"
    >
      <i class="fas fa-folder-open text-2xl text-secondary/50 mb-2"></i>
      <p class="text-sm text-secondary">
        {{ $t('folder.items.empty', 'No items in this folder') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import UiTag from '@/components/ui/Tag.vue'
import { Button } from '@owlint/feathers-vue'
import type { Folder } from '@/types/folder'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import { formatDate } from '@/utils/time'

const { t } = useI18n()

interface Props {
  folder: Folder
  globalView?: boolean
}

const props = defineProps<Props>()

const globalView = computed(() => props.globalView ?? false)


defineEmits<{
  'view-folder': [id: string]
  'delete-folder': [folder: Folder]
  'view-item': [payload: { itemId: string; folderId: string }]
}>()

// Folder permissions
const folderRef = toRef(props, 'folder')
const { canDeleteFolder, isSharedWithMe } = useFolderPermissions(folderRef)

// Compute share role label for display
const shareRoleLabel = computed(() => {
  if (!props.folder.share_role) return ''
  return props.folder.share_role === 'writer'
    ? t('folder.share.writer', 'Writer')
    : t('folder.share.reader', 'Reader')
})

const isExpanded = ref(false)

function toggleExpanded() {
  isExpanded.value = !isExpanded.value
}

// Helper to format item type
function formatItemType(type: string): string {
  if (type === 'company') {
    return t('folder.itemTypes.company')
  }
  return type.charAt(0).toUpperCase() + type.slice(1)
}

// Helper function to extract domain from website URL
function getCompanyDomain(website?: string) {
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
function getLogoUrl(website?: string) {
  const domain = getCompanyDomain(website)
  if (!domain) return ''
  return `https://img.logo.dev/${domain}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
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

// Compute owner initials for global view
const ownerInitials = computed(() => {
  if (!props.globalView) return ''
  const username = props.folder.owner_username || props.folder.owner || ''
  return username.substring(0, 2).toUpperCase()
})

// Check if folder has shares (for privacy tags in global view)
const hasShares = computed(() => {
  // If folder is not owned by current user but they have access, it's shared
  return !props.folder.is_owner && props.folder.share_role != null
})
</script>
