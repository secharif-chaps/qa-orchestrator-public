<template>
  <div class="relative" ref="containerRef">
    <!-- Trigger Button -->
    <button
      type="button"
      class="bg-sage-700 text-sage-200 hover:bg-sage-600 inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-all disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="disabled"
      @click="toggleDropdown"
    >
      <i class="fa fa-plus text-xs"></i>
      <span>{{ $t('sidebar.chapse.addCompany', 'Add company') }}</span>
      <span v-if="showLimit" class="text-sage-400">({{ contextCount }}/{{ maxCompanies }})</span>
    </button>

    <!-- Dropdown -->
    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        class="bg-sage-800 border-sage-700 absolute z-[9999] mt-1 w-[145px] overflow-hidden rounded-xl border shadow-lg"
        :class="dropdownPosition"
      >
        <!-- Search Input -->
        <div class="border-sage-700 border-b p-2">
          <div class="relative">
            <i
              class="fa fa-search text-sage-400 absolute top-1/2 left-2 -translate-y-1/2 text-[10px]"
            ></i>
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              :placeholder="$t('sidebar.chapse.searchCompanies', 'Search companies...')"
              class="bg-sage-900 text-sage-100 placeholder-sage-500 focus:ring-primary/50 w-full rounded-lg py-1.5 pr-2 pl-7 text-xs focus:ring-2 focus:outline-none"
              @input="handleSearch"
            />
          </div>
        </div>

        <!-- Results -->
        <div class="max-h-60 overflow-y-auto">
          <!-- Loading -->
          <div v-if="isSearching" class="flex items-center justify-center py-6">
            <i class="fa fa-spinner fa-spin text-sage-400"></i>
          </div>

          <!-- Empty State -->
          <div
            v-else-if="searchQuery && filteredCompanies.length === 0"
            class="px-2 py-4 text-center"
          >
            <p class="text-sage-400 text-xs">
              {{ $t('sidebar.chapse.noCompaniesFound', 'No companies found') }}
            </p>
          </div>

          <!-- Initial State -->
          <div
            v-else-if="!searchQuery && filteredCompanies.length === 0"
            class="px-2 py-4 text-center"
          >
            <p class="text-sage-400 text-xs">
              {{ $t('sidebar.chapse.typeToSearch', 'Type to search companies') }}
            </p>
          </div>

          <!-- Results List -->
          <div v-else class="py-1">
            <button
              v-for="company in filteredCompanies"
              :key="company.id ?? 0"
              type="button"
              class="hover:bg-sage-700 flex w-full items-center gap-2 px-2 py-1.5 text-left transition-colors"
              :class="{
                'cursor-not-allowed opacity-50':
                  company.id !== undefined && isCompanyInContext(company.id),
              }"
              :disabled="company.id !== undefined && isCompanyInContext(company.id)"
              @click="selectCompany(company)"
            >
              <!-- Company Icon -->
              <div
                class="bg-sage-600 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full"
              >
                <i class="fa fa-building text-sage-300 text-[10px]"></i>
              </div>

              <!-- Company Info -->
              <div class="min-w-0 flex-1">
                <p class="text-sage-100 truncate text-xs font-medium">
                  {{ company.name }}
                </p>
              </div>

              <!-- Already Added Indicator -->
              <i
                v-if="company.id !== undefined && isCompanyInContext(company.id)"
                class="fa fa-check text-primary text-xs"
              ></i>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { getCompanies } from '@/api/companies'
import type { Company } from '@/types/company'
import type { CompanyContext } from '@/stores/chapse'

interface Props {
  /** Current company context IDs */
  contextCompanyIds?: number[]
  /** Maximum companies allowed */
  maxCompanies?: number
  /** Current context count */
  contextCount?: number
  /** Show the limit indicator */
  showLimit?: boolean
  /** Disabled state */
  disabled?: boolean
  /** Dropdown position */
  position?: 'bottom-left' | 'bottom-right' | 'top-left' | 'top-right'
}

const props = withDefaults(defineProps<Props>(), {
  contextCompanyIds: () => [],
  maxCompanies: 3,
  contextCount: 0,
  showLimit: true,
  disabled: false,
  position: 'bottom-left',
})

const emit = defineEmits<{
  select: [company: CompanyContext]
}>()

// Refs
const containerRef = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const isOpen = ref(false)
const searchQuery = ref('')
const isSearching = ref(false)
const companies = ref<Company[]>([])
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

// Computed
const dropdownPosition = computed(() => {
  switch (props.position) {
    case 'bottom-right':
      return 'right-0'
    case 'top-left':
      return 'bottom-full mb-1 right-0'
    case 'top-right':
      return 'bottom-full mb-1 right-0'
    default:
      return 'left-0'
  }
})

const filteredCompanies = computed(() => {
  return companies.value
})

// Methods
function toggleDropdown() {
  if (props.disabled) return
  isOpen.value = !isOpen.value

  if (isOpen.value) {
    nextTick(() => {
      searchInputRef.value?.focus()
    })
  } else {
    resetSearch()
  }
}

function closeDropdown() {
  isOpen.value = false
  resetSearch()
}

function resetSearch() {
  searchQuery.value = ''
  companies.value = []
}

function isCompanyInContext(companyId: number): boolean {
  return props.contextCompanyIds.includes(companyId)
}

function handleSearch() {
  // Clear previous timer
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }

  if (!searchQuery.value.trim()) {
    companies.value = []
    isSearching.value = false
    return
  }

  // Show loader immediately while debouncing
  isSearching.value = true

  // Debounce the search by 300ms
  searchDebounceTimer = setTimeout(async () => {
    try {
      const response = await getCompanies({
        page: 1,
        size: 10,
        name: searchQuery.value.trim(),
      })
      companies.value = response.data ?? []
    } catch (error) {
      console.error('Error searching companies:', error)
      companies.value = []
    } finally {
      isSearching.value = false
    }
  }, 300)
}

function selectCompany(company: Company) {
  if (company.id === undefined) return
  if (isCompanyInContext(company.id)) return

  const context: CompanyContext = {
    id: company.id,
    name: company.name,
    siren: null, // siren is not available on Company type
  }

  emit('select', context)
  closeDropdown()
}

// Click outside handler
function handleClickOutside(event: MouseEvent) {
  if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
    closeDropdown()
  }
}

// Lifecycle
onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }
})
</script>
