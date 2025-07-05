<template>
  <Card>
    <div class=" flex flex-col gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-bullseye-arrow"></i>
          <span>{{ title }}</span>
        </h3>
      </div>

      <!-- Customer Type - individual property loading -->
      <h4>{{ $t('profile.sections.target.customerBase') }}</h4>
      <div
        v-if="company?.products?.customerType"
      >
        <p class="text-sm text-secondary">
          {{
              company?.products?.customerType ?? $t('common.notFound')
          }}
        </p>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        {{ $t('common.loading') }}
      </div>

      <!-- Marketing Positioning - individual property loading -->
      <h4>{{ $t('profile.sections.target.positioning') }}</h4>
      <div
        v-if="company?.products?.marketingPositioning"
      >
        <p class="text-sm text-secondary">
          {{
            
              company?.products?.marketingPositioning
             ?? $t('common.notFound')
          }}
         
        </p>
      </div>
      <div
        v-else
        class="text-secondary italic"
      >
        {{ $t('common.loading') }}
      </div>
    </div>
  </Card>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { company, getSourcedValue } = useCompanyData()

defineProps<{
  title: string
}>()
</script>
