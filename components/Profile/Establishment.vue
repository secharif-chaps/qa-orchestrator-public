<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-emerald-100 lg:w-2/3 text-emerald-600 mx-auto px-2 py-2 rounded"
      >
        <span >
          {{
            getSourcedValue(company?.profile?.establishment_year) ?? 'Not found'
          }}
        </span>
        
      </div>
      <div>year of establishment</div>
      <Source
        v-if="hasPropertyBeenUpdated('profile.establishment_year')"
        :sourced-value="company?.profile?.establishment_year"
      />
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, hasPropertyBeenUpdated, getSourcedValue } = useCompanyData()
</script>
