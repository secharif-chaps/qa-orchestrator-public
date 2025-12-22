<template>
  <div class="flex flex-col gap-6">
    <!-- Summary -->
    <div class="flex items-center gap-4 p-4 bg-base-200 rounded-xl">
      <Badge icon="fa-solid fa-users" />
      <div>
        <p class="font-medium text-sage-700 dark:text-sage-200">
          {{ $t('admin.import.previewSummary', { count: validUserCount }) }}
        </p>
        <p v-if="skippedCount > 0" class="text-sm text-warning-light-content">
          {{ $t('admin.import.previewSkipped', { count: skippedCount }) }}
        </p>
      </div>
    </div>

    <!-- Duplicate Emails Alert -->
    <Alert
      v-if="duplicateEmails.length > 0"
      variant="warning"
      :title="$t('admin.import.duplicatesFound')"
      icon="fa-solid fa-exclamation-triangle"
    >
      <template #default>
        <p class="text-sm">{{ $t('admin.import.duplicatesMessage') }}</p>
        <ul class="mt-2 text-sm list-disc list-inside">
          <li v-for="dup in duplicateEmails" :key="dup.email">
            <strong>{{ dup.username }}</strong> ({{ dup.email }}) - Row {{ dup.rowIndex + 1 }}
          </li>
        </ul>
      </template>
    </Alert>

    <!-- Validation Errors Alert -->
    <Alert
      v-if="validationErrors.length > 0"
      variant="danger"
      :title="$t('admin.import.validationErrors')"
      icon="fa-solid fa-exclamation-circle"
    >
      <template #default>
        <ul class="mt-2 text-sm list-disc list-inside max-h-32 overflow-y-auto">
          <li v-for="(error, idx) in validationErrors.slice(0, 10)" :key="idx">
            Row {{ error.row + 1 }}: {{ error.field }} - {{ error.message }}
          </li>
          <li v-if="validationErrors.length > 10" class="text-sage-500">
            ... and {{ validationErrors.length - 10 }} more errors
          </li>
        </ul>
      </template>
    </Alert>

    <!-- Preview Table -->
    <div class="overflow-x-auto border border-primary-stroke rounded-xl">
      <table class="w-full min-w-max">
        <thead class="bg-base-200">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-semibold text-sage-700 dark:text-sage-200">
              #
            </th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-sage-700 dark:text-sage-200">
              {{ $t('admin.import.fields.username') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-sage-700 dark:text-sage-200">
              {{ $t('admin.import.fields.email') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-sage-700 dark:text-sage-200">
              {{ $t('admin.import.fields.firstname') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-sage-700 dark:text-sage-200">
              {{ $t('admin.import.fields.lastname') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-sage-700 dark:text-sage-200">
              {{ $t('admin.import.fields.password') }}
            </th>
            <th class="px-4 py-3 text-left text-sm font-semibold text-sage-700 dark:text-sage-200">
              {{ $t('admin.import.status') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-primary-stroke">
          <tr
            v-for="(user, idx) in displayUsers"
            :key="idx"
            :class="[
              'bg-base-100',
              getRowStatus(idx) === 'error' && 'bg-error-light/30',
              getRowStatus(idx) === 'warning' && 'bg-warning-light/30',
            ]"
          >
            <td class="px-4 py-3 text-sm text-sage-500">{{ idx + 1 }}</td>
            <td class="px-4 py-3 text-sm">{{ user.username || '—' }}</td>
            <td class="px-4 py-3 text-sm">{{ user.email || '—' }}</td>
            <td class="px-4 py-3 text-sm text-sage-500">{{ user.firstname || '—' }}</td>
            <td class="px-4 py-3 text-sm text-sage-500">{{ user.lastname || '—' }}</td>
            <td class="px-4 py-3 text-sm text-sage-500">
              <!-- generatePasswords=true means generate ALL; false means use CSV (generate for missing) -->
              {{ generatePasswords ? $t('admin.import.willGenerate') : (user.password ? '••••••••' : $t('admin.import.willGenerate')) }}
            </td>
            <td class="px-4 py-3">
              <Tag
                v-if="getRowStatus(idx) === 'error'"
                variant="error"
                :label="getRowError(idx)"
                size="xs"
              />
              <Tag
                v-else-if="getRowStatus(idx) === 'warning'"
                variant="warning"
                :label="$t('admin.import.duplicate')"
                size="xs"
              />
              <Tag
                v-else
                variant="success"
                :label="$t('admin.import.ready')"
                size="xs"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Show more indicator -->
    <p v-if="users.length > MAX_PREVIEW_ROWS" class="text-sm text-sage-500 text-center">
      {{ $t('admin.import.showingPreview', { shown: MAX_PREVIEW_ROWS, total: users.length }) }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Alert, Badge } from '@owlint/feathers-vue'
import Tag from '@/components/ui/Tag.vue'
import type { UserImportRow, ValidationError, DuplicateInfo } from '@/types/user-import'

const MAX_PREVIEW_ROWS = 20

interface Props {
  /** Users to preview */
  users: UserImportRow[]
  /** Whether passwords will be generated */
  generatePasswords: boolean
  /** Duplicate emails found */
  duplicates?: DuplicateInfo[]
  /** Validation errors per row */
  validationErrors?: ValidationError[]
}

const props = withDefaults(defineProps<Props>(), {
  duplicates: () => [],
  validationErrors: () => [],
})

const { t } = useI18n()

/**
 * Users to display (limited for preview)
 */
const displayUsers = computed(() => {
  return props.users.slice(0, MAX_PREVIEW_ROWS)
})

/**
 * Duplicate emails for display
 */
const duplicateEmails = computed(() => {
  return props.duplicates || []
})

/**
 * Count of valid users (not errors or duplicates)
 */
const validUserCount = computed(() => {
  const errorRows = new Set(props.validationErrors.map((e) => e.row))
  const duplicateRows = new Set(props.duplicates.map((d) => d.rowIndex))

  return props.users.filter((_, idx) => !errorRows.has(idx) && !duplicateRows.has(idx)).length
})

/**
 * Count of skipped users
 */
const skippedCount = computed(() => {
  return props.users.length - validUserCount.value
})

/**
 * Get row status
 */
function getRowStatus(rowIndex: number): 'ok' | 'warning' | 'error' {
  // Check for validation errors
  if (props.validationErrors.some((e) => e.row === rowIndex)) {
    return 'error'
  }

  // Check for duplicates
  if (props.duplicates.some((d) => d.rowIndex === rowIndex)) {
    return 'warning'
  }

  return 'ok'
}

/**
 * Get error message for a row
 */
function getRowError(rowIndex: number): string {
  const error = props.validationErrors.find((e) => e.row === rowIndex)
  return error ? `${error.field}: ${error.message}` : ''
}
</script>
