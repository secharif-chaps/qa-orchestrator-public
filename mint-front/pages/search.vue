<template>
  <div class="space-y-4 max-w-7xl mx-auto" data-cy="company-search-page">


    <Card :title="$t('search.title')">
      <div class="flex flex-col gap-4">
      <div class="space-y-6">
        <div class="space-y-2">
          <OInput
            id="company"
            v-model="company"
            :placeholder="$t('search.fields.companyName.placeholder')"
            :error="companyError"
            data-cy="company-name-input"
            required
            :label="$t('search.fields.companyName.label')"
          >
          </OInput>
        </div>

        <div class="space-y-2">
          <OInput
            id="website"
            v-model="website"
            :placeholder="$t('search.fields.website.placeholder')"
            :error="websiteError"
            data-cy="website-input"
            required
            :label="$t('search.fields.website.label')"
          >
          </OInput>

        </div>
      </div>
      <div class="w-full flex items-center justify-end">
        <div class="flex space-x-2">
          <OButton
            :label="$t('search.actions.deleteData')"
            type="tertiary"
            :disabled="pending || (!company.trim() && !website.trim())"
            data-cy="delete-data-button"
            @click="resetData()"
          />
          <OButton
            :loading="pending"
            :disabled="pending || !isFormValid"
            @click="startSearch()"
            :label="$t('search.actions.launchSearch')"
            data-cy="launch-search-button"
          />
        </div>
      </div>
    </div>
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
