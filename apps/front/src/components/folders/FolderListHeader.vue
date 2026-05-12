<template>
  <PageHeader
    :title="t('common.folder.title')"
    :back-to="{ name: '/(home)' }"
    :back-label="t('common.action.backToHome')"
  >
    <template #info>
      <Dropdown v-if="canCreateAnything" align="left" width="md">
        <template #trigger>
          <Button variant="accent" icon="fa-plus" icon-right="fa-chevron-down">
            {{ t('common.folder.list.newButton') }}
          </Button>
        </template>
        <template #content>
          <DropdownItem v-if="canCreateCompany" @click="goTo('/companies/create')">
            <Icon icon="fa-building" class="text-xs" aria-hidden="true" />
            <span>{{ t('common.folder.list.newCompany') }}</span>
          </DropdownItem>
          <DropdownItem v-if="canCreateWatchFile" @click="goTo('/target/new')">
            <Icon icon="fa-binoculars" class="text-xs" aria-hidden="true" />
            <span>{{ t('common.folder.list.newWatchFile') }}</span>
          </DropdownItem>
        </template>
      </Dropdown>
    </template>

    <template #actions>
      <div class="gap-xl flex flex-wrap items-center">
        <Searchbar
          id="folder-search"
          v-model="filterName"
          :placeholder="t('common.folder.searchByName.placeholder')"
          size="sm"
          class="w-64"
        />

        <SortDropdown
          v-model:sort-by="sortBy"
          v-model:sort-order="sortOrder"
          :options="sortOptions"
        />

        <Toggle v-model="viewMode" :options="viewModeOptions" variant="pill" />
        <FilterButton v-model="filtersOpen" :count="store.activeFiltersCount" />
      </div>
    </template>
  </PageHeader>
</template>

<script setup lang="ts">
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SortDropdown, { type SortOption } from '@/components/ui/SortDropdown.vue'
import FilterButton from '@/components/ui/filters/FilterButton.vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useAuthStore } from '@/stores/auth'
import { useFoldersStore } from '@/stores/folders'
import { Button, Icon, Searchbar, Toggle } from '@owlint/feathers-vue'
import { storeToRefs } from 'pinia'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const filtersOpen = defineModel<boolean>('filtersOpen', { default: false })
const viewMode = defineModel<'grid' | 'table'>('viewMode', { required: true })

const { t } = useI18n()
const router = useRouter()
const store = useFoldersStore()
const authStore = useAuthStore()
const { filterName, sortBy, sortOrder } = storeToRefs(store)
const { canCreateCompany } = useCompanyPermissions()

const canCreateWatchFile = computed(() => authStore.hasPermission('target.create'))
const canCreateAnything = computed(() => canCreateCompany.value || canCreateWatchFile.value)

const sortOptions = computed<SortOption[]>(() => [
  { value: 'name', label: t('common.sort.byName') },
  { value: 'created_at', label: t('common.sort.byCreatedAt') },
  { value: 'updated_at', label: t('common.sort.byUpdatedAt') },
])

const viewModeOptions = computed(() => [
  { value: 'grid', label: t('common.folder.viewMode.grid'), icon: 'fa fa-th-large' },
  { value: 'table', label: t('common.folder.viewMode.table'), icon: 'fa fa-list' },
])

const goTo = (path: string) => {
  router.push(path)
}
</script>
