<template>
  <div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">
          {{ $t('admin.tasks.title', 'Task Monitoring') }}
        </h1>
        <p class="text-secondary mt-1">
          {{ $t('admin.tasks.description', 'Monitor and manage tasks across all organizations') }}
        </p>
      </div>

      <!-- Auto-refresh toggle -->
      <div class="flex items-center gap-4">
        <span class="text-sm text-secondary">
          {{ lastRefreshText }}
        </span>
        <button
          @click="toggleAutoRefresh"
          :class="[
            'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
            autoRefreshEnabled
              ? 'bg-success-light text-success-light-content'
              : 'bg-base-200 text-secondary hover:bg-base-300',
          ]"
        >
          <i :class="['fa fa-sync-alt', { 'animate-spin': autoRefreshEnabled && isRefreshing }]"></i>
          {{ autoRefreshEnabled ? 'Auto-refresh ON' : 'Auto-refresh OFF' }}
        </button>
        <Button variant="secondary" icon="fa fa-refresh" label="Refresh" @click="refreshAll" :loading="isRefreshing" />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center py-16 gap-4">
      <i class="fa fa-spinner animate-spin text-4xl text-primary"></i>
      <p class="text-secondary">Loading task data...</p>
    </div>

    <!-- Error State -->
    <Alert
      v-else-if="statsError || tasksError"
      variant="error"
      title="Error Loading Data"
      :message="(statsError?.message || tasksError?.message) ?? 'Failed to load task data'"
      icon="fa fa-exclamation-triangle"
    >
      <template #actions>
        <Button variant="secondary" size="sm" icon="fa fa-refresh" label="Retry" @click="refreshAll" />
      </template>
    </Alert>

    <!-- Main Content -->
    <template v-else>
      <!-- Summary Statistics -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <StatCard
          label="Total Tasks"
          :value="stats?.total_tasks ?? 0"
          icon="fa fa-tasks"
          variant="slate"
        />
        <StatCard
          label="Running"
          :value="stats?.running ?? 0"
          icon="fa fa-play-circle"
          variant="info"
        />
        <StatCard
          label="Pending"
          :value="stats?.pending ?? 0"
          icon="fa fa-clock"
          variant="warning"
        />
        <StatCard
          label="Blocked"
          :value="stats?.blocked ?? 0"
          icon="fa fa-ban"
          variant="slate"
        />
        <StatCard
          label="Failed"
          :value="stats?.error ?? 0"
          icon="fa fa-times-circle"
          variant="error"
        />
        <StatCard
          label="Success Rate"
          :value="formatPercent(stats?.success_rate ?? 0)"
          icon="fa fa-check-circle"
          variant="success"
          is-percentage
        />
      </div>

      <!-- Stuck Tasks Alert -->
      <Alert
        v-if="(stats?.stuck_count ?? 0) > 0"
        variant="warning"
        :title="`${stats?.stuck_count} Stuck Task${(stats?.stuck_count ?? 0) > 1 ? 's' : ''} Detected`"
        message="Tasks running longer than expected may need attention. Review and restart if necessary."
        icon="fa fa-exclamation-triangle"
      >
        <template #actions>
          <Button
            variant="secondary"
            size="sm"
            label="Select All Stuck"
            icon="fa fa-check-square"
            @click="selectAllStuck"
          />
        </template>
      </Alert>

      <!-- Filters -->
      <Card class="!p-4">
        <div class="flex flex-wrap items-center gap-4">
          <!-- Status Filter -->
          <Dropdown align="left" width="sm">
            <template #trigger="{ isOpen }">
              <button
                class="flex items-center gap-2 px-3 py-2 rounded-lg border border-primary-stroke bg-base-100 text-sm hover:bg-base-200 transition-colors"
              >
                <i class="fa fa-filter text-secondary"></i>
                <span>{{ selectedStatusLabel }}</span>
                <i :class="['fa fa-chevron-down text-xs transition-transform', { 'rotate-180': isOpen }]"></i>
              </button>
            </template>
            <template #content="{ close }">
              <DropdownItem @click="filters.status = undefined; close()">
                All Statuses
              </DropdownItem>
              <DropdownDivider />
              <DropdownItem @click="filters.status = 'running'; close()">
                <i class="fa fa-play-circle text-info mr-2"></i> Running
              </DropdownItem>
              <DropdownItem @click="filters.status = 'pending'; close()">
                <i class="fa fa-clock text-warning mr-2"></i> Pending
              </DropdownItem>
              <DropdownItem @click="filters.status = 'blocked'; close()">
                <i class="fa fa-ban text-secondary mr-2"></i> Blocked
              </DropdownItem>
              <DropdownItem @click="filters.status = 'succeeded'; close()">
                <i class="fa fa-check-circle text-success mr-2"></i> Succeeded
              </DropdownItem>
              <DropdownItem @click="filters.status = 'error'; close()">
                <i class="fa fa-times-circle text-error mr-2"></i> Error
              </DropdownItem>
            </template>
          </Dropdown>

          <!-- Task Type Filter -->
          <Dropdown align="left" width="sm">
            <template #trigger="{ isOpen }">
              <button
                class="flex items-center gap-2 px-3 py-2 rounded-lg border border-primary-stroke bg-base-100 text-sm hover:bg-base-200 transition-colors"
              >
                <i class="fa fa-tag text-secondary"></i>
                <span>{{ selectedTypeLabel }}</span>
                <i :class="['fa fa-chevron-down text-xs transition-transform', { 'rotate-180': isOpen }]"></i>
              </button>
            </template>
            <template #content="{ close }">
              <DropdownItem @click="filters.task_type = undefined; close()">
                All Types
              </DropdownItem>
              <DropdownDivider />
              <DropdownItem
                v-for="taskType in taskTypes"
                :key="taskType"
                @click="filters.task_type = taskType; close()"
              >
                {{ formatTaskType(taskType) }}
              </DropdownItem>
            </template>
          </Dropdown>

          <!-- Organization Filter -->
          <Dropdown align="left" width="md">
            <template #trigger="{ isOpen }">
              <button
                class="flex items-center gap-2 px-3 py-2 rounded-lg border border-primary-stroke bg-base-100 text-sm hover:bg-base-200 transition-colors"
              >
                <i class="fa fa-building text-secondary"></i>
                <span>{{ selectedOrgLabel }}</span>
                <i :class="['fa fa-chevron-down text-xs transition-transform', { 'rotate-180': isOpen }]"></i>
              </button>
            </template>
            <template #content="{ close }">
              <DropdownItem @click="filters.organization_id = undefined; close()">
                All Organizations
              </DropdownItem>
              <DropdownDivider />
              <DropdownItem
                v-for="org in organizations?.organizations ?? []"
                :key="org.id"
                @click="filters.organization_id = org.id; close()"
              >
                <span :class="{ 'text-secondary': org.is_internal }">
                  {{ org.name }}
                  <Tag v-if="org.is_internal" variant="slate" size="xs" label="Internal" class="ml-2" />
                </span>
              </DropdownItem>
            </template>
          </Dropdown>

          <!-- Clear Filters -->
          <Button
            v-if="hasActiveFilters"
            variant="tertiary"
            size="sm"
            icon="fa fa-times"
            label="Clear Filters"
            @click="clearFilters"
          />

          <!-- Spacer -->
          <div class="flex-1"></div>

          <!-- Bulk Actions -->
          <div v-if="selectedTaskIds.length > 0" class="flex items-center gap-3">
            <span class="text-sm text-secondary">
              {{ selectedTaskIds.length }} selected
            </span>
            <Button
              variant="primary"
              size="sm"
              icon="fa fa-redo"
              :label="`Restart ${selectedTaskIds.length} Task${selectedTaskIds.length > 1 ? 's' : ''}`"
              :loading="isRestarting"
              @click="handleBulkRestart"
            />
            <Button
              variant="tertiary"
              size="sm"
              label="Clear Selection"
              @click="clearSelection"
            />
          </div>
        </div>
      </Card>

      <!-- Tasks Table -->
      <Card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-base-200 border-b border-primary-stroke">
              <tr>
                <th class="px-4 py-3 text-left w-10">
                  <input
                    type="checkbox"
                    :checked="allRunningSelected"
                    :indeterminate="someRunningSelected && !allRunningSelected"
                    @change="toggleAllRunning"
                    class="rounded border-primary-stroke"
                  />
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wide">ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wide">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wide">Organization</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wide">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wide">Elapsed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-primary-stroke">
              <tr v-if="!tasks?.items?.length" class="bg-base-100">
                <td colspan="8" class="px-4 py-12 text-center text-secondary">
                  <i class="fa fa-inbox text-3xl mb-3 block"></i>
                  No tasks found matching your filters
                </td>
              </tr>
              <tr
                v-for="task in tasks?.items ?? []"
                :key="task.id"
                class="bg-base-100 hover:bg-base-200/50 transition-colors"
              >
                <td class="px-4 py-3">
                  <input
                    v-if="task.status === 'running'"
                    type="checkbox"
                    :checked="isSelected(task.id)"
                    @change="toggleTaskSelection(task.id)"
                    class="rounded border-primary-stroke"
                  />
                </td>
                <td class="px-4 py-3 text-sm font-mono">{{ task.id }}</td>
                <td class="px-4 py-3 text-sm font-medium">{{ task.company_name }}</td>
                <td class="px-4 py-3 text-sm text-secondary">
                  {{ getOrgName(task.organization_id) }}
                </td>
                <td class="px-4 py-3">
                  <Tag
                    :variant="task.is_prerequisite ? 'almond' : 'sage'"
                    size="xs"
                    :label="formatTaskType(task.type)"
                  />
                </td>
                <td class="px-4 py-3">
                  <Tag
                    :variant="getStatusVariant(task.status)"
                    size="xs"
                    :icon="getStatusIcon(task.status)"
                    :label="task.status"
                  />
                </td>
                <td class="px-4 py-3">
                  <span :class="getElapsedTimeClass(task)">
                    {{ formatElapsedTime(task.created_at) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <Button
                      v-if="task.status === 'running' || task.status === 'error'"
                      variant="tertiary"
                      size="sm"
                      icon="fa fa-redo"
                      icon-only
                      title="Restart task"
                      @click="handleRestartSingle(task.id)"
                    />
                    <Button
                      v-if="task.error"
                      variant="tertiary"
                      size="sm"
                      icon="fa fa-eye"
                      icon-only
                      title="View error"
                      @click="showError(task)"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="tasks && tasks.pages > 1" class="px-4 py-3 border-t border-primary-stroke bg-base-200/50">
          <div class="flex items-center justify-between">
            <span class="text-sm text-secondary">
              Showing {{ (filters.page! - 1) * filters.size! + 1 }} to {{ Math.min(filters.page! * filters.size!, tasks.total) }} of {{ tasks.total }} tasks
            </span>
            <div class="flex items-center gap-2">
              <Button
                variant="secondary"
                size="sm"
                icon="fa fa-chevron-left"
                :disabled="filters.page === 1"
                @click="filters.page!--"
              />
              <span class="px-3 py-1 text-sm">
                Page {{ filters.page }} of {{ tasks.pages }}
              </span>
              <Button
                variant="secondary"
                size="sm"
                icon="fa fa-chevron-right"
                :disabled="filters.page === tasks.pages"
                @click="filters.page!++"
              />
            </div>
          </div>
        </div>
      </Card>

      <!-- Failed Tasks Section -->
      <div v-if="failedTasks.length > 0">
        <h2 class="text-lg font-semibold mb-4">
          <i class="fa fa-exclamation-circle text-error mr-2"></i>
          Recent Failed Tasks
        </h2>
        <Card class="!p-0 overflow-hidden">
          <div class="divide-y divide-primary-stroke">
            <div
              v-for="task in failedTasks"
              :key="task.id"
              class="p-4 hover:bg-base-200/50 transition-colors"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-3 mb-2">
                    <span class="font-medium">{{ task.company_name }}</span>
                    <Tag variant="error" size="xs" :label="formatTaskType(task.type)" />
                    <span class="text-sm text-secondary">
                      {{ formatRelativeTime(task.updated_at) }}
                    </span>
                  </div>
                  <div
                    v-if="task.error"
                    class="text-sm text-error-light-content bg-error-light/50 rounded-lg p-3 font-mono"
                  >
                    <div
                      :class="{ 'line-clamp-2': !expandedErrors.has(task.id) }"
                    >
                      {{ task.error }}
                    </div>
                    <button
                      v-if="task.error.length > 100"
                      @click="toggleErrorExpand(task.id)"
                      class="text-xs text-error mt-2 hover:underline"
                    >
                      {{ expandedErrors.has(task.id) ? 'Show less' : 'Show more' }}
                    </button>
                  </div>
                </div>
                <Button
                  variant="secondary"
                  size="sm"
                  icon="fa fa-redo"
                  label="Restart"
                  @click="handleRestartSingle(task.id)"
                />
              </div>
            </div>
          </div>
        </Card>
      </div>
    </template>

    <!-- Bulk Restart Confirmation Modal -->
    <OModal
      v-model="showRestartModal"
      :display-modal="showRestartModal"
      title="Confirm Bulk Restart"
      size="md"
      icon="fa fa-redo"
      color="warning"
    >
      <template #description>
        <div class="space-y-4">
          <p class="text-secondary">
            You are about to restart <strong class="text-base-content">{{ selectedTaskIds.length }}</strong> task{{ selectedTaskIds.length > 1 ? 's' : '' }}.
            This action will:
          </p>
          <ul class="list-disc list-inside text-sm text-secondary space-y-1">
            <li>Cancel the currently running tasks</li>
            <li>Queue them for immediate re-execution</li>
            <li>Reset their status to "pending"</li>
          </ul>

          <!-- Selected tasks summary -->
          <div class="bg-base-200 rounded-lg p-3 max-h-40 overflow-y-auto">
            <p class="text-xs font-semibold text-secondary mb-2">Selected tasks:</p>
            <div class="space-y-1">
              <div
                v-for="task in selectedTasksForModal"
                :key="task.id"
                class="flex items-center justify-between text-sm"
              >
                <span class="truncate">{{ task.company_name }}</span>
                <Tag :variant="getStatusVariant(task.status)" size="xs" :label="formatTaskType(task.type)" />
              </div>
            </div>
          </div>

          <!-- Warning about stuck tasks -->
          <Alert
            v-if="selectedStuckCount > 0"
            variant="warning"
            :message="`${selectedStuckCount} of these tasks have been running for over 3 minutes and may be stuck.`"
            icon="fa fa-exclamation-triangle"
          />

          <!-- Restart result feedback -->
          <Alert
            v-if="lastRestartResult"
            :variant="lastRestartResult.skipped.length > 0 ? 'warning' : 'success'"
            :title="lastRestartResult.restarted.length > 0 ? 'Restart Initiated' : 'No Tasks Restarted'"
            icon="fa fa-info-circle"
          >
            <div class="text-sm space-y-1">
              <p v-if="lastRestartResult.restarted.length > 0">
                <i class="fa fa-check text-success mr-1"></i>
                {{ lastRestartResult.restarted.length }} task{{ lastRestartResult.restarted.length > 1 ? 's' : '' }} restarted successfully
              </p>
              <p v-if="lastRestartResult.skipped.length > 0">
                <i class="fa fa-exclamation-triangle text-warning mr-1"></i>
                {{ lastRestartResult.skipped.length }} task{{ lastRestartResult.skipped.length > 1 ? 's' : '' }} skipped
              </p>
              <div v-if="Object.keys(lastRestartResult.skipped_reasons).length > 0" class="mt-2 text-xs text-secondary">
                <p class="font-semibold">Skipped reasons:</p>
                <ul class="list-disc list-inside">
                  <li v-for="(reason, taskId) in lastRestartResult.skipped_reasons" :key="taskId">
                    Task #{{ taskId }}: {{ reason }}
                  </li>
                </ul>
              </div>
            </div>
          </Alert>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-3">
          <Button variant="secondary" label="Cancel" @click="closeRestartModal" :disabled="isRestarting" />
          <Button
            variant="primary"
            :label="isRestarting ? 'Restarting...' : `Restart ${selectedTaskIds.length} Task${selectedTaskIds.length > 1 ? 's' : ''}`"
            icon="fa fa-redo"
            @click="confirmBulkRestart"
            :loading="isRestarting"
            :disabled="lastRestartResult !== null"
          />
        </div>
      </template>
    </OModal>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.tasks
  title: 'Task Monitoring'
</route>

<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { useQuery, useQueryCache } from '@pinia/colada'
import { adminTasksQuery, adminTaskStatsQuery, adminOrganizationsQuery, ADMIN_QUERY_KEYS } from '@/queries/admin'
import { useRestartAdminTasks } from '@/mutations/admin'
import type { AdminTaskResponse, AdminTasksFilters, BulkRestartResponse } from '@/types/admin'
import type { TaskStatus, TaskType } from '@/types/task'
import Alert from '@/components/ui/Alert.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Tag from '@/components/ui/Tag.vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import DropdownDivider from '@/components/ui/DropdownDivider.vue'
import { OModal } from '@owlint/feathers-vue'

import { defineComponent, h } from 'vue'

// Stat Card Component (inline using render function)
const StatCard = defineComponent({
  props: {
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    icon: { type: String, required: true },
    variant: { type: String as () => 'success' | 'warning' | 'error' | 'info' | 'slate', default: 'slate' },
    isPercentage: { type: Boolean, default: false },
  },
  setup(props) {
    const variantClasses = computed(() => {
      const variants: Record<string, string> = {
        success: 'bg-success-light border-success-stroke text-success-light-content',
        warning: 'bg-warning-light border-warning-stroke text-warning-light-content',
        error: 'bg-error-light border-error-stroke text-error-light-content',
        info: 'bg-info-light border-info-stroke text-info-light-content',
        slate: 'bg-base-200 border-primary-stroke text-secondary',
      }
      return variants[props.variant] || variants.slate
    })

    const iconClasses = computed(() => {
      const variants: Record<string, string> = {
        success: 'text-success',
        warning: 'text-warning',
        error: 'text-error',
        info: 'text-info',
        slate: 'text-secondary',
      }
      return variants[props.variant] || variants.slate
    })

    return () => h('div', { class: `rounded-xl border p-4 ${variantClasses.value}` }, [
      h('div', { class: 'flex items-center gap-3' }, [
        h('div', { class: 'w-10 h-10 rounded-full bg-base-100/50 flex items-center justify-center' }, [
          h('i', { class: `${props.icon} ${iconClasses.value}` })
        ]),
        h('div', {}, [
          h('div', { class: 'text-2xl font-bold' }, props.value),
          h('div', { class: 'text-sm opacity-80' }, props.label)
        ])
      ])
    ])
  },
})

// Task types constant
const taskTypes: TaskType[] = ['profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', 'data_collection']

// Reactive filters
const filters = ref<AdminTasksFilters>({
  page: 1,
  size: 20,
  time_range_hours: 24,
  sort_by: 'created_at',
  sort_order: 'desc',
})

// Auto-refresh state
const autoRefreshEnabled = ref(false)
const lastRefresh = ref(new Date())
const isRefreshing = ref(false)
let refreshInterval: ReturnType<typeof setInterval> | null = null

// Error expansion state
const expandedErrors = ref(new Set<number>())

// Modal state
const showRestartModal = ref(false)
const lastRestartResult = ref<BulkRestartResponse | null>(null)

// Query cache
const queryCache = useQueryCache()

// Queries
const { data: stats, error: statsError, isLoading: statsLoading, refetch: refetchStats } = useQuery(
  adminTaskStatsQuery,
  () => ({ timeRangeHours: filters.value.time_range_hours ?? 24 })
)

const { data: tasks, error: tasksError, isLoading: tasksLoading, refetch: refetchTasks } = useQuery(
  adminTasksQuery,
  () => ({ filters: filters.value })
)

const { data: organizations } = useQuery(adminOrganizationsQuery)

// Mutation
const {
  selectedTaskIds,
  isSelected,
  toggleTaskSelection,
  selectAll,
  clearSelection,
  restartTasks,
  isPending: isRestarting,
} = useRestartAdminTasks()

// Computed
const isLoading = computed(() => statsLoading.value || tasksLoading.value)

const hasActiveFilters = computed(() => {
  return filters.value.status || filters.value.task_type || filters.value.organization_id
})

const selectedStatusLabel = computed(() => {
  if (!filters.value.status) return 'All Statuses'
  return filters.value.status.charAt(0).toUpperCase() + filters.value.status.slice(1)
})

const selectedTypeLabel = computed(() => {
  if (!filters.value.task_type) return 'All Types'
  return formatTaskType(filters.value.task_type)
})

const selectedOrgLabel = computed(() => {
  if (!filters.value.organization_id) return 'All Organizations'
  const org = organizations.value?.organizations.find(o => o.id === filters.value.organization_id)
  return org?.name ?? 'Unknown'
})

const failedTasks = computed(() => {
  return (tasks.value?.items ?? []).filter(t => t.status === 'error')
})

const runningTasks = computed(() => {
  return (tasks.value?.items ?? []).filter(t => t.status === 'running')
})

const allRunningSelected = computed(() => {
  if (runningTasks.value.length === 0) return false
  return runningTasks.value.every(t => selectedTaskIds.value.includes(t.id))
})

const someRunningSelected = computed(() => {
  return runningTasks.value.some(t => selectedTaskIds.value.includes(t.id))
})

// Selected tasks for modal display
const selectedTasksForModal = computed(() => {
  return (tasks.value?.items ?? []).filter(t => selectedTaskIds.value.includes(t.id))
})

// Count of selected stuck tasks (running > 3 min)
const selectedStuckCount = computed(() => {
  return selectedTasksForModal.value.filter(task => {
    if (task.status !== 'running') return false
    const created = new Date(task.created_at)
    const diffMin = Math.floor((Date.now() - created.getTime()) / 60000)
    return diffMin > 3
  }).length
})

const lastRefreshText = computed(() => {
  const seconds = Math.floor((Date.now() - lastRefresh.value.getTime()) / 1000)
  if (seconds < 60) return `Updated ${seconds}s ago`
  const minutes = Math.floor(seconds / 60)
  return `Updated ${minutes}m ago`
})

// Methods
function formatPercent(value: number): string {
  return `${(value * 100).toFixed(1)}%`
}

function formatTaskType(type: TaskType): string {
  const labels: Record<TaskType, string> = {
    profile: 'Profile',
    digital: 'Digital',
    timeline: 'Timeline',
    products: 'Products',
    jobs: 'Jobs',
    csr: 'CSR',
    press: 'Press',
    team: 'Team',
    data_collection: 'Data Collection',
  }
  return labels[type] || type
}

function getStatusVariant(status: TaskStatus): 'success' | 'warning' | 'error' | 'info' | 'slate' {
  const variants: Record<TaskStatus, 'success' | 'warning' | 'error' | 'info' | 'slate'> = {
    succeeded: 'success',
    pending: 'warning',
    error: 'error',
    running: 'info',
    blocked: 'slate',
  }
  return variants[status] || 'slate'
}

function getStatusIcon(status: TaskStatus): string {
  const icons: Record<TaskStatus, string> = {
    succeeded: 'fa fa-check-circle',
    pending: 'fa fa-clock',
    error: 'fa fa-times-circle',
    running: 'fa fa-play-circle',
    blocked: 'fa fa-ban',
  }
  return icons[status] || 'fa fa-question-circle'
}

function getOrgName(orgId: string | null): string {
  if (!orgId) return '-'
  const org = organizations.value?.organizations.find(o => o.id === orgId)
  return org?.name ?? orgId.slice(0, 8) + '...'
}

function formatElapsedTime(createdAt: string): string {
  const created = new Date(createdAt)
  const now = new Date()
  const diffMs = now.getTime() - created.getTime()
  const diffSec = Math.floor(diffMs / 1000)

  if (diffSec < 60) return `${diffSec}s`
  const diffMin = Math.floor(diffSec / 60)
  if (diffMin < 60) return `${diffMin}m ${diffSec % 60}s`
  const diffHour = Math.floor(diffMin / 60)
  return `${diffHour}h ${diffMin % 60}m`
}

function formatRelativeTime(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMin = Math.floor(diffMs / 60000)

  if (diffMin < 1) return 'just now'
  if (diffMin < 60) return `${diffMin}m ago`
  const diffHour = Math.floor(diffMin / 60)
  if (diffHour < 24) return `${diffHour}h ago`
  const diffDay = Math.floor(diffHour / 24)
  return `${diffDay}d ago`
}

function getElapsedTimeClass(task: AdminTaskResponse): string {
  if (task.status !== 'running') return 'text-secondary'

  const created = new Date(task.created_at)
  const diffMin = Math.floor((Date.now() - created.getTime()) / 60000)

  if (diffMin > 10) return 'text-error font-semibold' // Stuck
  if (diffMin > 5) return 'text-warning font-semibold' // Warning
  if (diffMin > 3) return 'text-warning' // Suspect
  return 'text-success' // Normal
}

function clearFilters() {
  filters.value.status = undefined
  filters.value.task_type = undefined
  filters.value.organization_id = undefined
  filters.value.page = 1
}

function toggleAllRunning() {
  if (allRunningSelected.value) {
    clearSelection()
  } else {
    selectAll(runningTasks.value.map(t => t.id))
  }
}

function selectAllStuck() {
  const stuckTasks = runningTasks.value.filter(task => {
    const created = new Date(task.created_at)
    const diffMin = Math.floor((Date.now() - created.getTime()) / 60000)
    return diffMin > 3
  })
  selectAll(stuckTasks.map(t => t.id))
}

// Open confirmation modal for bulk restart
function handleBulkRestart() {
  if (selectedTaskIds.value.length === 0) return
  lastRestartResult.value = null
  showRestartModal.value = true
}

// Close the restart modal
function closeRestartModal() {
  showRestartModal.value = false
  lastRestartResult.value = null
  // Refresh data if restart was performed
  if (lastRestartResult.value) {
    refreshAll()
  }
}

// Confirm and execute bulk restart
async function confirmBulkRestart() {
  if (selectedTaskIds.value.length === 0) return
  try {
    const result = await restartTasks(selectedTaskIds.value)
    lastRestartResult.value = result as BulkRestartResponse
    // Refresh after showing result
    await refreshAll()
    // Auto-close after 2 seconds if all succeeded
    if (result && (result as BulkRestartResponse).skipped.length === 0) {
      setTimeout(() => {
        showRestartModal.value = false
        lastRestartResult.value = null
      }, 2000)
    }
  } catch (error) {
    console.error('Failed to restart tasks:', error)
  }
}

async function handleRestartSingle(taskId: number) {
  await restartTasks([taskId])
  await refreshAll()
}

function showError(task: AdminTaskResponse) {
  expandedErrors.value.add(task.id)
}

function toggleErrorExpand(taskId: number) {
  if (expandedErrors.value.has(taskId)) {
    expandedErrors.value.delete(taskId)
  } else {
    expandedErrors.value.add(taskId)
  }
}

async function refreshAll() {
  isRefreshing.value = true
  try {
    await Promise.all([refetchStats(), refetchTasks()])
    lastRefresh.value = new Date()
  } finally {
    isRefreshing.value = false
  }
}

function toggleAutoRefresh() {
  autoRefreshEnabled.value = !autoRefreshEnabled.value

  if (autoRefreshEnabled.value) {
    refreshInterval = setInterval(() => {
      refreshAll()
    }, 10000) // 10 seconds
  } else if (refreshInterval) {
    clearInterval(refreshInterval)
    refreshInterval = null
  }
}

// Watch for filter changes to reset page
watch([() => filters.value.status, () => filters.value.task_type, () => filters.value.organization_id], () => {
  filters.value.page = 1
})

// Cleanup
onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>
