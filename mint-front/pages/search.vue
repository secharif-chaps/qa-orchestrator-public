<template>
  <div class="max-w-4xl mx-auto space-y-6" data-cy="company-search-page">
    <!-- Page Header -->
    <div class="text-center space-y-2">
      <h1 class="text-3xl font-semibold ">{{ $t('search.title') }}</h1>
      <p class="text-secondary">{{ $t('search.companyIdentity') }}</p>
    </div>

    <!-- Search Form Card -->
    <Card :title="$t('search.companyIdentity')">
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
            :disabled="pending || (!company.trim() && !website.trim())"
            data-cy="delete-data-button"
            @click="resetData()"
          />
          <OButton
            :label="$t('search.actions.launchSearch')"
            type="primary"
            icon="fas fa-search"
            :loading="pending"
            :disabled="pending || !isFormValid"
            data-cy="launch-search-button"
            submit
          />
        </div>
      </form>
    </Card>
  </div>
</template>

<script lang="ts" setup>
import { OButton, OIcon, OInput } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const company = ref('')
const website = ref('')
const companyError = ref('')
const websiteError = ref('')

const companyStore = useCompanyStore()

// Computed property to check if there are any validation errors or missing required fields
const hasErrors = computed(() => {
  return !!companyError.value || !!websiteError.value
})

// Computed property to check if both required fields are filled
const isFormValid = computed(() => {
  return company.value.trim().length > 0 && website.value.trim().length > 0 && !hasErrors.value
})

// Computed property to check if there's any data to delete
const hasData = computed(() => {
  return company.value.trim().length > 0 || website.value.trim().length > 0
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

const pending = ref(false)
const router = useRouter()
// Handle the search and redirection as soon as we get the company name
const startSearch = async () => {
  // Validate inputs before proceeding
  if (!validateCompany(company.value)) {
    companyError.value = t('search.fields.companyName.error')
    return
  }

  if (!validateWebsite(website.value)) {
    websiteError.value = t('search.fields.website.error')
    return
  }

  pending.value = true

  try {
    const trimmedCompany = company.value.trim().toLowerCase()
    // Ensure website has protocol
    const websiteUrl = website.value.includes('://') ? website.value : `https://${website.value}`
    
    const newCompany = await companyStore.createCompany({
      name: trimmedCompany,
      website: websiteUrl
    })
    router.push(`/companies/${newCompany.id}`)
  } catch (error) {
    // Handle any unexpected errors during the search process
    console.error('Error during search:', error)
    // You might want to show a toast or error message to the user here
  } finally {
    pending.value = false
  }
}
</script>
