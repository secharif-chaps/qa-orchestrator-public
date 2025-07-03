<template>
  <LayoutsCompanyCard
    :title="$t('profile.title')"
    icon="fa-building"
    v-if="company"
  >
    <div class="flex flex-col gap-4">


      <div class="@container grid grid-cols-6 gap-2">
        <div class="@max-6xl:col-span-6 @min-6xl:col-span-4">
          <Profile />
        </div>

        <div class="@max-6xl:col-span-6 @min-6xl:col-span-2 space-y-2">
          <div class="flex flex-col h-full gap-2">
            <ProfileGroup />
            <ProfileBusinessLine />
          </div>
        </div>

        <div class="@max-6xl:col-span-6 @min-6xl:col-span-3 space-y-2 flex flex-col">
          <!-- Products and services section -->
          <ProfileProducts :title="$t('profile.sections.products.title')" />

          <!-- Target audience section -->
          <ProfileTarget :title="$t('profile.sections.target.title')" />

          <!-- CSR section -->
          <ProfileCSR :title="$t('profile.sections.csr.title')" />
        </div>
        <div
          class="@max-6xl:col-span-6 @min-6xl:col-span-3 col-span-6 lg:col-span-3 flex flex-col space-y-2"
        >
          <div class="grid grid-cols-3 gap-2">
            <!-- Key metrics - each can load independently -->
            <ProfileEstablishment :title="$t('profile.sections.metrics.establishment')" />
            <ProfileEmployees :title="$t('profile.sections.metrics.employees')" />
            <ProfileRevenue :title="$t('profile.sections.metrics.revenue')" />
          </div>
          <div class="flex flex-col h-full gap-2">
            <!-- Digital strategy section -->
            <ProfileStrategy :title="$t('profile.sections.digital.title')" />

            <!-- Recent news section -->
            <ProfileNews :title="$t('profile.sections.news')" />
          </div>
        </div>
      </div>
    </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { Profile } from '#components'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Set page metadata
useHead({
  title: `Mint - ${t('profile.title')}`,
  meta: [{ name: 'description', content: t('profile.title') }]
})

const { company, companyId, fetchCompany } = useCompanyData()

const router = useRouter()

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
  if (!companyId.value) {
    router.push('/companies')
  }
})
</script>
