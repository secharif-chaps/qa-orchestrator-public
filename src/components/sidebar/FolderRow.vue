<template>
  <div class="ml-1 bg-sage-800 rounded-card">
    <!-- Folder Header -->
    <div
      @click.stop="$emit('toggle')"
      class="flex items-center gap-2 px-2 py-3 transition-colors group cursor-pointer bg-sage-800 relative z-10 rounded-card justify-between"
    >
      <div class="flex items-center gap-2 min-w-0 flex-1">
        <!-- Expand/Collapse Arrow -->
        <button
          v-if="folder.items && folder.items.length > 0"
          class="w-3 flex-shrink-0 flex items-center justify-center"
        >
          <i
            :class="isExpanded ? 'fa-chevron-down' : 'fa-chevron-right'"
            class="fa text-xs text-sage-300 transition-transform"
          ></i>
        </button>
        <div v-else class="w-3 flex-shrink-0"></div>

        <!-- Folder Icon -->
        <i
          class="fa text-sm text-sage-300 flex-shrink-0"
          :class="{ 'fa-folder': !isExpanded, 'fa-folder-open': isExpanded }"
        ></i>

        <!-- Folder Name -->
        <span
          class="text-sm text-white truncate hover:underline"
          @click.prevent="$emit('navigateFolder', folder.id)"
          >{{ folder.name }}</span
        >
      </div>

      <!-- Add Company Button (visible only for owners or writers) -->
      <Button
        v-if="canAddCompany"
        variant="tertiary"
        icon="fa fa-plus-circle"
        size="sm"
        @click.stop="$emit('addCompany', folder.id)"
        :title="$t('sidebar.chapse.addCompany')"
      >
      </Button>
    </div>

    <!-- Folder Items (Companies) -->
    <div
      v-if="isExpanded && folder.items && folder.items.length > 0"
      class="ml-3 pl-3 pb-2 relative"
    >
      <!-- <div class="absolute w-0.5 bg-sage-300 h-[calc(100%-30px)] top-0 -left-0.5"></div> -->
      <div class="relative" v-for="item in visibleItems" :key="item.id">
        <div
          class="absolute -left-[10px] -top-8 bottom-0 w-3 h-12 rounded-bl-card border-l-2 border-b-2 border-sage-300"
        ></div>
        <div
          class="flex ml-1 items-center gap-2 px-1.5 py-1.5 rounded-md hover:bg-sage-800/50 transition-colors cursor-pointer group"
          @click.prevent="$emit('navigateCompany', folder.id, item.id)"
        >
          <!-- Item Icon -->
          <div class="w-5 h-5 rounded bg-orange-200 flex items-center justify-center flex-shrink-0">
            <i class="fa fa-file-lines text-xs text-black"></i>
          </div>

          <!-- Item Name -->
          <span class="text-xs text-sage-300 flex-1 truncate group-hover:underline">{{
            item.name
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Folder, FolderItem } from '@/types/folder'
import { Button } from '@owlint/feathers-vue'

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
