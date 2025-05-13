<template>
  <Card>
    <div class=" flex flex-col gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-bullseye-arrow"></i>
          <span> Target audience and customer base </span>
        </h3>
      </div>

      <!-- Customer Type - individual property loading -->
      <div>Customer base</div>
      <div
        v-if="company?.products?.customerType"
      >
        <span class="text-sm text-secondary">
          {{

              company?.products?.customerType
            ?? 'Not found'
          }}
          
        </span>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading customer base...
      </div>

      <!-- Marketing Positioning - individual property loading -->
      <div>Positioning</div>
      <div
        v-if="company?.products?.marketingPositioning"
      >
        <span class="text-sm text-secondary">
          {{
            
              company?.products?.marketingPositioning
             ?? 'Not found'
          }}
         
        </span>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading positioning...
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, getSourcedValue } = useCompanyData()
</script>
