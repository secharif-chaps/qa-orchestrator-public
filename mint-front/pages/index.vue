<template>
  <Card title="Dasboard">
    <div class="space-x-2 flex gap-2">
      <OButton
        @click="$router.push('/search')"
        label="Search"
      ></OButton>

      <OButton
        @click="$router.push('/cards')"
        label="Cards"
      ></OButton>
    </div>
  </Card>
</template>

<script lang="ts" setup>
import { OButton } from '@owlint/feathers-vue'
</script>
