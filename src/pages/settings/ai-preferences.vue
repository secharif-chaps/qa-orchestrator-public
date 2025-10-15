<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-2xl font-bold">{{ $t('aiPreferences.settings.title') }}</h2>
      <p class="text-secondary mt-2">{{ $t('aiPreferences.settings.description') }}</p>
      <p v-if="lastUpdated" class="text-sm text-secondary mt-1">
        {{ $t('aiPreferences.settings.lastUpdated', { date: lastUpdated }) }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
    </div>

    <!-- Not Configured State -->
    <div v-else-if="!hasPreferences" class="bg-base-200 rounded-card border border-primary-stroke p-8">
      <div class="flex flex-col items-center text-center gap-4">
        <img
          src="@/assets/chapse/head.svg"
          alt="Chapse Assistant"
          class="h-20 w-auto object-contain"
          loading="lazy"
        />
        <div>
          <h3 class="text-xl font-semibold mb-2">
            {{ $t('aiPreferences.settings.notConfigured') }}
          </h3>
          <p class="text-secondary mb-4">
            Set up your AI preferences to enable personalized quick actions and recommendations.
          </p>
        </div>
        <Button
          variant="primary"
          icon="fa fa-magic"
          @click="goToSetup"
        >
          Set Up AI Preferences
        </Button>
      </div>
    </div>

    <!-- Edit Form -->
    <div v-else class="bg-base-200 rounded-card border border-primary-stroke p-8">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Role Field -->
        <Input
          id="role"
          v-model="form.role"
          :label="$t('aiPreferences.setup.fields.role.label')"
          :placeholder="$t('aiPreferences.setup.fields.role.placeholder')"
          :error="errors.role"
          :helper="$t('aiPreferences.setup.fields.role.helper')"
          icon="fa fa-user-tie"
          required
          clearable
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
          :title="$t('aiPreferences.settings.success.title')"
          :message="successMessage"
          icon="fa fa-check-circle"
        />

        <!-- Error Message -->
        <Alert
          v-if="errorMessage"
          variant="error"
          :title="$t('aiPreferences.settings.error.title')"
          :message="errorMessage"
          icon="fa fa-exclamation-circle"
        />

        <!-- Form Actions -->
        <div class="flex justify-end gap-3 pt-4">
          <Button
            type="submit"
            variant="primary"
            :label="$t('aiPreferences.settings.actions.save')"
            :loading="isSaving"
            icon="fa fa-check"
          />
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { getAiPreferences, saveAiPreferences } from '@/api/ai-preferences'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import type { AiPreferencesCreate } from '@/types/ai-preferences'

const router = useRouter()

// Loading state
const isLoading = ref(true)
const hasPreferences = ref(false)

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
const lastUpdated = ref('')

/**
 * Load existing preferences
 */
async function loadPreferences() {
  try {
    isLoading.value = true
    const preferences = await getAiPreferences()

    if (preferences) {
      hasPreferences.value = true
      form.role = preferences.role
      form.goals_text = preferences.goals_text
      form.desired_output_text = preferences.desired_output_text
      form.documentation_text = preferences.documentation_text || ''

      // Format last updated date (would come from API in real implementation)
      const now = new Date()
      lastUpdated.value = now.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })
    } else {
      hasPreferences.value = false
    }
  } catch (error: any) {
    if (error.status === 404) {
      hasPreferences.value = false
    } else {
      console.error('Failed to load preferences:', error)
      errorMessage.value = 'Failed to load your preferences. Please try again.'
    }
  } finally {
    isLoading.value = false
  }
}

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
    successMessage.value = 'Your AI preferences have been updated successfully.'

    // Update last updated date
    const now = new Date()
    lastUpdated.value = now.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })

    // Clear success message after 3 seconds
    setTimeout(() => {
      successMessage.value = ''
    }, 3000)
  } catch (error: any) {
    console.error('Failed to update AI preferences:', error)

    if (error.status === 401) {
      errorMessage.value = 'You must be logged in to update AI preferences'
    } else {
      errorMessage.value =
        error.message || 'Failed to update AI preferences. Please try again.'
    }
  } finally {
    isSaving.value = false
  }
}

/**
 * Navigate to setup page
 */
function goToSetup() {
  router.push({ name: '/ai-preferences-setup' })
}

// Load preferences on mount
onMounted(() => {
  loadPreferences()
})
</script>

<route lang="yaml">
meta:
  requiresAuth: true
  title: 'AI Preferences'
</route>
