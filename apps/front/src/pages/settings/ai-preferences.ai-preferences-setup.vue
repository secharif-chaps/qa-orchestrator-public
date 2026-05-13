<template>
  <div class="mx-auto max-w-224 px-4 py-8">
    <!-- Header -->
    <div class="mb-8 text-center">
      <div class="mb-6 flex justify-center">
        <img
          src="@/assets/chapse/head.svg"
          alt="Chapse Assistant"
          class="h-24 w-auto object-contain"
          loading="lazy"
        />
      </div>
      <h1 class="text-primary mb-3 text-3xl font-bold">
        {{ $t('settings.aiPreferences.setup.title') }}
      </h1>
      <p class="text-neutral-black-font mx-auto max-w-168 text-base">
        {{ $t('settings.aiPreferences.setup.description') }}
      </p>
    </div>

    <!-- Setup Form -->
    <div class="bg-primary-lightest rounded-card border-primary-lighter-stroke shadow-2 border p-8">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Role Field -->
        <Input
          id="role"
          v-model="form.role"
          :label="$t('settings.aiPreferences.setup.fields.role.label')"
          :placeholder="$t('settings.aiPreferences.setup.fields.role.placeholder')"
          :error="errors.role"
          icon="fa-user-tie"
          required
        />

        <!-- Goals Field -->
        <div class="space-y-2">
          <label for="goals" class="field-required block text-sm font-medium">
            {{ $t('settings.aiPreferences.setup.fields.goals.label') }}
          </label>
          <textarea
            id="goals"
            v-model="form.goals_text"
            :placeholder="$t('settings.aiPreferences.setup.fields.goals.placeholder')"
            :class="[
              'w-full rounded-sm border px-4 py-3 transition-all duration-200',
              'focus:ring-primary-200 focus:border-primary-200 focus:ring-2 focus:outline-none',
              'dark:focus:ring-primary-700 dark:focus:border-primary-700',
              'border-primary-lighter-stroke placeholder:text-neutral-black-font/60 bg-white',
              'dark:placeholder:text-sage-300 resize-none',
              errors.goals_text ? 'border-warning focus:border-warning focus:ring-warning/20' : '',
            ]"
            rows="4"
            required
            maxlength="2000"
          ></textarea>
          <div class="flex items-center justify-between">
            <div v-if="errors.goals_text" class="text-warning flex items-center gap-2 text-sm">
              <i class="fa fa-exclamation-circle text-xs"></i>
              <span>{{ errors.goals_text }}</span>
            </div>
            <div v-else class="text-neutral-black-font text-xs">
              {{ $t('settings.aiPreferences.setup.fields.goals.helper') }}
            </div>
            <div class="text-neutral-black-font text-xs">
              {{
                $t('common.countOfTotal', {
                  count: form.goals_text.length,
                  total: GOALS_MAX_LENGTH,
                })
              }}
            </div>
          </div>
        </div>

        <!-- Desired Output Field -->
        <div class="space-y-2">
          <label for="desired-output" class="block text-sm font-medium">
            {{ $t('settings.aiPreferences.setup.fields.desiredOutput.label') }}
          </label>
          <textarea
            id="desired-output"
            v-model="form.desired_output_text"
            :placeholder="$t('settings.aiPreferences.setup.fields.desiredOutput.placeholder')"
            :class="[
              'w-full rounded-sm border px-4 py-3 transition-all duration-200',
              'focus:ring-primary-200 focus:border-primary-200 focus:ring-2 focus:outline-none',
              'dark:focus:ring-primary-700 dark:focus:border-primary-700',
              'border-primary-lighter-stroke placeholder:text-neutral-black-font/60 bg-white',
              'dark:placeholder:text-sage-300 resize-none',
              errors.desired_output_text
                ? 'border-warning focus:border-warning focus:ring-warning/20'
                : '',
            ]"
            rows="4"
            required
            maxlength="2000"
          ></textarea>
          <div class="flex items-center justify-between">
            <div
              v-if="errors.desired_output_text"
              class="text-warning flex items-center gap-2 text-sm"
            >
              <i class="fa fa-exclamation-circle text-xs"></i>
              <span>{{ errors.desired_output_text }}</span>
            </div>
            <div v-else class="text-neutral-black-font text-xs">
              {{ $t('settings.aiPreferences.setup.fields.desiredOutput.helper') }}
            </div>
            <div class="text-neutral-black-font text-xs">
              {{
                $t('common.countOfTotal', {
                  count: form.desired_output_text.length,
                  total: DESIRED_OUTPUT_MAX_LENGTH,
                })
              }}
            </div>
          </div>
        </div>

        <!-- Documentation Field (Optional) -->
        <div class="space-y-2">
          <label for="documentation" class="block text-sm font-medium">
            {{ $t('settings.aiPreferences.setup.fields.documentation.label') }}
            <span class="text-neutral-black-font ml-2 text-sm font-normal">
              {{ $t('settings.aiPreferences.setup.optional') }}
            </span>
          </label>
          <textarea
            id="documentation"
            v-model="form.documentation_text"
            :placeholder="$t('settings.aiPreferences.setup.fields.documentation.placeholder')"
            class="focus:ring-primary-200 focus:border-primary-200 dark:focus:ring-primary-700 dark:focus:border-primary-700 border-primary-lighter-stroke placeholder:text-neutral-black-font/60 dark:placeholder:text-sage-300 w-full resize-none rounded-sm border bg-white px-4 py-3 transition-all duration-200 focus:ring-2 focus:outline-none"
            rows="4"
            maxlength="5000"
          ></textarea>
          <div class="flex items-center justify-between">
            <div class="text-neutral-black-font text-xs">
              {{ $t('settings.aiPreferences.setup.fields.documentation.helper') }}
            </div>
            <div class="text-neutral-black-font text-xs">
              {{
                $t('common.countOfTotal', {
                  count: form.documentation_text?.length || 0,
                  total: DOCUMENTATION_MAX_LENGTH,
                })
              }}
            </div>
          </div>
        </div>

        <!-- Success Message -->
        <Alert
          v-if="successMessage"
          variant="success"
          :title="$t('settings.aiPreferences.setup.success.title')"
          :description="successMessage"
          icon="fa-check-circle"
        />

        <!-- Error Message -->
        <Alert
          v-if="errorMessage"
          variant="danger"
          :title="$t('settings.aiPreferences.setup.error.title')"
          :description="errorMessage"
          icon="fa-exclamation-circle"
        />

        <!-- Form Actions -->
        <div class="flex justify-end gap-3 pt-4">
          <Button
            variant="secondary"
            :label="$t('common.cancel')"
            @click="handleCancel"
            :disabled="isSaving"
          />
          <Button
            type="submit"
            variant="primary"
            :label="$t('settings.aiPreferences.setup.actions.save')"
            :loading="isSaving"
            icon="fa-check"
          />
        </div>
      </form>
    </div>

    <!-- Help Section -->
    <div class="bg-info-light border-info-stroke mt-8 rounded-sm border p-6">
      <div class="flex gap-4">
        <div class="flex-shrink-0">
          <i class="fa fa-lightbulb text-info-light-content text-2xl"></i>
        </div>
        <div>
          <h3 class="text-info-light-content mb-2 text-base font-semibold">
            {{ $t('settings.aiPreferences.setup.help.title') }}
          </h3>
          <ul class="text-info-light-content space-y-2 text-sm">
            <li class="flex items-start gap-2">
              <i class="fa fa-check mt-1 text-xs"></i>
              <span>{{ $t('settings.aiPreferences.setup.help.tip1') }}</span>
            </li>
            <li class="flex items-start gap-2">
              <i class="fa fa-check mt-1 text-xs"></i>
              <span>{{ $t('settings.aiPreferences.setup.help.tip2') }}</span>
            </li>
            <li class="flex items-start gap-2">
              <i class="fa fa-check mt-1 text-xs"></i>
              <span>{{ $t('settings.aiPreferences.setup.help.tip3') }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { saveAiPreferences } from '@/api/ai-preferences'
import type { AiPreferencesCreate } from '@/types/ai-preferences'
import { Alert, Button, Input } from '@owlint/feathers-vue'
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const router = useRouter()
const { t } = useI18n()

const GOALS_MAX_LENGTH = 2000
const DESIRED_OUTPUT_MAX_LENGTH = 2000
const DOCUMENTATION_MAX_LENGTH = 5000

// Form state
const form = reactive<AiPreferencesCreate>({
  role: '',
  goals_text: '',
  desired_output_text: '',
  documentation_text: '',
})

// Validation errors
const errors = reactive({
  role: '',
  goals_text: '',
  desired_output_text: '',
})

// UI state
const isSaving = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

/**
 * Validate form fields
 */
function validateForm(): boolean {
  // Reset errors
  errors.role = ''
  errors.goals_text = ''
  errors.desired_output_text = ''

  let isValid = true

  // Validate role
  if (!form.role.trim()) {
    errors.role = t('settings.aiPreferences.setup.role.required')
    isValid = false
  } else if (form.role.length > 255) {
    errors.role = t('settings.aiPreferences.setup.role.tooLong')
    isValid = false
  }

  // Validate goals
  if (!form.goals_text.trim()) {
    errors.goals_text = t('settings.aiPreferences.setup.goals.required')
    isValid = false
  } else if (form.goals_text.length > 2000) {
    errors.goals_text = t('settings.aiPreferences.setup.goals.tooLong')
    isValid = false
  }

  // Validate desired output
  if (!form.desired_output_text.trim()) {
    errors.desired_output_text = t('settings.aiPreferences.setup.desiredOutput.required')
    isValid = false
  } else if (form.desired_output_text.length > 2000) {
    errors.desired_output_text = t('settings.aiPreferences.setup.desiredOutput.tooLong')
    isValid = false
  }

  return isValid
}

/**
 * Handle form submission
 */
async function handleSubmit() {
  // Clear previous messages
  successMessage.value = ''
  errorMessage.value = ''

  // Validate form
  if (!validateForm()) {
    errorMessage.value = t('settings.aiPreferences.setup.validation.formInvalid')
    return
  }

  try {
    isSaving.value = true

    // Save preferences
    await saveAiPreferences(form)

    // Show success message
    successMessage.value = t('settings.aiPreferences.setup.success.message')

    // Redirect after 2 seconds
    setTimeout(() => {
      router.push({ name: '/settings/ai-preferences' })
    }, 2000)
  } catch (error: unknown) {
    console.error('Failed to save AI preferences:', error)
    const httpError = error as { status?: number; message?: string }

    if (httpError.status === 401) {
      errorMessage.value = t('settings.aiPreferences.settings.messages.authError')
    } else {
      errorMessage.value = httpError.message || t('settings.aiPreferences.setup.error.message')
    }
  } finally {
    isSaving.value = false
  }
}

/**
 * Handle cancel action
 */
function handleCancel() {
  router.push({ name: '/settings/ai-preferences' })
}
</script>

<route lang="yaml">
meta:
  requiresAuth: true
  title: 'AI Preferences Setup'
</route>
