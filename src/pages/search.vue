<template>
  <div class="max-w-4xl mx-auto space-y-6" data-cy="company-search-page">
    <!-- Page Header -->
    <div class="space-y-2">
      <h1 class="text-3xl font-semibold">{{ $t('search.title') }}</h1>
      <p class="text-secondary">{{ $t('search.companyIdentity') }}</p>
    </div>

    <!-- Search Form Card -->
    <div class="bg-bg1 rounded-lg p-6" :title="$t('search.companyIdentity')">
      <form @submit.prevent="startSearch" class="space-y-6">
        <!-- Form Fields -->
        <div class="space-y-4">
          <OInput
            id="company"
            v-model="company"
            :placeholder="$t('search.fields.companyName.placeholder')"
            :error="companyError"
            data-cy="company-name-input"
            required
            :label="$t('search.fields.companyName.label')"
            icon="fas fa-building"
          />

          <OInput
            id="website"
            v-model="website"
            :placeholder="$t('search.fields.website.placeholder')"
            :error="websiteError"
            data-cy="website-input"
            required
            :label="$t('search.fields.website.label')"
            icon="fas fa-globe"
          />
        </div>

        <!-- Help Text -->
        <div class="bg-bg1 border border-slate-200 dark:border-slate-600 rounded-lg p-4">
          <div class="flex items-start space-x-3">
            <i class="fas fa-info-circle text-primary mt-0.5"></i>
            <div>
              <p class="text-sm font-medium text-secondary">{{ $t('search.mandatoryFields') }}</p>
              <p class="text-xs text-secondary mt-1">
                Provide both company name and website to start the search process.
              </p>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between pt-4">
          <OButton
            :label="$t('search.actions.deleteData')"
            type="secondary"
            color="slate"
            icon="fas fa-trash"
            :disabled="mutationLoading || (!company.trim() && !website.trim())"
            @click="resetData()"
          />
          <OButton
            :label="$t('search.actions.launchSearch')"
            type="primary"
            icon="fas fa-search"
            :loading="mutationLoading"
            :disabled="mutationLoading || !isFormValid"
            @click="submit"
          />
        </div>
      </form>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.create
</route>

<script lang="ts" setup>
import { OButton, OIcon, OInput } from '@owlint/feathers-vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useCreateCompany } from '@/mutations/companies'

const { t } = useI18n()

const company = ref('')
const website = ref('')
const companyError = ref('')
const websiteError = ref('')

const { isLoading: mutationLoading, mutateAsync } = useCreateCompany()

// Computed property to check if there are any validation errors or missing required fields
const hasErrors = computed(() => {
  return !!companyError.value || !!websiteError.value
})

// Computed property to check if both required fields are filled
const isFormValid = computed(() => {
  return company.value.trim().length > 0 && website.value.trim().length > 0 && !hasErrors.value
})

// Validate website URL format
const validateWebsite = (url: string) => {
  if (!url.trim()) return false

  try {
    // Add https:// if no protocol is provided
    const urlToTest = url.includes('://') ? url : `https://${url}`
    const parsedUrl = new URL(urlToTest)

    // Check if it's a valid HTTP/HTTPS URL
    return parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:'
  } catch {
    return false
  }
}

// Validate company name
const validateCompany = (name: string) => {
  return name.trim().length >= 2
}

// Watch for changes to validate inputs
watch([company, website], ([newCompany, newWebsite]) => {
  // Reset errors when inputs change
  companyError.value = ''
  websiteError.value = ''

  // Validate company name
  if (newCompany && !validateCompany(newCompany)) {
    companyError.value = t('search.fields.companyName.error')
  }

  // Validate website URL
  if (newWebsite && !validateWebsite(newWebsite)) {
    websiteError.value = t('search.fields.website.error')
  }
})

// Reset the form data
const resetData = () => {
  company.value = ''
  website.value = ''
  companyError.value = ''
  websiteError.value = ''
}

const router = useRouter()

// Handle the search and redirection as soon as we get the company name
const submit = async () => {
  // Validate inputs before proceeding
  console.log('startSearch', company.value, website.value)

  if (!validateCompany(company.value)) {
    companyError.value = t('search.fields.companyName.error')
    return
  }

  if (!validateWebsite(website.value)) {
    websiteError.value = t('search.fields.website.error')
    return
  }

  try {
    const trimmedCompany = company.value.trim()
    // Ensure website has protocol
    const websiteUrl = website.value.includes('://') ? website.value : `https://${website.value}`

    const newCompany = await mutateAsync({
      name: trimmedCompany,
      website: websiteUrl,
    })

    router.push(`/companies/${newCompany.id}`)
  } catch (error: any) {
    // Handle any unexpected errors during the search process
    console.error('Error during search:', error)

    // Display user-friendly error message
    if (error?.message) {
      // Extract meaningful error message
      if (error.message.includes('Validation error')) {
        companyError.value = 'Invalid company name format'
        websiteError.value = 'Invalid website URL format'
      } else if (error.message.includes('Invalid input')) {
        companyError.value = 'Please check your company name'
        websiteError.value = 'Please check your website URL'
      } else if (error.message.includes('unauthorized') || error.message.includes('401')) {
        // Authentication error - will be handled by navigateTo('/login') in API service
      } else {
        // Generic error
        companyError.value = 'An error occurred while creating the company'
      }
    } else {
      companyError.value = 'Network error - please try again'
    }
  }
}
</script>
