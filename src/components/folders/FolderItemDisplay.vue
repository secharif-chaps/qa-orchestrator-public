<template>
  <!-- Card View -->
  <div
    v-if="mode === 'grid'"
    class="bg-bg1 rounded-lg p-4 border border-border-2 hover:ring-4 hover:ring-primary/70 ring-offset-2 ring-offset-bg3 transition-all duration-200 cursor-pointer group"
    @click="handleClick"
  >
    <div class="flex items-start justify-between mb-4">
      <div class="flex items-center gap-3">
        <div
          class="w-12 h-12 rounded-lg bg-white ring-1 ring-border-2 overflow-hidden flex items-center justify-center"
        >
          <img
            v-if="item.type === 'company' && getCompanyDomain(item.website)"
            :src="getLogoUrl(item.website)"
            :alt="`${item.name} logo`"
            class="w-full h-full object-contain p-1"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <div
            v-show="showFallbackIcon || !getCompanyDomain(item.website) || item.type !== 'company'"
            class="w-full h-full flex items-center justify-center bg-primary/10 dark:bg-primary/20"
          >
            <i class="fas fa-building text-primary text-xl"></i>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="text-lg font-semibold group-hover:text-primary transition-colors truncate">
            {{ item.name }}
          </h3>
          <p class="text-sm text-secondary truncate">
            {{ formatType(item.type) }}
          </p>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="flex gap-1 opacity-100 group-hover:opacity-100 transition-opacity">
        <Button
          variant="tertiary"
          icon="fa fa-eye"
          icon-only
          :title="$t('folder.item.actions.view', 'View Item')"
          @click.stop="handleClick"
        />
        <Button
          variant="tertiary"
          color="danger"
          icon="fa fa-trash"
          icon-only
          :title="$t('folder.item.actions.remove', 'Remove from folder')"
          @click.stop="$emit('removeItem', item)"
        />
      </div>
    </div>

    <div class="flex items-center justify-between text-sm text-secondary">
      <span>{{ $t('folder.item.created', 'Created') }} {{ formatDate(item.created_at) }}</span>
      <span v-if="item.owner_username">{{ item.owner_username }}</span>
    </div>
  </div>

  <!-- Table Row (for table mode) -->
  <div v-else class="contents">
    <!-- This is handled by the parent table structure -->
    <slot />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import type { FolderItem } from '@/types/folder'
import { useI18n } from 'vue-i18n'

interface Props {
  item: FolderItem
  mode: 'grid' | 'table'
}

const props = defineProps<Props>()

const emit = defineEmits<{
  viewItem: [id: string]
  removeItem: [item: FolderItem]
}>()

const { t } = useI18n()
const showFallbackIcon = ref(false)

const handleClick = () => {
  if (props.item.type === 'company') {
    emit('viewItem', props.item.item_id)
  }
}

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

const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const formatType = (type: string) => {
  return type.charAt(0).toUpperCase() + type.slice(1)
}
</script>