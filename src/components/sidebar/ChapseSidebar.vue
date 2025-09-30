<template>
  <div class="h-full flex flex-col justify-between">
    <div class="flex items-center justify-between border-b-2 shadow border-sage-800 px-4 py-2">
      <h2 class="text-headline-2xl">Chaps-e</h2>
      <Button variant="ghost-primary" dark icon="fa-solid fa-expand"> Agrandir </Button>
    </div>

    <div class="px-4 w-full flex flex-col gap-4 justify-between">
      <div class="w-full grow gap-2 flex flex-col items-center justify-center">
        <img :src="chapse" class="w-32 h-32 mb-4" />
        <Button variant="secondary" dark> Fais moi une synthèse </Button>
        <Button variant="secondary" dark> Liste moi les technologies citées </Button>
        <Button variant="secondary" dark> Génère moi un PDF </Button>
      </div>
    </div>

    <div class="relative p-4">
      <textarea
        name=""
        id=""
        placeholder="Write a message..."
        class="p-4 w-full h-32 bg-sage-900 rounded-block"
      ></textarea>
      <Button variant="primary" icon="fa fa-send" icon-only class="absolute right-6 bottom-8"
        >Send</Button
      >
    </div>
  </div>
</template>

<script setup lang="ts">
import chapse from '@/assets/chapse/default.svg'
import Button from '../ui/Button.vue'
</script>
