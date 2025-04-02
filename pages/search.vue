<template>
  <div class="space-y-4" data-cy="company-search-page">
    <div class="card flex items-center space-x-4">
      <OIcon
        icon="fa-search"
        type="secondary"
      ></OIcon>
      <h1 class="text-2xl font-extrabold">New company</h1>
    </div>

    <Card title="Company identity">
      <div class="space-y-2">
        <OInput
          id="company"
          v-model="company"
          placeholder="Sephora"
          label="Company name"
          :error="companyError"
          data-cy="company-name-input"
        ></OInput>

        <OInput
          id="website"
          v-model="website"
          placeholder="https://www.sephora.fr"
          label="Website"
          :error="websiteError"
          data-cy="website-input"
        ></OInput>
      </div>
    </Card>

    <Card title="Advanced search"> </Card>

    <Card>
      <div class="flex items-center justify-between">
        <div>
          <span class="font-bold text-slate-500">
            <span class="text-red-600"> * </span>mandatory fields to start
            search
          </span>
        </div>
        <div class="flex space-x-2">
          <OButton
            label="Delete Data"
            type="tertiary"
            :disabled="pending"
            data-cy="delete-data-button"
            @click="resetData()"
          />
          <OButton
            :loading="pending"
            :disabled="pending || hasErrors"
            @click="startSearch()"
            label="Launch Search"
            data-cy="launch-search-button"
          />
        </div>
      </div>
    </Card>
  </div>
</template>

<script lang="ts" setup>
import { OButton, OIcon, OInput } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'

const company = ref('')
const website = ref('')
const companyError = ref('')
const websiteError = ref('')

const { generate, generateTimeline, findProducts } = useAgent()

const companyStore = useCompanyStore()
const router = useRouter()

// Computed property to check if there are any validation errors
const hasErrors = computed(() => {
  return !!companyError.value || !!websiteError.value
})

// Validate website URL format
const validateWebsite = (url: string) => {
  try {
    new URL(url)
    return true
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
    companyError.value = 'Company name must be at least 2 characters long'
  }

  // Validate website URL
  if (newWebsite && !validateWebsite(newWebsite)) {
    websiteError.value = 'Please enter a valid URL (e.g., https://www.example.com)'
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

// Handle the search and redirection as soon as we get the company name
const startSearch = async () => {
  // Validate inputs before proceeding
  if (!validateCompany(company.value)) {
    companyError.value = 'Company name must be at least 2 characters long'
    return
  }

  if (!validateWebsite(website.value)) {
    websiteError.value = 'Please enter a valid URL (e.g., https://www.example.com)'
    return
  }

  pending.value = true

  try {
    const trimmedCompany = company.value.trim()
    generate(trimmedCompany, website.value)
    generateTimeline(trimmedCompany)
    findProducts(trimmedCompany)

    // Redirect to the company page
    router.push(`/cards/${trimmedCompany}`)
  } catch (error) {
    // Handle any unexpected errors during the search process
    console.error('Error during search:', error)
    // You might want to show a toast or error message to the user here
  } finally {
    pending.value = false
  }
}
</script>
