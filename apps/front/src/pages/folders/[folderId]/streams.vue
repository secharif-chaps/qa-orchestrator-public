<template>
  <RouterView
    :folder="folder"
    :search-term="searchTerm"
    :view-mode="viewMode"
    :company-filter="companyFilter"
    :refetch="refetch"
  />
</template>

<route lang="yaml">
meta:
  permissions:
    - stream.read
</route>

<script setup lang="ts">
import type { Folder } from '@/types/folder'

// Pass-through layout for stream routes.
// Props from the parent [folderId].vue layout are explicitly forwarded.
interface Props {
  folder?: Folder
  searchTerm?: string
  viewMode?: 'table' | 'grid'
  companyFilter?: 'all' | 'archived'
  refetch?: () => void
}

defineProps<Props>()
</script>
