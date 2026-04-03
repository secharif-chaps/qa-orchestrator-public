<template>
  <!--
    Root wrapper receives `class="min-w-0 flex-1"` from parent.
    flex-1 makes its width stable (set by flex layout, not content) → no oscillation.
  -->
  <div ref="wrapper" class="flex items-center justify-end">
    <!-- Hidden measurement container (same font/padding as real tabs) -->
    <div
      ref="measure"
      class="pointer-events-none invisible absolute h-0 overflow-hidden"
      aria-hidden="true"
    >
      <div class="flex gap-1 p-1">
        <span
          v-for="tab in allTabsOrdered"
          :key="`m-${tab.id}`"
          :data-tab-id="tab.id"
          class="shrink-0 rounded-lg px-2 py-2 text-sm whitespace-nowrap"
        >
          {{ t(tab.label) }}
        </span>
        <span
          data-tab-id="__overflow"
          class="flex shrink-0 items-center gap-1 rounded-lg px-2 py-2 text-sm whitespace-nowrap"
        >
          <Icon icon="fa-plus" class="text-xs" aria-hidden="true" />
          {{ t('screen.company.tabs.more', { count: 99 }) }}
        </span>
      </div>
    </div>

    <!-- Tab bar -->
    <nav class="bg-sage-50 border-sage-200 inline-flex items-center gap-1 rounded-xl border p-1">
      <TabItem v-for="tab in visibleNavigationTabs" :key="tab.id" :tab="tab" variant="primary" />

      <!-- Overflow menu -->
      <Dropdown v-if="overflowNavigationTabs.length > 0" align="right" width="auto">
        <template #trigger>
          <button
            type="button"
            class="flex shrink-0 cursor-pointer items-center gap-1 rounded-lg px-2 py-2 text-sm whitespace-nowrap transition-colors"
            :class="
              hasActiveOverflowTab ? 'bg-sage-800 text-white' : 'text-sage-800 hover:bg-sage-100'
            "
          >
            <Icon icon="fa-plus" class="text-xs" aria-hidden="true" />
            {{ t('screen.company.tabs.more', { count: overflowNavigationTabs.length }) }}
          </button>
        </template>
        <template #content>
          <DropdownItem v-for="tab in overflowNavigationTabs" :key="tab.id" @click="tab.click()">
            {{ tab.title }}
          </DropdownItem>
        </template>
      </Dropdown>
    </nav>
  </div>
</template>

