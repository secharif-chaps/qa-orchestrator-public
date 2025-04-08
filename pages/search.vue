<template>
  <div class="space-y-4" data-cy="company-search-page">
    <div class="card flex items-center space-x-4">
      <OIcon
        icon="fa-search"
        type="secondary"
      ></OIcon>
      <h1 class="text-2xl font-extrabold">{{ $t('search.title') }}</h1>
    </div>

    <Card :title="$t('search.companyIdentity')">
      <div class="space-y-2">
        <OInput
          id="company"
          v-model="company"
          :placeholder="$t('search.fields.companyName.placeholder')"
          :label="$t('search.fields.companyName.label')"
          :error="companyError"
          data-cy="company-name-input"
        ></OInput>

        <OInput
          id="website"
          v-model="website"
          :placeholder="$t('search.fields.website.placeholder')"
          :label="$t('search.fields.website.label')"
          :error="websiteError"
          data-cy="website-input"
        ></OInput>
      </div>
    </Card>

    <Card :title="$t('search.advancedSearch')"> </Card>

    <Card>
      <div class="flex items-center justify-between">
        <div>
          <span class="font-bold text-slate-500">
            <span class="text-red-600"> * </span>{{ $t('search.mandatoryFields') }}
          </span>
        </div>
        <div class="flex space-x-2">
          <OButton
            :label="$t('search.actions.deleteData')"
            type="tertiary"
            :disabled="pending"
            data-cy="delete-data-button"
            @click="resetData()"
          />
          <OButton
            :loading="pending"
            :disabled="pending || hasErrors"
            @click="startSearch()"
            :label="$t('search.actions.launchSearch')"
            data-cy="launch-search-button"
          />
        </div>
      </div>
    </Card>
  </div>
</template>

<script lang="ts" setup>
import { OButton, OIcon, OInput } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import { useCompanyStore } from '~/stores/company'

const { t } = useI18n()

const company = ref('')
const website = ref('')
const companyError = ref('')
const websiteError = ref('')

const { findProfile, findTimeline, findProducts, findJobOffers } = useAgent()

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
    // Start with profile
    findProfile(trimmedCompany, website.value)
    
    // Wait 3s before timeline
    await new Promise(resolve => setTimeout(resolve, 3000))
    findTimeline(trimmedCompany)
    
    // Wait 3s before products
    await new Promise(resolve => setTimeout(resolve, 3000))
    findProducts(trimmedCompany)
    
    // Wait 3s before job offers
    await new Promise(resolve => setTimeout(resolve, 3000))
    findJobOffers(trimmedCompany)

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
