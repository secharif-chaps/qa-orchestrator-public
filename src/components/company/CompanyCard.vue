<template>
  <div class="max-w-7xl mx-auto grid grid-cols-12 gap-4 px-4 sm:px-6 lg:px-8">
    <!-- Back button -->
    <div class="col-span-12">
      <OButton type="tertiary" @click="$router.back()">
        <i class="fa fa-arrow-left"></i>
        <span class="text-secondary">Back</span>
      </OButton>
    </div>

    <!-- Title and buttons -->
    <div class="col-span-12 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <h1 class="text-3xl font-semibold">{{ title }}</h1>
      </div>
      <div class="flex gap-2">
        <OButton
          type="tertiary"
          :label="showAiChat ? 'Hide AI Chat' : 'Ask our AI'"
          @click="showAiChat = !showAiChat"
        >
          <i class="fa fa-comment"></i>
          <span class="text-secondary">Ask our AI</span>
        </OButton>
        <slot name="actions"></slot>
        <Export />
      </div>
    </div>

    <!-- Loading state -->
    <div class="col-span-12">
      <slot name="loading"></slot>
    </div>

    <!-- Main content container -->
    <div
      class="@container"
      :class="{
        'col-span-12': !showAiChat,
        'col-span-7 lg:col-span-9': showAiChat,
      }"
    >
      <slot></slot>
    </div>

    <!-- AI Chat sidebar -->
    <div
      :class="{
        'col-span-0': !showAiChat,
        'col-span-5 lg:col-span-3': showAiChat,
      }"
      v-show="showAiChat"
    >
      <div class="sticky top-20">
        <Chat @hide="showAiChat = false" />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { OButton } from '@owlint/feathers-vue'
import { ref } from 'vue'
import Export from './Export.vue'
import Chat from './Chat.vue'

// Props
defineProps<{
  title: string
  icon: string
}>()

// Chat visibility state
const showAiChat = ref(false)
</script>

<style>
.container {
  container-type: inline-size;
  container-name: content;
}

@container content (max-width: 1200px) {
  .card {
    grid-template-columns: repeat(2, 1fr);
  }
}

@container content (max-width: 768px) {
  .card {
    grid-template-columns: 1fr;
  }
}
</style>
