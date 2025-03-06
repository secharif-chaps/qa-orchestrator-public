<template>
  <Card>
    <div class="grid grid-cols-2 gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-box-open"></i>
          <span> Product and services </span>
        </h3>
      </div>

      <!-- Product Range - individual property loading -->
      <div>Product Range</div>
      <div v-if="hasPropertyBeenUpdated('products_and_services.product_range')">
        <span class="text-sm text-secondary">
          {{ (company?.products_and_services?.product_range || []).join(', ') }}
        </span>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading product range...
      </div>

      <!-- Partner Brands - individual property loading -->
      <div>Partner Brand</div>
      <div
        v-if="hasPropertyBeenUpdated('products_and_services.partner_brands')"
      >
        <ul class="list-disc">
          <li
            class="space-x-2 text-secondary"
            v-for="brand in company?.products_and_services?.partner_brands ||
            []"
          >
            <span class="text-sm">
              {{ brand }}
            </span>
          </li>
        </ul>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading partner brands...
      </div>

      <!-- Private Labels - individual property loading -->
      <div>{{ company?.profile?.name || companyName }} private label</div>
      <div
        v-if="hasPropertyBeenUpdated('products_and_services.private_labels')"
      >
        <ul class="list-disc">
          <li
            class="space-x-2 text-secondary"
            v-for="brand in company?.products_and_services?.private_labels ||
            []"
          >
            <span class="text-sm">
              {{ brand }}
            </span>
          </li>
        </ul>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading private labels...
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, companyName, hasPropertyBeenUpdated } = useCompanyData()
</script>
