<template>
  <Card class="h-full">
    <div class="grid grid-cols-2 gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-hand-holding-heart"></i>
          <span> CSR </span>
        </h3>
      </div>

      <!-- CSR Initiatives - individual property loading -->
      <div>Responsibility</div>
      <div v-if="hasPropertyBeenUpdated('csr.responsibility_initiatives')">
        <ul class="list-disc">
          <li
            class="space-x-2 text-secondary"
            v-for="initiative in company?.csr?.responsibility_initiatives || []"
          >
            <span class="text-sm">
              {{ getSourcedValue(initiative) }}
            </span>
            <Source :sourced-value="initiative" />
          </li>
          <li
            v-if="company?.csr?.responsibility_initiatives?.length === 0"
            class="text-sm text-secondary italic"
          >
            Not found
          </li>
        </ul>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading initiatives...
      </div>

      <!-- Charity Actions - individual property loading -->
      <div>Charity Initiative</div>
      <div v-if="hasPropertyBeenUpdated('csr.charity_actions')">
        <span
          class="text-sm text-secondary"
          v-if="company?.csr?.charity_actions"
        >
          {{
            (
              company?.csr?.charity_actions.map((action) => action.value) || []
            ).join(', ') || 'Not found'
          }}
        </span>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        Loading charity initiatives...
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, hasPropertyBeenUpdated, getSourcedValue } = useCompanyData()
</script>
