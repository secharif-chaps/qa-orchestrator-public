<template>
  <div class="max-w-4xl mx-auto py-8 px-4">
    <!-- Header -->
    <div class="text-center mb-8">
      <div class="flex justify-center mb-6">
        <img
          src="@/assets/chapse/head.svg"
          alt="Chapse Assistant"
          class="h-24 w-auto object-contain"
          loading="lazy"
        />
      </div>
      <h1 class="text-3xl font-bold text-primary mb-3">
        {{ $t('aiPreferences.setup.title') }}
      </h1>
      <p class="text-base text-secondary max-w-2xl mx-auto">
        {{ $t('aiPreferences.setup.description') }}
      </p>
    </div>

    <!-- Setup Form -->
    <div class="bg-base-200 rounded-card border border-primary-stroke p-8 shadow-shadow-2">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Role Field -->
        <Input
          id="role"
          v-model="form.role"
          :label="$t('aiPreferences.setup.fields.role.label')"
          :placeholder="$t('aiPreferences.setup.fields.role.placeholder')"
          :error="errors.role"
          icon="fa-user-tie"
          required
        />

        <!-- Goals Field -->
        <div class="space-y-2">
          <label for="goals" class="block text-sm font-medium">
            {{ $t('aiPreferences.setup.fields.goals.label') }}
            <span class="text-warning ml-1">*</span>
          </label>
          <textarea
            id="goals"
            v-model="form.goals_text"
            :placeholder="$t('aiPreferences.setup.fields.goals.placeholder')"
            :class="[
              'w-full px-4 py-3 border rounded-lg transition-all duration-200',
              'focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-200',
              'dark:focus:ring-primary-700 dark:focus:border-primary-700',
              'bg-base-100 border-primary-stroke placeholder:text-secondary/60',
              'dark:placeholder:text-sage-300 resize-none',
              errors.goals_text ? 'border-warning focus:border-warning focus:ring-warning/20' : '',
            ]"
            rows="4"
            required
            maxlength="2000"
          ></textarea>
          <div class="flex justify-between items-center">
            <div v-if="errors.goals_text" class="flex items-center gap-2 text-sm text-warning">
              <i class="fa fa-exclamation-circle text-xs"></i>
              <span>{{ errors.goals_text }}</span>
            </div>
            <div v-else class="text-xs text-secondary">
              {{ $t('aiPreferences.setup.fields.goals.helper') }}
            </div>
            <div class="text-xs text-secondary">
              {{ form.goals_text.length }}/2000
            </div>
          </div>
        </div>

        <!-- Desired Output Field -->
        <div class="space-y-2">
          <label for="desired-output" class="block text-sm font-medium">
            {{ $t('aiPreferences.setup.fields.desiredOutput.label') }}
            <span class="text-warning ml-1">*</span>
          </label>
          <textarea
            id="desired-output"
            v-model="form.desired_output_text"
            :placeholder="$t('aiPreferences.setup.fields.desiredOutput.placeholder')"
            :class="[
              'w-full px-4 py-3 border rounded-lg transition-all duration-200',
              'focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-200',
              'dark:focus:ring-primary-700 dark:focus:border-primary-700',
              'bg-base-100 border-primary-stroke placeholder:text-secondary/60',
              'dark:placeholder:text-sage-300 resize-none',
              errors.desired_output_text
                ? 'border-warning focus:border-warning focus:ring-warning/20'
                : '',
            ]"
            rows="4"
            required
            maxlength="2000"
          ></textarea>
          <div class="flex justify-between items-center">
            <div
              v-if="errors.desired_output_text"
              class="flex items-center gap-2 text-sm text-warning"
            >
              <i class="fa fa-exclamation-circle text-xs"></i>
              <span>{{ errors.desired_output_text }}</span>
            </div>
            <div v-else class="text-xs text-secondary">
              {{ $t('aiPreferences.setup.fields.desiredOutput.helper') }}
            </div>
            <div class="text-xs text-secondary">
              {{ form.desired_output_text.length }}/2000
            </div>
          </div>
        </div>

        <!-- Documentation Field (Optional) -->
        <div class="space-y-2">
          <label for="documentation" class="block text-sm font-medium">
            {{ $t('aiPreferences.setup.fields.documentation.label') }}
            <span class="text-sm font-normal text-secondary ml-2">({{
              $t('aiPreferences.setup.optional')
            }})</span>
          </label>
          <textarea
            id="documentation"
            v-model="form.documentation_text"
            :placeholder="$t('aiPreferences.setup.fields.documentation.placeholder')"
            class="w-full px-4 py-3 border rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-200 dark:focus:ring-primary-700 dark:focus:border-primary-700 bg-base-100 border-primary-stroke placeholder:text-secondary/60 dark:placeholder:text-sage-300 resize-none"
            rows="4"
            maxlength="5000"
          ></textarea>
          <div class="flex justify-between items-center">
            <div class="text-xs text-secondary">
              {{ $t('aiPreferences.setup.fields.documentation.helper') }}
            </div>
            <div class="text-xs text-secondary">
              {{ form.documentation_text?.length || 0 }}/5000
            </div>
          </div>
        </div>

        <!-- Success Message -->
        <Alert
          v-if="successMessage"
          variant="success"
          :title="$t('aiPreferences.setup.success.title')"
          :description="successMessage"
          icon="fa-check-circle"
        />

        <!-- Error Message -->
        <Alert
          v-if="errorMessage"
          variant="danger"
          :title="$t('aiPreferences.setup.error.title')"
          :description="errorMessage"
          icon="fa-exclamation-circle"
        />

        <!-- Form Actions -->
        <div class="flex justify-end gap-3 pt-4">
          <Button
            variant="secondary"
            :label="$t('common.actions.cancel')"
            @click="handleCancel"
            :disabled="isSaving"
          />
          <Button
            type="submit"
            variant="primary"
            :label="$t('aiPreferences.setup.actions.save')"
            :loading="isSaving"
            icon="fa fa-check"
          />
        </div>
      </form>
    </div>

    <!-- Help Section -->
    <div class="mt-8 bg-info-light border border-info-stroke rounded-lg p-6">
      <div class="flex gap-4">
        <div class="flex-shrink-0">
          <i class="fa fa-lightbulb text-2xl text-info-light-content"></i>
        </div>
        <div>
          <h3 class="font-semibold text-base text-info-light-content mb-2">
            {{ $t('aiPreferences.setup.help.title') }}
          </h3>
          <ul class="space-y-2 text-sm text-info-light-content">
            <li class="flex items-start gap-2">
              <i class="fa fa-check text-xs mt-1"></i>
              <span>{{ $t('aiPreferences.setup.help.tip1') }}</span>
            </li>
            <li class="flex items-start gap-2">
              <i class="fa fa-check text-xs mt-1"></i>
              <span>{{ $t('aiPreferences.setup.help.tip2') }}</span>
            </li>
            <li class="flex items-start gap-2">
              <i class="fa fa-check text-xs mt-1"></i>
              <span>{{ $t('aiPreferences.setup.help.tip3') }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { saveAiPreferences } from '@/api/ai-preferences'
import { Alert, Button, Input } from '@owlint/feathers-vue'
import type { AiPreferencesCreate } from '@/types/ai-preferences'

const router = useRouter()

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
    errors.role = 'Role is required'
    isValid = false
  } else if (form.role.length > 255) {
    errors.role = 'Role must be less than 255 characters'
    isValid = false
  }

  // Validate goals
  if (!form.goals_text.trim()) {
    errors.goals_text = 'Goals are required'
    isValid = false
  } else if (form.goals_text.length > 2000) {
    errors.goals_text = 'Goals must be less than 2000 characters'
    isValid = false
  }

  // Validate desired output
  if (!form.desired_output_text.trim()) {
    errors.desired_output_text = 'Desired output is required'
    isValid = false
  } else if (form.desired_output_text.length > 2000) {
    errors.desired_output_text = 'Desired output must be less than 2000 characters'
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
    errorMessage.value = 'Please fix the errors in the form'
    return
  }

  try {
    isSaving.value = true

    // Save preferences
    await saveAiPreferences(form)

    // Show success message
    successMessage.value =
      'Your AI preferences have been saved successfully. Quick actions will now be personalized based on your role and goals.'

    // Redirect after 2 seconds
    setTimeout(() => {
      router.push({ name: '/(home)' })
    }, 2000)
  } catch (error: any) {
    console.error('Failed to save AI preferences:', error)

    if (error.status === 401) {
      errorMessage.value = 'You must be logged in to save AI preferences'
    } else {
      errorMessage.value =
        error.message || 'Failed to save AI preferences. Please try again.'
    }
  } finally {
    isSaving.value = false
  }
}

/**
 * Handle cancel action
 */
function handleCancel() {
  router.push({ name: '/(home)' })
}
</script>

<route lang="yaml">
meta:
  requiresAuth: true
  title: 'AI Preferences Setup'
</route>
