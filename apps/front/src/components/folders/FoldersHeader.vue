<template>
  <PageHeader
    :title="folder?.name || t('common.folder.loading')"
    :back-to="{ name: '/folders/(list)' }"
    :back-label="t('common.action.backToFolders')"
  >
    <template v-if="isSharedWithMe" #buttons>
      <Tag :label="t('common.folder.shared.badge')" intent="info" size="sm" />
      <Tag v-if="shareRoleLabel" :label="shareRoleLabel" variant="secondary" size="sm" />
    </template>

    <template #info>
      <Dropdown v-if="canCreateItems" align="left" width="xl">
        <template #trigger="{ isOpen }">
          <button
            type="button"
            class="bg-accent-200 text-accent-950 hover:bg-accent-100 flex h-9 cursor-pointer items-center gap-2 rounded-full px-4 text-sm font-medium transition-colors"
            aria-haspopup="menu"
            :aria-expanded="isOpen"
          >
            <Icon icon="fa-plus" aria-hidden="true" />
            <span>{{ t('common.folder.actions.new') }}</span>
            <Icon icon="fa-chevron-down" class="text-xs" aria-hidden="true" />
          </button>
        </template>

        <template #content>
          <DropdownItem
            v-if="isScreenEnabled"
            icon="fas fa-building"
            color="blue"
            :label="t('common.folder.addItems.companyScreen')"
            :description="t('common.folder.addItems.companyDescription')"
            @click="
              $router.push(
                `/folders/${($route.params as Record<string, string>).folderId}/create/company`,
              )
            "
          />

          <DropdownItem
            v-if="isStreamEnabled && canWriteStreams"
            icon="fas fa-paper-plane"
            color="purple"
            :label="t('common.folder.addItems.stream')"
            :description="t('common.folder.addItems.streamDescription')"
            @click="
              $router.push(
                `/folders/${($route.params as Record<string, string>).folderId}/streams/create`,
              )
            "
          />

          <DropdownItem
            disabled
            icon="fas fa-eye"
            color="green"
            :label="t('common.folder.addItems.watchfile')"
            :description="t('common.folder.addItems.watchfileDescription')"
          >
            <template #suffix>
              <Tag variant="secondary" size="xs" :label="t('common.soon')" />
            </template>
          </DropdownItem>

          <DropdownItem
            disabled
            icon="fas fa-project-diagram"
            color="purple"
            :label="t('common.folder.addItems.graphrag')"
            :description="t('common.folder.addItems.graphragDescription')"
          >
            <template #suffix>
              <Tag variant="secondary" size="xs" :label="t('common.soon')" />
            </template>
          </DropdownItem>
        </template>
      </Dropdown>
    </template>

    <template #actions>
      <div class="flex items-center gap-6">
        <div class="w-[340px] max-w-full">
          <Searchbar
            id="folder-search-input"
            v-model="searchTerm"
            :placeholder="t('common.folder.search.placeholder')"
          />
        </div>

        <Toggle v-model="viewMode" :options="viewModeOptions" variant="pill" />
      </div>
    </template>
  </PageHeader>
</template>

<script setup lang="ts">
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { useFolderPermissions } from '@/composables/useFolderPermissions'
import { useScreenModule } from '@/composables/useScreenModule'
import { useStreamModule } from '@/composables/useStreamModule'
import { useStreamPermissions } from '@/composables/useStreamPermissions'
import type { Folder } from '@/types/folder'
import { Icon, Searchbar, Tag, Toggle } from '@owlint/feathers-vue'
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  folder?: Folder | null
}

const props = defineProps<Props>()

const { t } = useI18n()

const folderRef = toRef(props, 'folder')
const { canCreateItems, isSharedWithMe } = useFolderPermissions(folderRef)

const { isScreenEnabled } = useScreenModule()
const { isStreamEnabled } = useStreamModule()
const { canWriteStreams } = useStreamPermissions()

const shareRoleLabel = computed(() => {
  if (!props.folder?.share_role) return ''
  return props.folder.share_role === 'writer'
    ? t('common.folder.share.writer')
    : t('common.folder.share.reader')
})

const searchTerm = defineModel<string>('searchTerm', { default: '' })
const viewMode = defineModel<'table' | 'grid'>('viewMode', { required: true })

const viewModeOptions = computed(() => [
  {
    value: 'grid',
    label: t('common.folder.viewMode.grid'),
    icon: 'fa fa-th-large',
  },
  {
    value: 'table',
    label: t('common.folder.viewMode.table'),
    icon: 'fa fa-list',
  },
])
</script>
