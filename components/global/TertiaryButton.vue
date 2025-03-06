<template>
  <div>
    <button
      type="button"
      :class="classes"
      class="rounded-md bg-transparent font-semibold text-primary hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 hover:ring-2 ring-emerald-200"
    >
      <slot name="left-icon"></slot>
      <slot default></slot>
      <slot name="right-icon"></slot>
    </button>
  </div>
</template>

<script lang="ts" setup>
const props = defineProps({
  size: {
    type: String,
    default: 'large',
  },
})

const classes = computed(() => {
  return {
    'px-1 py-1': props.size === 'small',
    'px-3 py-2': props.size === 'large',
  }
})
</script>
