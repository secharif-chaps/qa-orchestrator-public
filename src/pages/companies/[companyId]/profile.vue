<template>
  <div class="flex flex-col gap-4">
    <div class="grid grid-cols-6 gap-2">
      <div class="col-span-6">
        <ProfileHeader />
      </div>

      <div class="col-span-6 space-y-2">
        <div class="flex flex-col h-full gap-2">
          <ProfileGroup />
          <ProfileBusinessLine />
        </div>
      </div>

      <div class="col-span-12 grid grid-cols-3 gap-2">
        <ProfileEstablishment />
        <ProfileEmployees />
        <ProfileRevenue />
      </div>

      <div class="col-span-12 space-y-2 flex flex-col">
        <ProfileProducts />
        <!-- <ProfileTarget /> -->
        <ProfileCSR />

        <div class="flex flex-col h-full gap-2">
          <ProfileStrategy />
          <ProfileNews />
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.view
</route>

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

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))
</script>
