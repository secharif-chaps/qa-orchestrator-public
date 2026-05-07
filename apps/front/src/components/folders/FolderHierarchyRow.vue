<template>
  <div>
    <!-- Folder Row -->
    <div
      class="hover:bg-primary-lightest cursor-pointer px-6 py-4 transition-colors"
      @click="toggleExpanded"
    >
      <div
        :class="
          globalView
            ? 'grid grid-cols-14 items-center gap-4'
            : 'grid grid-cols-12 items-center gap-4'
        "
      >
        <!-- Name with expand/collapse icon -->
        <div class="col-span-5 flex items-center gap-3">
          <Button
            variant="tertiary"
            size="xs"
            :icon="isExpanded ? 'fa-chevron-down' : 'fa-chevron-right'"
            :title="
              isExpanded ? $t('common.folder.actions.collapse') : $t('common.folder.actions.expand')
            "
            @click.stop="toggleExpanded"
          />

          <div
            class="border-primary-lighter-stroke flex h-10 w-10 items-center justify-center rounded-sm border"
            :class="folderColorClasses"
          >
            <i :class="folderIcon" class="text-lg"></i>
          </div>

          <div class="flex flex-1 items-center gap-2">
            <h3 class="font-medium">{{ folder.name }}</h3>
            <!-- Privacy Tags (Global View Only) -->
            <template v-if="globalView">
              <Tag
                v-if="folder.is_owner && !hasShares"
                intent="neutral"
                :label="$t('common.folder.privacy.private')"
                size="xs"
                class="rounded-full"
              />
              <Tag
                v-else-if="hasShares"
                intent="info"
                icon="fa fa-share-nodes"
                :label="$t('common.folder.privacy.shared')"
                size="xs"
                class="rounded-full"
              />
            </template>
            <!-- Shared badge (non-global view) -->
            <Tag
              v-else-if="isSharedWithMe"
              :label="$t('common.folder.shared.badge')"
              intent="info"
              size="xs"
            />
            <!-- Role badge -->
            <Tag
              v-if="!globalView && isSharedWithMe && shareRoleLabel"
              :label="shareRoleLabel"
              intent="neutral"
              size="xs"
            />
          </div>
        </div>

        <!-- Owner Column (Global View Only) -->
        <div v-if="globalView" class="col-span-2 flex items-center gap-2">
          <AvatarInitials
            :name="folder.owner_username || folder.owner"
            size="xs"
            :variant="folder.is_owner ? 'primary' : 'secondary'"
          />
          <span class="text-neutral-black-font text-sm">
            {{ folder.is_owner ? $t('common.folder.owner.you') : folder.owner_username }}
          </span>
        </div>

        <!-- Items count -->
        <div class="col-span-2">
          <Tag
            intent="neutral"
            :label="$t('common.folder.itemsChip', folder.items?.length || 0)"
            size="sm"
          />
        </div>

        <!-- Created date -->
        <div class="col-span-1">
          <span class="text-neutral-black-font text-sm">{{ formatDate(folder.created_at) }}</span>
        </div>

        <!-- Actions -->
        <div class="col-span-2 text-right">
          <div class="flex items-center justify-end gap-2">
            <Button
              variant="tertiary"
              size="sm"
              icon="fa-external-link-alt"
              :label="$t('common.folder.actions.view')"
              @click.stop="$emit('view-folder', folder.id)"
            />
            <!-- Delete button only for owners -->
            <Button
              v-if="canDeleteFolder"
              variant="tertiary"
              size="sm"
              icon="fa-trash"
              @click.stop="$emit('delete-folder', folder)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Expanded Items -->
    <div
      v-if="isExpanded && folder.items && folder.items.length > 0"
      class="bg-primary-lightest/30"
    >
      <div
        v-for="item in folder.items"
        :key="item.id"
        class="hover:bg-primary-lightest/50 border-primary/20 ml-12 cursor-pointer border-l-4 px-6 py-3 transition-colors"
        @click="$emit('view-item', { itemId: item.id, folderId: folder.id })"
      >
        <div class="grid grid-cols-12 items-center gap-4">
          <!-- Item name with indentation -->
          <div class="col-span-5 flex items-center gap-3 pl-8">
            <div
              class="ring-primary-stroke flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-sm bg-white ring-1"
            >
              <Logo
                v-if="item.type === 'company'"
                :website="item.website"
                :name="item.name"
                :alt="item.name"
                :width="32"
                :height="32"
              />
              <div
                v-else
                class="bg-primary/10 dark:bg-primary/20 flex h-full w-full items-center justify-center"
              >
                <i class="fas fa-building text-neutral-black-font text-sm"></i>
              </div>
            </div>
            <div class="flex-1">
              <h4 class="text-sm font-medium">{{ item.name }}</h4>
            </div>
          </div>

          <!-- Item type -->
          <div class="col-span-2">
            <Tag intent="accent" :label="formatItemType(item.type)" size="xs" />
          </div>

          <!-- Item owner -->
          <div class="col-span-2">
            <span class="text-neutral-black-font text-xs">{{ item.owner || '' }}</span>
          </div>

          <!-- Item created date -->
          <div class="col-span-1">
            <span class="text-neutral-black-font text-xs">{{ formatDate(item.created_at) }}</span>
          </div>

          <!-- Item actions -->
          <div class="col-span-2 text-right">
            <Button
              variant="tertiary"
              size="sm"
              icon="fa-external-link-alt"
              @click.stop="$emit('view-item', { itemId: item.id, folderId: folder.id })"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state for expanded folder -->
    <div
      v-else-if="isExpanded"
      class="bg-primary-lightest/30 border-primary/20 ml-12 border-l-4 px-6 py-8 text-center"
    >
      <i class="fas fa-folder-open text-neutral-black-font/50 mb-2 text-2xl"></i>
      <p class="text-neutral-black-font text-sm">
        {{ $t('common.folder.items.empty') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import AvatarInitials from '@/components/ui/AvatarInitials.vue'
import Logo from '@/components/ui/Logo.vue'
import { useDateTime } from '@/composables/useDateTime'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import type { Folder } from '@/types/folder'
import { Button, Tag } from '@owlint/feathers-vue'
import { computed, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { formatDate } = useDateTime()

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
    ? t('common.folder.share.writer')
    : t('common.folder.share.reader')
})

const isExpanded = ref(false)

function toggleExpanded() {
  isExpanded.value = !isExpanded.value
}

// Helper to format item type
function formatItemType(type: string): string {
  if (type === 'company') {
    return t('common.folder.itemTypes.company')
  }
  return type.charAt(0).toUpperCase() + type.slice(1)
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

// Check if folder has shares (for privacy tags in global view)
const hasShares = computed(() => {
  // If folder is not owned by current user but they have access, it's shared
  return !props.folder.is_owner && props.folder.share_role != null
})
</script>
