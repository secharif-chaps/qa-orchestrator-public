<template>
  <Card>
    <div class="text-center space-y-2">
      <div
        class="bg-emerald-100 lg:w-2/3 text-emerald-600 mx-auto px-2 py-2 rounded"
      >
        <span >
          {{
            getSourcedValue(company?.profile?.establishmentYear) ?? 'Not found'
          }}
        </span>
        
      </div>
      <div>year of establishment</div>
      <Source
        v-if="company?.profile?.establishmentYear"
        :sourced-value="company?.profile?.establishmentYear"
      />
    </div>
  </Card>
</template>

<script lang="ts" setup>
const { company, getSourcedValue } = useCompanyData()
</script>
