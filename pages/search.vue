<template>
  <div class="space-y-4">
    <div class="card flex items-center space-x-4">
      <OIcon
        icon="fa-search"
        type="secondary"
      ></OIcon>
      <h1 class="text-2xl font-bold">New company</h1>
    </div>

    <Card title="Company identity">
      <div class="space-y-2">
        <OInput
          id="company"
          v-model="company"
          placeholder="Sephora"
          label="Company name"
        ></OInput>

        <OInput
          id="website"
          v-model="website"
          placeholder="https://www.sephora.fr"
          label="Website"
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
          />
          <OButton
            :loading="pending"
            @click="startSearch()"
            label="Launch Search"
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

const { generate, generateTimeline, pending } = useAgent()

const companyStore = useCompanyStore()
const router = useRouter()

// Reset the form data
const resetData = () => {
  company.value = ''
  website.value = ''
}

// Handle the search and redirection as soon as we get the company name
const startSearch = async () => {
  if (!company.value || !website.value) return

  // Start both requests but don't wait for them to complete
  // First argument is for the company data generation
  generate(company.value, website.value, (companyName) => {
    // As soon as we get the company name, redirect to the company page
    if (companyName) {
      console.log('Company name received, starting timeline generation:', companyName);
      
      // Start the timeline generation in parallel
      // We don't await this since we want to redirect immediately
      // The pending state will be tracked by the useAgent composable
      generateTimeline(companyName);
      
      // Redirect to the company page
      router.push(`/cards/${companyName}`);
    }
  })

  // No need for graph generation here

  // We don't need to wait for the results here since we'll redirect on the callback
}
</script>
