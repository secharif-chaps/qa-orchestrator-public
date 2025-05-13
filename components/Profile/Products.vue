<template>
  <Card>
    <div class="flex flex-col gap-4 ">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-box-open"></i>
          <span> Product and services </span>
        </h3>
      </div>

      <!-- Product Range - individual property loading -->
      <div>Product Range</div>
      <div>
        <span class="text-sm text-secondary">
          {{
            (
              company?.products?.range.map(
                (p) => p.value
              ) || []
            ).join(', ') || 'Not found'
          }}
        </span>
      </div>

      <!-- Partner Brands - individual property loading -->
      <div>Partner Brand</div>
      <div class="pl-4"
      >
        <ul class="list-disc">
          <li
            class="text-secondary space-x-2"
            v-for="brand in company?.products?.partnerBrands ||
            []"
          >
            <span class="text-sm">
              {{ brand.value }}
            </span>
            <Source :sourced-value="company?.profile?.group_name" />
          </li>
          <li
            v-if="company?.products?.partnerBrands?.length === 0"
            class="text-sm text-secondary"
          >
            Not found
          </li>
        </ul>
      </div>


      <!-- Private Labels - individual property loading -->
      <div>{{ company?.name }} private label</div>
      <div class="pl-4"
       
      >
        <ul class="list-disc">
          <li
            class="space-x-2 text-secondary"
            v-for="brand in company?.products?.privateLabels ||
            []"
          >
            <span class="text-sm">
              {{ brand.value }}
            </span>
            <Source :sourced-value="company?.profile?.group_name" />
          </li>
          <li
            v-if="company?.products?.privateLabels?.length === 0"
            class="text-sm text-secondary"
          >
            Not found
          </li>
        </ul>
      </div>
   
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company } = useCompanyData()
</script>
