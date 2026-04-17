<template>
  <div class="gap-md flex flex-col rounded-xl bg-white p-6">
    <div v-if="$slots.header">
      <slot name="header" />
    </div>
    <slot />
  </div>
</template>
