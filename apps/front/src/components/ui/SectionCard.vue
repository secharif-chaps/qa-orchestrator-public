<template>
  <div class="gap-md bg-neutral-white p-xl shadow-2 flex flex-col rounded-xl">
    <div v-if="$slots.header">
      <slot name="header" />
    </div>
    <slot />
  </div>
</template>
