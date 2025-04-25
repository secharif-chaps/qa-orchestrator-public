<template>
  <Card>
    <div class="grid grid-cols-2 gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-bullseye-arrow"></i>
          <span> Target audience and customer base </span>
        </h3>
      </div>

      <!-- Customer Type - individual property loading -->
      <div>Customer base</div>
      <div
        v-if="company?.target_audience_and_customer_base?.customer_type"
      >
        <span class="text-sm text-secondary">
          {{
            getSourcedValue(
              company?.target_audience_and_customer_base?.customer_type
            ) ?? 'Not found'
          }}
          <Source
            :sourced-value="
              company?.target_audience_and_customer_base?.customer_type
            "
          />
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
        v-if="company?.target_audience_and_customer_base?.marketing_positioning"
      >
        <span class="text-sm text-secondary">
          {{
            getSourcedValue(
              company?.target_audience_and_customer_base?.marketing_positioning
            ) ?? 'Not found'
          }}
          <Source
            :sourced-value="
              company?.target_audience_and_customer_base?.marketing_positioning
            "
          />
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
