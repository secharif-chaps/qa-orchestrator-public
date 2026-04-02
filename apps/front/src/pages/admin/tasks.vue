<template>
  <div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">
          {{ $t('admin.tasks.title') }}
        </h1>
        <p class="text-secondary mt-1">
          {{ $t('admin.tasks.description') }}
        </p>
      </div>

      <!-- Auto-refresh toggle -->
      <div class="flex items-center gap-4">
        <span class="text-secondary text-sm">
          {{ lastRefreshText }}
        </span>
        <button
          @click="toggleAutoRefresh"
          :class="[
            'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
            autoRefreshEnabled
              ? 'bg-success-light text-success-light-content'
              : 'bg-base-200 text-secondary hover:bg-base-300',
          ]"
        >
          <i
            :class="['fa fa-sync-alt', { 'animate-spin': autoRefreshEnabled && isRefreshing }]"
          ></i>
          {{
            autoRefreshEnabled
              ? $t('admin.tasks.autoRefresh.on')
              : $t('admin.tasks.autoRefresh.off')
          }}
        </button>
        <Button
          variant="secondary"
          icon="fa fa-refresh"
          :label="$t('admin.tasks.refresh')"
          @click="refreshAll"
          :loading="isRefreshing"
        />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center gap-4 py-16">
      <i class="fa fa-spinner text-primary animate-spin text-4xl"></i>
      <p class="text-secondary">{{ $t('admin.tasks.loading') }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="tasksError" class="flex flex-col gap-4">
      <Alert
        variant="danger"
        :title="$t('admin.tasks.error.title')"
        :description="tasksError?.message ?? $t('admin.tasks.error.description')"
        icon="fa-exclamation-triangle"
        :action="$t('admin.tasks.error.retry')"
        @click="refreshAll"
      />
    </div>

    <!-- Main Content -->
    <template v-else>
      <!-- Summary Statistics -->
      <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
        <StatCard
          :label="$t('admin.tasks.stats.totalTasks')"
          :value="stats?.total_tasks ?? 0"
          icon="fa fa-tasks"
          variant="slate"
        />
        <StatCard
          :label="$t('admin.tasks.stats.running')"
          :value="stats?.running ?? 0"
          icon="fa fa-play-circle"
          variant="info"
        />
        <StatCard
          :label="$t('admin.tasks.stats.pending')"
          :value="stats?.pending ?? 0"
          icon="fa fa-clock"
          variant="warning"
        />
        <StatCard
          :label="$t('admin.tasks.stats.failed')"
          :value="stats?.error ?? 0"
          icon="fa fa-times-circle"
          variant="error"
        />
        <StatCard
          :label="$t('admin.tasks.stats.successRate')"
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
        :title="
          $t(
            'admin.tasks.stuckAlert.title',
            { count: stats?.stuck_count ?? 0 },
            stats?.stuck_count ?? 0,
          )
        "
        :description="$t('admin.tasks.stuckAlert.description')"
        icon="fa-exclamation-triangle"
        :action="$t('admin.tasks.stuckAlert.selectAll')"
        @click="selectAllStuck"
      />

      <!-- Filters -->
      <Card class="!p-4">
        <div class="flex flex-wrap items-center gap-4">
          <!-- Status Filter -->
          <Dropdown align="left" width="sm">
            <template #trigger="{ isOpen }">
              <button
                class="border-primary-stroke bg-base-100 hover:bg-base-200 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
              >
                <i class="fa fa-filter text-secondary"></i>
                <span>{{ selectedStatusLabel }}</span>
                <i
                  :class="[
                    'fa fa-chevron-down text-xs transition-transform',
                    { 'rotate-180': isOpen },
                  ]"
                ></i>
              </button>
            </template>
            <template #content>
              <DropdownItem @click="filters.status = undefined">
                {{ $t('admin.tasks.filters.allStatuses') }}
              </DropdownItem>
              <DropdownDivider />
              <DropdownItem @click="filters.status = 'running'">
                <i class="fa fa-play-circle text-info mr-2"></i>
                {{ $t('admin.tasks.status.running') }}
              </DropdownItem>
              <DropdownItem @click="filters.status = 'pending'">
                <i class="fa fa-clock text-warning mr-2"></i> {{ $t('admin.tasks.status.pending') }}
              </DropdownItem>
              <DropdownItem @click="filters.status = 'succeeded'">
                <i class="fa fa-check-circle text-success mr-2"></i>
                {{ $t('admin.tasks.status.succeeded') }}
              </DropdownItem>
              <DropdownItem @click="filters.status = 'error'">
                <i class="fa fa-times-circle text-error mr-2"></i>
                {{ $t('admin.tasks.status.error') }}
              </DropdownItem>
            </template>
          </Dropdown>

          <!-- Task Type Filter -->
          <Dropdown align="left" width="sm">
            <template #trigger="{ isOpen }">
              <button
                class="border-primary-stroke bg-base-100 hover:bg-base-200 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
              >
                <i class="fa fa-tag text-secondary"></i>
                <span>{{ selectedTypeLabel }}</span>
                <i
                  :class="[
                    'fa fa-chevron-down text-xs transition-transform',
                    { 'rotate-180': isOpen },
                  ]"
                ></i>
              </button>
            </template>
            <template #content>
              <DropdownItem @click="filters.task_type = undefined">
                {{ $t('admin.tasks.filters.allTypes') }}
              </DropdownItem>
              <DropdownDivider />
              <DropdownItem
                v-for="taskType in taskTypes"
                :key="taskType"
                @click="filters.task_type = taskType"
              >
                {{ formatTaskType(taskType) }}
              </DropdownItem>
            </template>
          </Dropdown>

          <!-- Organization Filter -->
          <Dropdown align="left" width="md">
            <template #trigger="{ isOpen }">
              <button
                class="border-primary-stroke bg-base-100 hover:bg-base-200 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
              >
                <i class="fa fa-building text-secondary"></i>
                <span>{{ selectedOrgLabel }}</span>
                <i
                  :class="[
                    'fa fa-chevron-down text-xs transition-transform',
                    { 'rotate-180': isOpen },
                  ]"
                ></i>
              </button>
            </template>
            <template #content>
              <DropdownItem @click="filters.organization_id = undefined">
                {{ $t('admin.tasks.filters.allOrganizations') }}
              </DropdownItem>
              <DropdownDivider />
              <DropdownItem
                v-for="org in organizations?.organizations ?? []"
                :key="org.id"
                @click="filters.organization_id = org.id"
              >
                <span :class="{ 'text-secondary': org.is_internal }">
                  {{ org.name }}
                  <Tag
                    v-if="org.is_internal"
                    variant="slate"
                    size="xs"
                    :label="$t('admin.tasks.filters.internal')"
                    class="ml-2"
                  />
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
            :label="$t('admin.tasks.filters.clearFilters')"
            @click="clearFilters"
          />

          <!-- Spacer -->
          <div class="flex-1"></div>

          <!-- Bulk Actions -->
          <div v-if="selectedTaskIds.length > 0" class="flex items-center gap-3">
            <span class="text-secondary text-sm">
              {{ $t('admin.tasks.selection.selected', { count: selectedTaskIds.length }) }}
            </span>
            <Button
              variant="primary"
              size="sm"
              icon="fa fa-redo"
              :label="
                $t(
                  'admin.tasks.selection.restart',
                  { count: selectedTaskIds.length },
                  selectedTaskIds.length,
                )
              "
              :loading="isRestarting"
              @click="handleBulkRestart"
            />
            <Button
              variant="tertiary"
              size="sm"
              :label="$t('admin.tasks.selection.clearSelection')"
              @click="clearSelection"
            />
          </div>
        </div>
      </Card>

      <!-- Tasks Table -->
      <Card class="overflow-hidden !p-0">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-base-200 border-primary-stroke border-b">
              <tr>
                <th class="w-10 px-4 py-3 text-left">
                  <input
                    type="checkbox"
                    :checked="allRunningSelected"
                    :indeterminate="someRunningSelected && !allRunningSelected"
                    @change="toggleAllRunning"
                    class="border-primary-stroke rounded"
                  />
                </th>
                <th
                  class="text-secondary px-4 py-3 text-left text-xs font-semibold tracking-wide uppercase"
                >
                  {{ $t('admin.tasks.table.id') }}
                </th>
                <th
                  class="text-secondary px-4 py-3 text-left text-xs font-semibold tracking-wide uppercase"
                >
                  {{ $t('admin.tasks.table.company') }}
                </th>
                <th
                  class="text-secondary px-4 py-3 text-left text-xs font-semibold tracking-wide uppercase"
                >
                  {{ $t('admin.tasks.table.organization') }}
                </th>
                <th
                  class="text-secondary px-4 py-3 text-left text-xs font-semibold tracking-wide uppercase"
                >
                  {{ $t('admin.tasks.table.type') }}
                </th>
                <th
                  class="text-secondary px-4 py-3 text-left text-xs font-semibold tracking-wide uppercase"
                >
                  {{ $t('admin.tasks.table.status') }}
                </th>
                <th
                  class="text-secondary px-4 py-3 text-left text-xs font-semibold tracking-wide uppercase"
                >
                  {{ $t('admin.tasks.table.elapsed') }}
                </th>
                <th
                  class="text-secondary px-4 py-3 text-left text-xs font-semibold tracking-wide uppercase"
                >
                  {{ $t('admin.tasks.table.actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-primary-stroke divide-y">
              <tr v-if="!tasks?.items?.length" class="bg-base-100">
                <td colspan="8" class="text-secondary px-4 py-12 text-center">
                  <i class="fa fa-inbox mb-3 block text-3xl"></i>
                  {{ $t('admin.tasks.table.noTasks') }}
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
                    class="border-primary-stroke rounded"
                  />
                </td>
                <td class="px-4 py-3 font-mono text-sm">{{ task.id }}</td>
                <td class="px-4 py-3 text-sm font-medium">{{ task.company_name }}</td>
                <td class="text-secondary px-4 py-3 text-sm">
                  {{ getOrgName(task.organization_id) }}
                </td>
                <td class="px-4 py-3">
                  <Tag variant="sage" size="xs" :label="formatTaskType(task.type)" />
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
                      :title="$t('admin.tasks.table.restartTask')"
                      @click="handleRestartSingle(task.id)"
                    />
                    <Button
                      v-if="task.error"
                      variant="tertiary"
                      size="sm"
                      icon="fa fa-eye"
                      icon-only
                      :title="$t('admin.tasks.table.viewError')"
                      @click="showError(task)"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div
          v-if="tasks && tasks.pages > 1"
          class="border-primary-stroke bg-base-200/50 border-t px-4 py-3"
        >
          <div class="flex items-center justify-between">
            <span class="text-secondary text-sm">
              {{
                $t('admin.tasks.pagination.showing', {
                  from: (filters.page! - 1) * filters.size! + 1,
                  to: Math.min(filters.page! * filters.size!, tasks.total),
                  total: tasks.total,
                })
              }}
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
                {{ $t('admin.tasks.pagination.page', { page: filters.page, pages: tasks.pages }) }}
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
    </template>

    <!-- Bulk Restart Confirmation Modal -->
    <OModal
      v-model="showRestartModal"
      :display-modal="showRestartModal"
      :title="$t('admin.tasks.modal.title')"
      size="md"
      icon="fa fa-redo"
      color="warning"
    >
      <template #description>
        <div class="space-y-4">
          <p class="text-secondary">
            {{
              $t(
                'admin.tasks.modal.description',
                { count: selectedTaskIds.length },
                selectedTaskIds.length,
              )
            }}
          </p>
          <ul class="text-secondary list-inside list-disc space-y-1 text-sm">
            <li>{{ $t('admin.tasks.modal.actions.cancel') }}</li>
            <li>{{ $t('admin.tasks.modal.actions.queue') }}</li>
            <li>{{ $t('admin.tasks.modal.actions.reset') }}</li>
          </ul>

          <!-- Selected tasks summary -->
          <div class="bg-base-200 max-h-40 overflow-y-auto rounded-lg p-3">
            <p class="text-secondary mb-2 text-xs font-semibold">
              {{ $t('admin.tasks.modal.selectedTasks') }}
            </p>
            <div class="space-y-1">
              <div
                v-for="task in selectedTasksForModal"
                :key="task.id"
                class="flex items-center justify-between text-sm"
              >
                <span class="truncate">{{ task.company_name }}</span>
                <Tag
                  :variant="getStatusVariant(task.status)"
                  size="xs"
                  :label="formatTaskType(task.type)"
                />
              </div>
            </div>
          </div>

          <!-- Warning about stuck tasks -->
          <Alert
            v-if="selectedStuckCount > 0"
            variant="warning"
            :message="$t('admin.tasks.modal.stuckWarning', { count: selectedStuckCount })"
            icon="fa fa-exclamation-triangle"
          />

          <!-- Restart result feedback -->
          <Alert
            v-if="lastRestartResult"
            :variant="lastRestartResult.skipped.length > 0 ? 'warning' : 'success'"
            :title="
              lastRestartResult.restarted.length > 0
                ? $t('admin.tasks.modal.result.initiated')
                : $t('admin.tasks.modal.result.noTasks')
            "
            icon="fa fa-info-circle"
          >
            <div class="space-y-1 text-sm">
              <p v-if="lastRestartResult.restarted.length > 0">
                <i class="fa fa-check text-success mr-1"></i>
                {{
                  $t(
                    'admin.tasks.modal.result.restarted',
                    { count: lastRestartResult.restarted.length },
                    lastRestartResult.restarted.length,
                  )
                }}
              </p>
              <p v-if="lastRestartResult.skipped.length > 0">
                <i class="fa fa-exclamation-triangle text-warning mr-1"></i>
                {{
                  $t(
                    'admin.tasks.modal.result.skipped',
                    { count: lastRestartResult.skipped.length },
                    lastRestartResult.skipped.length,
                  )
                }}
              </p>
              <div
                v-if="Object.keys(lastRestartResult.skipped_reasons).length > 0"
                class="text-secondary mt-2 text-xs"
              >
                <p class="font-semibold">{{ $t('admin.tasks.modal.result.skippedReasons') }}</p>
                <ul class="list-inside list-disc">
                  <li v-for="(reason, taskId) in lastRestartResult.skipped_reasons" :key="taskId">
                    {{ $t('admin.tasks.modal.result.taskReason', { id: taskId, reason: reason }) }}
                  </li>
                </ul>
              </div>
            </div>
          </Alert>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-3">
          <Button
            variant="secondary"
            :label="$t('admin.tasks.modal.buttons.cancel')"
            @click="closeRestartModal"
            :disabled="isRestarting"
          />
          <Button
            variant="primary"
            :label="
              isRestarting
                ? $t('admin.tasks.modal.buttons.restarting')
                : $t(
                    'admin.tasks.modal.buttons.restart',
                    { count: selectedTaskIds.length },
                    selectedTaskIds.length,
                  )
            "
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
import { ref, computed, watch, onUnmounted, defineComponent, h } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuery } from '@pinia/colada'
import { adminTasksQuery, adminOrganizationsQuery } from '@/queries/admin'
import { useRestartAdminTasks } from '@/mutations/admin'
import type { AdminTaskResponse, AdminTasksFilters, BulkRestartResponse } from '@/types/admin'
import type { TaskStatus, TaskType } from '@/types/task'
import { Alert, Badge, Button, OModal } from '@owlint/feathers-vue'
import Card from '@/components/ui/Card.vue'
import Tag from '@/components/ui/Tag.vue'
import Dropdown from '@/components/ui/Dropdown.vue'
import DropdownItem from '@/components/ui/DropdownItem.vue'
import DropdownDivider from '@/components/ui/DropdownDivider.vue'

// Stat Card Component (inline using render function with Badge for icon)
const StatCard = defineComponent({
  props: {
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    icon: { type: String, required: true },
    variant: {
      type: String as () => 'success' | 'warning' | 'error' | 'info' | 'slate',
      default: 'slate',
    },
    isPercentage: { type: Boolean, default: false },
  },
  setup(props) {
    const variantClasses = computed(() => {
      const variants: Record<string, string> = {
        success:
          'border-success-stroke bg-success-50 text-success-light-950 dark:border-success-400/30 dark:bg-success-400/30 dark:text-success-50',
        warning:
          'border-warning-stroke bg-warning-50 text-warning-950 dark:border-warning-400/30 dark:bg-warning-400/30 dark:text-warning-50',
        error:
          'border-error-stroke bg-error-50 text-error-950 dark:border-error-400/30 dark:bg-error-400/30 dark:text-error-50',
        info: 'border-info-stroke bg-info-50 text-info-950 dark:border-info-400/30 dark:bg-info-400/30 dark:text-info-50',
        slate:
          'border-gray-300 bg-gray-50 text-gray-950 dark:border-gray-400/30 dark:bg-gray-400/30 dark:text-gray-50',
      }
      return variants[props.variant] || variants.slate
    })

    // Map stat card variant to badge intent (Vuellar uses intent for semantic colors)
    type BadgeIntent = 'success' | 'warning' | 'info' | 'neutral' | 'accent' | 'danger'
    const badgeIntent = computed((): BadgeIntent => {
      const intentMap: Record<string, BadgeIntent> = {
        success: 'success',
        warning: 'warning',
        error: 'danger',
        info: 'info',
        slate: 'neutral',
      }
      return intentMap[props.variant] || 'neutral'
    })

    return () =>
      h('div', { class: `relative rounded-xl border p-4 ${variantClasses.value}` }, [
        h('div', { class: 'flex items-start gap-4' }, [
          h(Badge, {
            intent: badgeIntent.value,
            variant: 'secondary',
            size: 'sm',
            icon: props.icon,
          }),
          h('div', { class: 'flex-1 min-w-0' }, [
            h('div', { class: 'text-2xl font-bold' }, props.value),
            h('div', { class: 'text-sm leading-relaxed opacity-90' }, props.label),
          ]),
        ]),
      ])
  },
})

// Task types constant
const taskTypes: TaskType[] = [
  'profile',
  'digital',
  'timeline',
  'products',
  'jobs',
  'csr',
  'press',
  'team',
]

// i18n
const { t } = useI18n()

// Reactive filters (no time_range_hours - we paginate through all tasks)
const filters = ref<AdminTasksFilters>({
  page: 1,
  size: 20,
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

// Query - single query for tasks, stats computed client-side from current page
const {
  data: tasks,
  error: tasksError,
  isLoading: tasksLoading,
  refetch: refetchTasks,
} = useQuery(() => adminTasksQuery({ filters: filters.value }))

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

// Computed stats from current page tasks
const stats = computed(() => {
  const items = tasks.value?.items ?? []

  // Count by status
  const running = items.filter((t) => t.status === 'running').length
  const pending = items.filter((t) => t.status === 'pending').length
  const succeeded = items.filter((t) => t.status === 'succeeded').length
  const error = items.filter((t) => t.status === 'error').length

  // Calculate success rate (succeeded / (succeeded + error))
  const completed = succeeded + error
  const success_rate = completed > 0 ? succeeded / completed : 0

  // Count stuck tasks (running > 3 minutes)
  const stuck_count = items.filter((task) => {
    if (task.status !== 'running') return false
    const created = new Date(task.created_at)
    const diffMin = Math.floor((Date.now() - created.getTime()) / 60000)
    return diffMin > 3
  }).length

  return {
    total_tasks: items.length,
    running,
    pending,
    succeeded,
    error,
    success_rate,
    stuck_count,
  }
})

// Computed
const isLoading = computed(() => tasksLoading.value)

const hasActiveFilters = computed(() => {
  return filters.value.status || filters.value.task_type || filters.value.organization_id
})

const selectedStatusLabel = computed(() => {
  if (!filters.value.status) return t('admin.tasks.filters.allStatuses')
  return t(`admin.tasks.status.${filters.value.status}`)
})

const selectedTypeLabel = computed(() => {
  if (!filters.value.task_type) return t('admin.tasks.filters.allTypes')
  return formatTaskType(filters.value.task_type)
})

const selectedOrgLabel = computed(() => {
  if (!filters.value.organization_id) return t('admin.tasks.filters.allOrganizations')
  const org = organizations.value?.organizations.find((o) => o.id === filters.value.organization_id)
  return org?.name ?? 'Unknown'
})

const runningTasks = computed(() => {
  return (tasks.value?.items ?? []).filter((t) => t.status === 'running')
})

const allRunningSelected = computed(() => {
  if (runningTasks.value.length === 0) return false
  return runningTasks.value.every((t) => selectedTaskIds.value.includes(t.id))
})

const someRunningSelected = computed(() => {
  return runningTasks.value.some((t) => selectedTaskIds.value.includes(t.id))
})

// Selected tasks for modal display
const selectedTasksForModal = computed(() => {
  return (tasks.value?.items ?? []).filter((t) => selectedTaskIds.value.includes(t.id))
})

// Count of selected stuck tasks (running > 3 min)
const selectedStuckCount = computed(() => {
  return selectedTasksForModal.value.filter((task) => {
    if (task.status !== 'running') return false
    const created = new Date(task.created_at)
    const diffMin = Math.floor((Date.now() - created.getTime()) / 60000)
    return diffMin > 3
  }).length
})

const lastRefreshText = computed(() => {
  const seconds = Math.floor((Date.now() - lastRefresh.value.getTime()) / 1000)
  if (seconds < 60) return t('admin.tasks.lastUpdated.seconds', { seconds })
  const minutes = Math.floor(seconds / 60)
  return t('admin.tasks.lastUpdated.minutes', { minutes })
})

// Methods
function formatPercent(value: number): string {
  return `${(value * 100).toFixed(1)}%`
}

// Task type translation keys mapping
const taskTypeKeys: Record<TaskType, string> = {
  profile: 'admin.tasks.taskTypes.profile',
  digital: 'admin.tasks.taskTypes.digital',
  timeline: 'admin.tasks.taskTypes.timeline',
  products: 'admin.tasks.taskTypes.products',
  jobs: 'admin.tasks.taskTypes.jobs',
  csr: 'admin.tasks.taskTypes.csr',
  press: 'admin.tasks.taskTypes.press',
  team: 'admin.tasks.taskTypes.team',
  corporate_structure: 'admin.tasks.taskTypes.corporateStructure',
  sanctions: 'admin.tasks.taskTypes.sanctions',
}

function formatTaskType(type: TaskType): string {
  return t(taskTypeKeys[type]) || type
}

function getStatusVariant(status: TaskStatus): 'success' | 'warning' | 'error' | 'info' | 'slate' {
  const variants: Record<TaskStatus, 'success' | 'warning' | 'error' | 'info' | 'slate'> = {
    succeeded: 'success',
    pending: 'warning',
    error: 'error',
    running: 'info',
  }
  return variants[status] || 'slate'
}

function getStatusIcon(status: TaskStatus): string {
  const icons: Record<TaskStatus, string> = {
    succeeded: 'fa fa-check-circle',
    pending: 'fa fa-clock',
    error: 'fa fa-times-circle',
    running: 'fa fa-play-circle',
  }
  return icons[status] || 'fa fa-question-circle'
}

function getOrgName(orgId: string | null): string {
  if (!orgId) return '-'
  const org = organizations.value?.organizations.find((o) => o.id === orgId)
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
    selectAll(runningTasks.value.map((t) => t.id))
  }
}

function selectAllStuck() {
  const stuckTasks = runningTasks.value.filter((task) => {
    const created = new Date(task.created_at)
    const diffMin = Math.floor((Date.now() - created.getTime()) / 60000)
    return diffMin > 3
  })
  selectAll(stuckTasks.map((t) => t.id))
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

async function refreshAll() {
  isRefreshing.value = true
  try {
    await refetchTasks()
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
watch(
  [() => filters.value.status, () => filters.value.task_type, () => filters.value.organization_id],
  () => {
    filters.value.page = 1
  },
)

// Cleanup
onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>
