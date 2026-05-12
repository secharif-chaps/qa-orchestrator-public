<template>
  <div ref="wrapper" class="flex w-full items-center justify-end">
    <!--
      Hidden measurement container — never visible, used only to measure the rendered
      width of each tab + the overflow button so `visibleCount` can decide how many
      tabs fit in the available space without re-measuring on every change.
      The `99` is a worst-case 2-digit placeholder for the overflow count: it reserves
      enough width to fit any realistic value (1–99) without re-measuring when the
      count changes.
    -->
    <div
      ref="measure"
      class="pointer-events-none invisible absolute h-0 overflow-hidden"
      aria-hidden="true"
    >
      <div class="flex gap-1 p-1">
        <span
          v-for="tab in orderedTabs"
          :key="`m-${tab.id}`"
          :data-tab-id="tab.id"
          class="shrink-0 rounded-lg px-2 py-2 text-sm whitespace-nowrap"
        >
          {{ tabLabelMap[tab.id] }}
        </span>
        <span
          data-tab-id="__overflow"
          class="flex shrink-0 items-center gap-1 rounded-lg px-2 py-2 text-sm whitespace-nowrap"
        >
          {{ OVERFLOW_MEASURE_PLACEHOLDER }}
          <Icon icon="fa-chevron-right" class="text-xs" aria-hidden="true" />
        </span>
      </div>
    </div>

    <!-- Tab bar -->
    <nav class="bg-sage-50 border-sage-200 inline-flex items-center gap-1 rounded-md border p-1">
      <TabItem v-for="tab in visibleNavigationTabs" :key="tab.id" :tab="tab" variant="primary" />

      <!-- Overflow menu -->
      <Dropdown v-if="overflowNavigationTabs.length > 0" align="right" width="auto">
        <template #trigger>
          <button
            type="button"
            class="flex shrink-0 cursor-pointer items-center gap-1 rounded-md px-2 py-2 text-sm whitespace-nowrap transition-colors"
            :class="
              hasActiveOverflowTab ? 'bg-sage-800 text-white' : 'text-sage-800 hover:bg-sage-100'
            "
          >
            {{ overflowNavigationTabs.length }}
            <Icon icon="fa-chevron-right" class="text-xs" aria-hidden="true" />
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

export interface PageHeaderTab {
  id: string
  routeName: string
  /** i18n key — translated internally with t() */
  label: string
  /** Optional numeric badge displayed next to the title */
  badge?: number
  /** When true, the tab keeps its original position even with popularity sorting */
  pinned?: boolean
}

interface Props {
  tabs: PageHeaderTab[]
  routeParams?: Record<string, string | number>
  /** Max width as a percentage of the wrapper (0–100). Defaults to 70. */
  maxWidthPercent?: number
  /** localStorage key used to persist visit counts. If omitted, tabs are not reordered by popularity. */
  visitsStorageKey?: string
}

const { tabs, routeParams, maxWidthPercent = 70, visitsStorageKey } = defineProps<Props>()

// Two-digit placeholder used only to measure the max width of the overflow badge
// in the hidden measurement row. Avoids re-measuring when the count changes (1–99).
const OVERFLOW_MEASURE_PLACEHOLDER = '99'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const tabLabelMap = computed<Record<string, string>>(() =>
  Object.fromEntries(tabs.map((tab) => [tab.id, t(tab.label)])),
)

// ── Visit tracking (localStorage) ──────────────────────────────────
const loadVisitCounts = (): Record<string, number> => {
  if (!visitsStorageKey) return {}
  try {
    const stored = localStorage.getItem(visitsStorageKey)
    if (!stored) return {}
    const parsed = JSON.parse(stored)
    return typeof parsed === 'object' && parsed !== null ? parsed : {}
  } catch {
    return {}
  }
}

const saveVisitCounts = (counts: Record<string, number>) => {
  if (!visitsStorageKey) return
  try {
    localStorage.setItem(visitsStorageKey, JSON.stringify(counts))
  } catch {
    // Fail silently — default order used as fallback
  }
}

const visitCounts = ref<Record<string, number>>(loadVisitCounts())

const recordVisit = (tabId: string) => {
  if (!visitsStorageKey) return
  const tab = tabs.find((t) => t.id === tabId)
  // Pinned tabs aren't reordered, so don't waste storage on them
  if (!tab || tab.pinned) return
  const counts = { ...visitCounts.value }
  counts[tabId] = (counts[tabId] ?? 0) + 1
  visitCounts.value = counts
  saveVisitCounts(counts)
}

// ── Ordered tabs: pinned keep their positions, rest sorted by popularity ──
const orderedTabs = computed(() => {
  if (!visitsStorageKey) return tabs

  const pinned = tabs.filter((tab) => tab.pinned)
  const rest = tabs.filter((tab) => !tab.pinned)

  const sorted = [...rest].sort((a, b) => {
    const countA = visitCounts.value[a.id] ?? 0
    const countB = visitCounts.value[b.id] ?? 0
    if (countB !== countA) return countB - countA
    return rest.indexOf(a) - rest.indexOf(b)
  })

  return [...pinned, ...sorted]
})

// ── Active tab detection ────────────────────────────────────────────
const activeTabId = computed(() => {
  const name = String(route.name ?? '')
  return tabs.find((tab) => tab.routeName === name)?.id ?? null
})

// ── NavigationTab conversion ────────────────────────────────────────
const toNavigationTab = (tab: PageHeaderTab): NavigationTab => ({
  id: tab.id,
  title: tabLabelMap.value[tab.id] ?? tab.label,
  isActive: tab.id === activeTabId.value,
  badge: tab.badge,
  click: () => {
    if (tab.id === activeTabId.value) return
    recordVisit(tab.id)
    router.push({
      name: tab.routeName as never,
      params: routeParams,
    })
  },
})

// ── Overflow calculation ────────────────────────────────────────────
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
  const ordered = orderedTabs.value
  const total = ordered.length
  if (wrapperWidth.value === 0 || tabWidths.value.size === 0) return total

  const gap = 4 // gap-1
  const padding = 8 // p-1 = 4px each side
  const border = 2 // 1px border each side
  const effectiveWidth = wrapperWidth.value * (maxWidthPercent / 100)
  let budget = effectiveWidth - padding - border
  let count = 0

  for (let i = 0; i < total; i++) {
    const tab = ordered[i]
    const w = tabWidths.value.get(tab.id) ?? 80
    const remaining = total - i - 1

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
  orderedTabs.value.slice(0, visibleCount.value).map(toNavigationTab),
)
const overflowNavigationTabs = computed<NavigationTab[]>(() =>
  orderedTabs.value.slice(visibleCount.value).map(toNavigationTab),
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
  () => orderedTabs.value.map((tab) => tabLabelMap.value[tab.id] ?? tab.id).join(','),
  async () => {
    await nextTick()
    measureAllTabs()
  },
)
</script>
