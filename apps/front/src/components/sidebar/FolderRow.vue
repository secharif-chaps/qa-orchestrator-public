<template>
  <div class="bg-sage-800 rounded-card ml-1">
    <!-- Folder Header -->
    <div
      @click.stop="$emit('toggle')"
      class="group bg-sage-800 rounded-card relative z-10 flex cursor-pointer items-center justify-between gap-2 px-2 py-3 transition-colors"
    >
      <div class="flex min-w-0 flex-1 items-center gap-2">
        <!-- Expand/Collapse Arrow -->
        <button
          v-if="folder.items && folder.items.length > 0"
          class="flex w-3 flex-shrink-0 items-center justify-center"
        >
          <i
            :class="isExpanded ? 'fa-chevron-down' : 'fa-chevron-right'"
            class="fa text-sage-300 text-xs transition-transform"
          ></i>
        </button>
        <div v-else class="w-3 flex-shrink-0"></div>

        <!-- Folder Icon -->
        <i
          class="fa text-sage-300 flex-shrink-0 text-sm"
          :class="{ 'fa-folder': !isExpanded, 'fa-folder-open': isExpanded }"
        ></i>

        <!-- Folder Name -->
        <span
          class="truncate text-sm text-white hover:underline"
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
      class="relative ml-3 pb-2 pl-3"
    >
      <!-- <div class="absolute w-0.5 bg-sage-300 h-[calc(100%-30px)] top-0 -left-0.5"></div> -->
      <div class="relative" v-for="item in visibleItems" :key="item.id">
        <div
          class="rounded-bl-card border-sage-300 absolute -top-8 bottom-0 -left-[10px] h-12 w-3 border-b-2 border-l-2"
        ></div>
        <div
          class="hover:bg-sage-800/50 group ml-1 flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1.5 transition-colors"
          @click.prevent="$emit('navigateCompany', folder.id, item.id)"
        >
          <!-- Item Icon -->
          <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded bg-orange-200">
            <i class="fa fa-file-lines text-xs text-black"></i>
          </div>

          <!-- Item Name -->
          <span class="text-sage-300 flex-1 truncate text-xs group-hover:underline">{{
            item.name
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Folder } from '@/types/folder'
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
