<template>
  <div class="flex flex-col gap-6">
    <!-- Loading State -->
    <div v-if="isLoading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
    </div>

    <!-- Not Configured State -->
    <div v-else-if="!hasPreferences" class="bg-base-100 rounded-card border border-primary-stroke p-8">
      <div class="flex flex-col items-center text-center gap-4">
        <img
          src="@/assets/chapse/head.svg"
          alt="Chapse Assistant"
          class="h-20 w-auto object-contain"
          loading="lazy"
        />
        <div class="flex flex-col gap-2">
          <h3 class="text-xl font-semibold">
            {{ $t('aiPreferences.settings.notConfigured') }}
          </h3>
          <p class="text-secondary">
            {{ $t('aiPreferences.settings.setUpDescription') }}
          </p>
        </div>
        <Button
          variant="primary"
          icon="fa fa-magic"
          :label="$t('aiPreferences.settings.setUpButton')"
          @click="goToSetup"
        />
      </div>
    </div>

    <!-- Edit Form -->
    <div v-else class="bg-base-100 rounded-card border border-primary-stroke p-6">
      <!-- Last Updated Info -->
      <div v-if="lastUpdated" class="flex items-center gap-2 text-sm text-secondary mb-6">
        <i class="fas fa-clock"></i>
        <span>{{ $t('aiPreferences.settings.lastUpdated', { date: lastUpdated }) }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="flex flex-col gap-6">
        <!-- Role Field -->
        <Input
          id="role"
          v-model="form.role"
          :label="$t('aiPreferences.setup.fields.role.label')"
          :placeholder="$t('aiPreferences.setup.fields.role.placeholder')"
          :error="errors.role"
          icon="fa fa-user-tie"
          required
        />

        <!-- Goals Field -->
        <div class="relative flex flex-col" >
          <label for="goals">
            {{ $t('aiPreferences.setup.fields.goals.label') }}
            <span class="text-warning ml-1">*</span>
          </label>
          <Textarea
            id="goals"
            v-model="form.goals_text"
            :label="$t('aiPreferences.setup.fields.goals.label')"
            :placeholder="$t('aiPreferences.setup.fields.goals.placeholder')"
            :error="errors.goals_text"
            :maxlength="2000"
            :rows="4"
            required
          />
        </div>

        <!-- Desired Output Field -->
        <div>
          <label for="desiredOutput">
            {{ $t('aiPreferences.setup.fields.desiredOutput.label') }}
            <span class="text-warning ml-1">*</span>
          </label>
          <Textarea
            id="desired-output"
            v-model="form.desired_output_text"
            :label="$t('aiPreferences.setup.fields.desiredOutput.label')"
            :placeholder="$t('aiPreferences.setup.fields.desiredOutput.placeholder')"
            :error="errors.desired_output_text"
            :maxlength="2000"
            :rows="4"
            required
          />
        </div>

        <!-- Documentation Field (Optional) -->
        <div>
          <label for="documentation">
            {{ $t('aiPreferences.setup.fields.documentation.label') }}
            <span class="text-sm font-normal text-secondary ml-2">({{
              $t('aiPreferences.setup.optional')
            }})</span>
          </label>
          <Textarea
            id="documentation"
            v-model="form.documentation_text"
            :label="$t('aiPreferences.setup.fields.documentation.label')"
            :placeholder="$t('aiPreferences.setup.fields.documentation.placeholder')"
            :maxlength="5000"
            :rows="4"
          />
        </div>
        <!-- Success Message -->
        <Alert
          v-if="successMessage"
          variant="success"
          :title="$t('aiPreferences.settings.success.title')"
          :description="successMessage"
          icon="fa fa-check-circle"
        />

        <!-- Error Message -->
        <Alert
          v-if="errorMessage"
          variant="danger"
          :title="$t('aiPreferences.settings.error.title')"
          :description="errorMessage"
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
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getAiPreferences, saveAiPreferences } from '@/api/ai-preferences'
import { Input, Textarea, Button, Alert } from '@owlint/feathers-vue'
import type { AiPreferencesCreate } from '@/types/ai-preferences'

const router = useRouter()
const { t } = useI18n()

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
      errorMessage.value = t('aiPreferences.settings.messages.loadError')
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
    errors.role = t('aiPreferences.setup.role.required')
    isValid = false
  } else if (form.role.length > 255) {
    errors.role = t('aiPreferences.setup.role.tooLong')
    isValid = false
  }

  // Validate goals
  if (!form.goals_text.trim()) {
    errors.goals_text = t('aiPreferences.setup.goals.required')
    isValid = false
  } else if (form.goals_text.length > 2000) {
    errors.goals_text = t('aiPreferences.setup.goals.tooLong')
    isValid = false
  }

  // Validate desired output
  if (!form.desired_output_text.trim()) {
    errors.desired_output_text = t('aiPreferences.setup.desiredOutput.required')
    isValid = false
  } else if (form.desired_output_text.length > 2000) {
    errors.desired_output_text = t('aiPreferences.setup.desiredOutput.tooLong')
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
    errorMessage.value = t('aiPreferences.setup.validation.formInvalid')
    return
  }

  try {
    isSaving.value = true

    // Save preferences
    await saveAiPreferences(form)

    // Show success message
    successMessage.value = t('aiPreferences.settings.success.message')

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
      errorMessage.value = t('aiPreferences.settings.messages.authError')
    } else {
      errorMessage.value =
        error.message || t('aiPreferences.settings.error.message')
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
