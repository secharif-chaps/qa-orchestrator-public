<template>
  <div class="flex flex-col gap-6">
    <!-- Mapping Table -->
    <div class="border-primary-stroke overflow-hidden rounded-xl border">
      <table class="w-full">
        <thead class="bg-base-200">
          <tr>
            <th class="text-sage-700 dark:text-sage-200 px-4 py-3 text-left text-sm font-semibold">
              {{ $t('admin.import.csvColumn') }}
            </th>
            <th class="text-sage-700 dark:text-sage-200 px-4 py-3 text-left text-sm font-semibold">
              {{ $t('admin.import.mapsTo') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-primary-stroke divide-y">
          <tr v-for="mapping in mappings" :key="mapping.csvColumn" class="bg-base-100">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <code class="bg-base-200 rounded px-2 py-1 text-sm">
                  {{ mapping.csvColumn }}
                </code>
                <span
                  v-if="mapping.isRequired"
                  class="text-error text-sm"
                  :title="$t('admin.import.requiredFieldTooltip')"
                >
                  *
                </span>
              </div>
            </td>
            <td class="px-4 py-3">
              <select
                :value="mapping.targetField || 'ignore'"
                class="bg-base-100 border-primary-stroke text-sage-700 dark:text-sage-200 focus:ring-primary focus:border-primary w-full max-w-xs rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:outline-none"
                @change="
                  handleMappingChange(mapping.csvColumn, ($event.target as HTMLSelectElement).value)
                "
              >
                <option value="ignore">— {{ $t('admin.import.ignore') }}</option>
                <option value="username">
                  {{ $t('admin.import.fields.username') }}
                  {{ isFieldUsed('username', mapping.csvColumn) ? '✓' : '' }}
                </option>
                <option value="email">
                  {{ $t('admin.import.fields.email') }}
                  {{ isFieldUsed('email', mapping.csvColumn) ? '✓' : '' }}
                </option>
                <option value="firstname">
                  {{ $t('admin.import.fields.firstname') }}
                  {{ isFieldUsed('firstname', mapping.csvColumn) ? '✓' : '' }}
                </option>
                <option value="lastname">
                  {{ $t('admin.import.fields.lastname') }}
                  {{ isFieldUsed('lastname', mapping.csvColumn) ? '✓' : '' }}
                </option>
                <option value="password">
                  {{ $t('admin.import.fields.password') }}
                  {{ isFieldUsed('password', mapping.csvColumn) ? '✓' : '' }}
                </option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Warning for ignored columns -->
    <Alert
      v-if="unmappedColumns.length > 0"
      variant="warning"
      :title="$t('admin.import.ignoredColumnsWarning')"
      :message="$t('admin.import.ignoredColumnsMessage', { columns: unmappedColumns.join(', ') })"
      icon="fa-solid fa-exclamation-triangle"
    />

    <!-- Required fields warning -->
    <Alert
      v-if="!isValid"
      variant="danger"
      :title="$t('admin.import.requiredFieldsWarning')"
      :message="$t('admin.import.requiredFieldsMessage')"
      icon="fa-solid fa-exclamation-circle"
    />

    <!-- Password handling section -->
    <div class="flex flex-col gap-4">
      <!-- Info alert when no password column detected -->
      <Alert
        v-if="!hasPasswordColumn"
        variant="info"
        :title="$t('admin.import.noPasswordColumnTitle')"
        :message="$t('admin.import.noPasswordColumnMessage')"
        icon="fa-solid fa-circle-info"
      />

      <!-- Password mode selection when password column exists -->
      <div v-if="hasPasswordColumn" class="bg-base-200 rounded-xl p-4">
        <p class="text-sage-700 dark:text-sage-200 mb-3 font-medium">
          {{ $t('admin.import.passwordHandling') }}
        </p>

        <div class="flex flex-col gap-3">
          <!-- Option 1: Use from CSV -->
          <label class="flex cursor-pointer items-start gap-3">
            <input
              type="radio"
              name="password-mode"
              :checked="!generatePasswords"
              class="text-primary focus:ring-primary mt-1 h-4 w-4"
              @change="$emit('update:generatePasswords', false)"
            />
            <div>
              <p class="text-sage-700 dark:text-sage-200 font-medium">
                {{ $t('admin.import.usePasswordsFromFile') }}
              </p>
              <p class="text-sage-500 dark:text-sage-400 text-sm">
                {{ $t('admin.import.usePasswordsFromFileHint') }}
              </p>
            </div>
          </label>

          <!-- Option 2: Generate all -->
          <label class="flex cursor-pointer items-start gap-3">
            <input
              type="radio"
              name="password-mode"
              :checked="generatePasswords"
              class="text-primary focus:ring-primary mt-1 h-4 w-4"
              @change="$emit('update:generatePasswords', true)"
            />
            <div>
              <p class="text-sage-700 dark:text-sage-200 font-medium">
                {{ $t('admin.import.generateAllPasswords') }}
              </p>
              <p class="text-sage-500 dark:text-sage-400 text-sm">
                {{ $t('admin.import.generateAllPasswordsHint') }}
              </p>
            </div>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Alert } from '@owlint/feathers-vue'
import type { ColumnMapping } from '@/types/user-import'
import type { TargetField } from '@/composables/useColumnMapper'

interface Props {
  /** Column mappings */
  mappings: ColumnMapping[]
  /** Whether to generate random passwords */
  generatePasswords: boolean
  /** Headers from the parsed file */
  headers: string[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:mappings': [mappings: ColumnMapping[]]
  'update:generatePasswords': [value: boolean]
}>()

useI18n()

/**
 * Check if all required fields are mapped
 */
const isValid = computed(() => {
  const mappedFields = new Set(
    props.mappings.filter((m) => m.targetField !== null).map((m) => m.targetField),
  )
  return mappedFields.has('username') && mappedFields.has('email')
})

/**
 * Check if password column is mapped
 */
const hasPasswordColumn = computed(() => {
  return props.mappings.some((m) => m.targetField === 'password')
})

/**
 * Get list of unmapped columns
 */
const unmappedColumns = computed(() => {
  return props.mappings.filter((m) => m.targetField === null).map((m) => m.csvColumn)
})

/**
 * Check if a field is already used by another column
 */
function isFieldUsed(field: TargetField, excludeColumn: string): boolean {
  return props.mappings.some((m) => m.targetField === field && m.csvColumn !== excludeColumn)
}

/**
 * Handle mapping change from dropdown
 */
function handleMappingChange(csvColumn: string, value: string): void {
  const targetField: TargetField = value === 'ignore' ? null : (value as TargetField)

  // Clone mappings and update
  const newMappings = props.mappings.map((m) => {
    if (m.csvColumn === csvColumn) {
      return {
        ...m,
        targetField,
        isRequired: targetField === 'username' || targetField === 'email',
      }
    }

    // If another column had this field, clear it
    if (targetField !== null && m.targetField === targetField) {
      return {
        ...m,
        targetField: null,
        isRequired: false,
      }
    }

    return m
  })

  emit('update:mappings', newMappings)
}

// Enforce password generation rules based on password column detection
watch(
  hasPasswordColumn,
  (hasPassword) => {
    if (!hasPassword) {
      // No password column mapped - must generate all passwords (forced)
      emit('update:generatePasswords', true)
    } else if (props.generatePasswords) {
      // Password column exists - default to using passwords from file
      emit('update:generatePasswords', false)
    }
  },
  { immediate: true },
)
</script>