<script lang="ts" setup>
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import { Icon, TabItem, type NavigationTab } from '@owlint/feathers-vue'
import { computed, nextTick, onMounted, onUnmounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

interface Props {
  folderId: string
  companyId: string
  /** Max width as a percentage of the wrapper (0–100). Defaults to 70. */
  maxWidthPercent?: number
}

const { folderId, companyId, maxWidthPercent = 70 } = defineProps<Props>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

// ── Tab definitions ─────────────────────────────────────────────────
interface TabDefinition {
  id: string
  routeName: string
  label: string
}

const DEFAULT_TABS: TabDefinition[] = [
  {
    id: 'profile',
    routeName: '/folders/[folderId]/companies/[companyId]/',
    label: 'screen.company.tabs.profile',
  },
  {
    id: 'timeline',
    routeName: '/folders/[folderId]/companies/[companyId]/timeline',
    label: 'screen.company.tabs.timeline',
  },
  {
    id: 'products',
    routeName: '/folders/[folderId]/companies/[companyId]/products',
    label: 'screen.company.tabs.products',
  },
  {
    id: 'team',
    routeName: '/folders/[folderId]/companies/[companyId]/team',
    label: 'screen.company.tabs.team',
  },
  {
    id: 'jobs',
    routeName: '/folders/[folderId]/companies/[companyId]/jobs',
    label: 'screen.company.tabs.jobs',
  },
  {
    id: 'press',
    routeName: '/folders/[folderId]/companies/[companyId]/press',
    label: 'screen.company.tabs.press',
  },
  {
    id: 'csr',
    routeName: '/folders/[folderId]/companies/[companyId]/csr',
    label: 'screen.company.tabs.csr',
  },
]

// ── Visit tracking (localStorage) ──────────────────────────────────
const VISITS_KEY = 'chapsmind:company-tab-visits'

const visitCounts = ref<Record<string, number>>(loadVisitCounts())

function loadVisitCounts(): Record<string, number> {
  try {
    const stored = localStorage.getItem(VISITS_KEY)
    if (!stored) return {}
    const parsed = JSON.parse(stored)
    return typeof parsed === 'object' && parsed !== null ? parsed : {}
  } catch {
    return {}
  }
}

function saveVisitCounts(counts: Record<string, number>) {
  try {
    localStorage.setItem(VISITS_KEY, JSON.stringify(counts))
  } catch {
    // Fail silently — default order used as fallback
  }
}

const recordVisit = (tabId: string) => {
  // Profile is always pinned first — no need to track
  if (tabId === 'profile') return
  const counts = { ...visitCounts.value }
  counts[tabId] = (counts[tabId] ?? 0) + 1
  visitCounts.value = counts
  saveVisitCounts(counts)
}

// ── Ordered tabs: profile pinned first, rest sorted by popularity ──
const allTabsOrdered = computed(() => {
  const profile = DEFAULT_TABS[0]
  const rest = DEFAULT_TABS.slice(1)

  const sorted = [...rest].sort((a, b) => {
    const countA = visitCounts.value[a.id] ?? 0
    const countB = visitCounts.value[b.id] ?? 0
    if (countB !== countA) return countB - countA
    // Stable: keep default order for equal counts
    return rest.indexOf(a) - rest.indexOf(b)
  })

  return [profile, ...sorted]
})

// ── Active tab detection ────────────────────────────────────────────
const activeTabId = computed(() => {
  const name = String(route.name ?? '')
  return DEFAULT_TABS.find((tab) => tab.routeName === name)?.id ?? null
})

// ── NavigationTab conversion ────────────────────────────────────────
const toNavigationTab = (tab: TabDefinition): NavigationTab => ({
  id: tab.id,
  title: t(tab.label),
  isActive: tab.id === activeTabId.value,
  click: () => {
    if (tab.id === activeTabId.value) return
    recordVisit(tab.id)
    router.push({
      name: tab.routeName as never,
      params: { folderId, companyId },
    })
  },
})

// ── Overflow calculation ────────────────────────────────────────────
// The wrapper is flex-1 from the parent → its width is set by the flex
// layout, NOT by its children. This gives us a stable measurement that
// won't oscillate when we add/remove tabs.
const wrapperRef = useTemplateRef('wrapper')
const measureRef = useTemplateRef('measure')
const wrapperWidth = ref(0)
const tabWidths = ref(new Map<string, number>())
const overflowBtnWidth = ref(0)

const measureAllTabs = () => {
  if (!measureRef.value) return
  const spans = measureRef.value.querySelectorAll<HTMLElement>('[data-tab-id]')
  const widths = new Map<string, number>()

  spans.forEach((el) => {
    const id = el.dataset.tabId!
    if (id === '__overflow') {
      overflowBtnWidth.value = el.offsetWidth
    } else {
      widths.set(id, el.offsetWidth)
    }
  })
  tabWidths.value = widths
}

const visibleCount = computed(() => {
  const tabs = allTabsOrdered.value
  const total = tabs.length
  if (wrapperWidth.value === 0 || tabWidths.value.size === 0) return total

  const gap = 4 // gap-1
  const padding = 8 // p-1 = 4px each side
  const border = 2 // 1px border each side
  const effectiveWidth = wrapperWidth.value * (maxWidthPercent / 100)
  let budget = effectiveWidth - padding - border
  let count = 0

  for (let i = 0; i < total; i++) {
    const tab = tabs[i]
    const w = tabWidths.value.get(tab.id) ?? 80
    const remaining = total - i - 1

    // Reserve space for the overflow button when tabs remain after this one
    const reservedForOverflow = remaining > 0 ? overflowBtnWidth.value + gap : 0
    const needed = w + (count > 0 ? gap : 0)

    if (count > 0 && budget - needed - reservedForOverflow < 0) break
    budget -= needed
    count++
  }

  if (count >= total) return total
  return Math.max(1, count)
})

const visibleNavigationTabs = computed<NavigationTab[]>(() =>
  allTabsOrdered.value.slice(0, visibleCount.value).map(toNavigationTab),
)
const overflowNavigationTabs = computed<NavigationTab[]>(() =>
  allTabsOrdered.value.slice(visibleCount.value).map(toNavigationTab),
)

const hasActiveOverflowTab = computed(() =>
  overflowNavigationTabs.value.some((tab) => tab.isActive),
)

// ── ResizeObserver on wrapper (stable flex-1 width) ─────────────────
let observer: ResizeObserver | null = null

onMounted(async () => {
  await nextTick()
  measureAllTabs()

  if (wrapperRef.value) {
    wrapperWidth.value = wrapperRef.value.offsetWidth
    observer = new ResizeObserver((entries) => {
      for (const entry of entries) {
        wrapperWidth.value = entry.contentRect.width
      }
    })
    observer.observe(wrapperRef.value)
  }
})

onUnmounted(() => {
  observer?.disconnect()
})

// Re-measure when language or tab order changes
watch(
  () => allTabsOrdered.value.map((tab) => t(tab.label)).join(','),
  async () => {
    await nextTick()
    measureAllTabs()
  },
)
</script>
