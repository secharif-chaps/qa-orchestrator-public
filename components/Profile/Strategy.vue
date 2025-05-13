<template>
  <Card>
    <div class="flex flex-col gap-4 ">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-chart-sine"></i>
          <span> Digital strategy and social media </span>
        </h3>
      </div>

      <!-- Digital Strategy - individual property loading -->
      <div>Digital Strategy</div>
      <div
        class="text-secondary text-sm"
       
      >
        {{
          getSourcedValue(
            company?.digital?.strategy
          ) || 'Not found'
        }}
        <Source
          :sourced-value="
            company?.digital?.strategy
          "
        />
      </div>
     

      <!-- Loyalty Program - individual property loading -->
      <div>Loyalty program</div>
      <div
        class="text-secondary text-sm"
  
      >
        {{
          getSourcedValue(
            company?.digital?.loyaltyProgram
          ) || 'Not found'
        }}
        <Source
          :sourced-value="
            company?.digital?.loyaltyProgram
          "
        />
      </div>
    

      <!-- Online Services - individual property loading -->
      <div>Online Services</div>
      <div
        class="text-secondary text-sm"
      >
        {{
          (
            company?.digital?.onlineServices.map(
              (service) => service.value
            ) || []
          ).join(', ') || 'Not found'
        }}
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, getSourcedValue } = useCompanyData()
</script>
