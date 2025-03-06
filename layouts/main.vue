<template>
  <div class="bg-slate-100 min-h-screen h-full min-w-screen w-full">
    <Appbar />
    <div class="flex h-full">
      <Sidebar />
      <div class="py-24 pl-24 pr-4 w-full min-h-screen">
        <slot />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup></script>
