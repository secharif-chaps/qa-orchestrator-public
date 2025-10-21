<template>
  <div class="flex flex-col items-center justify-center py-12 px-6">
    <img
      src="@/assets/chapse/error_light.svg"
      alt="No data"
      class="w-24 h-24 mb-6 opacity-80"
    />
    <div class="text-center">
      <slot>
        <p class="text-secondary text-lg">No data available</p>
      </slot>
    </div>
  </div>
</template>

<script setup lang="ts">
// Simple no data state component with customizable content via slot
</script>
