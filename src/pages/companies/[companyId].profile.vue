<template>
  <CompanyCard :title="$t('profile.title')" icon="fa-building" v-if="company">
    <div class="flex flex-col gap-4">
      <div class="@container grid grid-cols-6 gap-2">
        <div class="@max-6xl:col-span-6 @min-6xl:col-span-4">
          <ProfileHeader />
        </div>

        <div class="@max-6xl:col-span-6 @min-6xl:col-span-2 space-y-2">
          <div class="flex flex-col h-full gap-2">
            <ProfileGroup />
            <ProfileBusinessLine />
          </div>
        </div>

        <div class="@max-6xl:col-span-6 @min-6xl:col-span-3 space-y-2 flex flex-col">
          <!-- Products and services section -->
          <ProfileProducts />

          <!-- Target audience section -->
          <ProfileTarget />

          <!-- CSR section -->
          <ProfileCSR />
        </div>
        <div
          class="@max-6xl:col-span-6 @min-6xl:col-span-3 col-span-6 lg:col-span-3 flex flex-col space-y-2"
        >
          <div class="grid grid-cols-3 gap-2">
            <!-- Key metrics - each can load independently -->
            <ProfileEstablishment />
            <ProfileEmployees />
            <ProfileRevenue />
          </div>
          <div class="flex flex-col h-full gap-2">
            <!-- Digital strategy section -->
            <ProfileStrategy />

            <!-- Recent news section -->
            <ProfileNews />
          </div>
        </div>
      </div>
    </div>
  </CompanyCard>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import ProfileHeader from '@/components/company/profile/ProfileHeader.vue'
import ProfileGroup from '@/components/company/profile/ProfileGroup.vue'
import ProfileBusinessLine from '@/components/company/profile/ProfileBusinessLine.vue'
import ProfileProducts from '@/components/company/profile/ProfileProducts.vue'
import ProfileTarget from '@/components/company/profile/ProfileTarget.vue'
import ProfileCSR from '@/components/company/profile/ProfileCSR.vue'
import ProfileEstablishment from '@/components/company/profile/ProfileEstablishment.vue'
import ProfileEmployees from '@/components/company/profile/ProfileEmployees.vue'
import ProfileRevenue from '@/components/company/profile/ProfileRevenue.vue'
import ProfileStrategy from '@/components/company/profile/ProfileStrategy.vue'
import ProfileNews from '@/components/company/profile/ProfileNews.vue'
import CompanyCard from '@/components/company/CompanyCard.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))
</script>
