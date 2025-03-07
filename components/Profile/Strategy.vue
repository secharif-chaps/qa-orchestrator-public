<template>
  <Card>
    <div class="grid grid-cols-2 gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-chart-sine"></i>
          <span> Digital strategy and social media </span>
        </h3>
      </div>

      <!-- Digital Strategy - individual property loading -->
      <div>Digital Strategy</div>
      <div
        class="text-secondary"
        v-if="
          hasPropertyBeenUpdated(
            'digital_strategy_and_social_media.digital_strategy'
          )
        "
      >
        {{
          getSourcedValue(
            company?.digital_strategy_and_social_media?.digital_strategy
          ) || 'Not found'
        }}
        <Source
          :item="company?.digital_strategy_and_social_media?.digital_strategy"
        />
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading digital strategy...
      </div>

      <div class="col-span-2 border-b-2 border-slate-200"></div>

      <!-- Loyalty Program - individual property loading -->
      <div>Loyalty program</div>
      <div
        class="text-secondary"
        v-if="
          hasPropertyBeenUpdated(
            'digital_strategy_and_social_media.loyalty_program'
          )
        "
      >
        {{
          getSourcedValue(
            company?.digital_strategy_and_social_media?.loyalty_program
          ) || 'Not found'
        }}
        <Source
          :item="company?.digital_strategy_and_social_media?.loyalty_program"
        />
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading loyalty program...
      </div>

      <!-- Online Services - individual property loading -->
      <div>Online Services</div>
      <div
        class="text-secondary"
        v-if="
          hasPropertyBeenUpdated(
            'digital_strategy_and_social_media.online_services'
          )
        "
      >
        {{
          (
            company?.digital_strategy_and_social_media?.online_services.map(
              (service) => service.value
            ) || []
          ).join(', ') || 'Not found'
        }}
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading online services...
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, hasPropertyBeenUpdated, getSourcedValue } = useCompanyData()
</script>
