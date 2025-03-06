<template>
  <div class="fixed top-0 w-full z-10 pl-4">
    <div class="bg-white rounded-bl-2xl h-[68px] px-6 shadow-md">
      <div class="flex items-center justify-between h-full">
        <NuxtLink to="/">
          <div class="flex items-center space-x-2 text-xl text-primary">
            <i class="fa fa-leaf"></i>
            <h1 class="font-extrabold">MINT</h1>
          </div>
        </NuxtLink>
        <div class="max-w-md grow">
          <OInput
            id="search"
            placeholder="Search"
          />
        </div>
        <div></div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { OInput } from '@owlint/feathers-vue'
</script>
