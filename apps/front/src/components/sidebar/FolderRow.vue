<template>
  <div class="dark:bg-sage-800 border-sage-300 rounded-card ml-1 border bg-white">
    <!-- Folder Header -->
    <div
      @click.stop="$emit('toggle')"
      class="group text-sage-900 dark:bg-sage-800 rounded-card relative z-10 flex cursor-pointer items-center justify-between gap-2 bg-white px-2 py-3 transition-colors"
    >
      <div class="flex min-w-0 flex-1 items-center gap-2">
        <!-- Expand/Collapse Arrow -->
        <button
          v-if="folder.items && folder.items.length > 0"
          class="flex w-3 shrink-0 items-center justify-center"
        >
          <Icon
            :icon="isExpanded ? 'fa-chevron-down' : 'fa-chevron-right'"
            class="dark:text-sage-300 text-xs transition-transform"
          />
        </button>
        <div v-else class="w-3 shrink-0"></div>

        <!-- Folder Icon -->
        <Icon
          class="dark:text-sage-300 shrink-0 text-sm"
          :icon="isExpanded ? 'fa-folder' : 'fa-folder-open'"
        />

        <!-- Folder Name -->
        <span
          class="truncate text-sm hover:underline"
          @click.prevent="$emit('navigateFolder', folder.id)"
          >{{ folder.name }}</span
        >
      </div>

      <!-- Add Company Button (visible only for owners or writers) -->
      <Button
        v-if="canAddCompany"
        variant="tertiary"
        icon="fa-plus-circle"
        size="sm"
        @click.stop="$emit('addCompany', folder.id)"
        :title="$t('sidebar.chapse.addCompany')"
      >
      </Button>
    </div>

    <!-- Folder Items (Companies) -->
    <div
      v-if="isExpanded && folder.items && folder.items.length > 0"
      class="relative ml-3 pb-2 pl-3"
    >
      <!-- <div class="absolute w-0.5 bg-sage-300 h-[calc(100%-30px)] top-0 -left-0.5"></div> -->
      <div class="relative mr-3" v-for="item in visibleItems" :key="item.id">
        <div
          class="rounded-bl-card border-sage-300 absolute -top-8 bottom-0 -left-[10px] h-12 w-3 border-b-2 border-l-2"
        ></div>
        <div
          class="hover:bg-sage-300/80 dark:hover:bg-sage-800/50 group ml-1 flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1.5 transition-colors"
          @click.prevent="$emit('navigateCompany', folder.id, item.id)"
        >
          <!-- Item Icon -->
          <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-orange-200">
            <Icon icon="fa-file-lines" class="text-xs text-black" />
          </div>

          <!-- Item Name -->
          <span
            class="text-sage-900 dark:text-sage-300 flex-1 truncate text-xs group-hover:underline"
            >{{ item.name }}</span
          >
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Folder } from '@/types/folder'
import { Button, Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'

const props = defineProps<{
  folder: Folder
  isExpanded: boolean
  searchTerm: string
}>()

defineEmits<{
  toggle: []
  navigateFolder: [folderId: string]
  navigateCompany: [folderId: string, companyId: string]
  addCompany: [folderId: string]
}>()

// Check if user can add companies to this folder (owner or writer)
const canAddCompany = computed(() => {
  return props.folder.is_owner || props.folder.share_role === 'writer'
})

// Filter items based on search
const visibleItems = computed(() => {
  if (!props.folder.items) return []

  // If searching, only show matching items
  if (props.searchTerm.trim()) {
    const query = props.searchTerm.toLowerCase()
    return props.folder.items.filter((item) => item.name.toLowerCase().includes(query))
  }

  return props.folder.items
})
</script>
