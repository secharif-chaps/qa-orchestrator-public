<template>
  <div class="space-y-8">
    <!-- Basic Tags -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Basic Tags</h2>
      <div class="p-4 border border-primary-stroke rounded-card flex flex-wrap gap-3">
        <Tag variant="primary" label="Primary" />
        <Tag variant="secondary" label="Secondary" />
        <Tag variant="success" label="Success" />
        <Tag variant="warning" label="Warning" />
        <Tag variant="error" label="Error" />
        <Tag variant="info" label="Info" />
        <Tag variant="accent" label="Accent" />
      </div>
      <div
        class="dark p-4 border border-primary-stroke rounded-card dark:text-sage-100 dark:bg-sage-900 flex flex-wrap gap-3"
      >
        <Tag variant="primary" label="Primary" />
        <Tag variant="secondary" label="Secondary" />
        <Tag variant="success" label="Success" />
        <Tag variant="warning" label="Warning" />
        <Tag variant="error" label="Error" />
        <Tag variant="info" label="Info" />
        <Tag variant="accent" label="Accent" />
      </div>
    </section>

    <!-- Basic Tags -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Basic Tags</h2>
      <div class="p-4 border border-primary-stroke rounded-card flex flex-wrap gap-3">
        <Tag appearance="outline" variant="primary" label="Primary" />
        <Tag appearance="outline" variant="secondary" label="Secondary" />
        <Tag appearance="outline" variant="success" label="Success" />
        <Tag appearance="outline" variant="warning" label="Warning" />
        <Tag appearance="outline" variant="error" label="Error" />
        <Tag appearance="outline" variant="info" label="Info" />
        <Tag appearance="outline" variant="accent" label="Accent" />
      </div>
      <div
        class="dark p-4 border border-primary-stroke rounded-card dark:text-sage-100 dark:bg-sage-900 flex flex-wrap gap-3"
      >
        <Tag appearance="outline" variant="primary" label="Primary" />
        <Tag appearance="outline" variant="secondary" label="Secondary" />
        <Tag appearance="outline" variant="success" label="Success" />
        <Tag appearance="outline" variant="warning" label="Warning" />
        <Tag appearance="outline" variant="error" label="Error" />
        <Tag appearance="outline" variant="info" label="Info" />
        <Tag appearance="outline" variant="accent" label="Accent" />
      </div>
    </section>

    <!-- Tags with Icons -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">With Icons</h2>
      <div class="flex flex-wrap gap-3">
        <Tag variant="primary" icon="fa fa-star" label="Featured" />
        <Tag variant="success" icon="fa fa-check" label="Approved" />
        <Tag variant="warning" icon="fa fa-exclamation-triangle" label="Pending" />
        <Tag variant="error" icon="fa fa-times-circle" label="Rejected" />
        <Tag variant="info" icon="fa fa-info-circle" label="Information" />
        <Tag variant="accent" icon="fa fa-clock" label="Scheduled" />
      </div>
    </section>

    <!-- Tags with Dots -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">With Status Dots</h2>
      <div class="flex flex-wrap gap-3">
        <Tag variant="success" dot label="Online" />
        <Tag variant="warning" dot label="Away" />
        <Tag variant="error" dot label="Offline" />
        <Tag variant="accent" dot label="Accent" />
      </div>
    </section>

    <!-- Different Sizes -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Size Variations</h2>
      <div class="space-y-3">
        <div class="flex items-center gap-3">
          <span class="text-sm text-primary-light-content w-20">Extra Small:</span>
          <Tag variant="primary" label="XS Badge" size="xs" />
          <Tag variant="success" icon="fa fa-check" label="Done" size="xs" />
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm text-primary-light-content w-20">Small:</span>
          <Tag variant="primary" label="SM Badge" size="sm" />
          <Tag variant="warning" icon="fa fa-star" label="Featured" size="sm" />
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm text-primary-light-content w-20">Medium:</span>
          <Tag variant="primary" label="MD Badge" size="md" />
          <Tag variant="info" icon="fa fa-info" label="Information" size="md" />
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm text-primary-light-content w-20">Large:</span>
          <Tag variant="primary" label="LG Badge" size="lg" />
          <Tag variant="error" icon="fa fa-exclamation" label="Alert" size="lg" />
        </div>
      </div>
    </section>

    <!-- Dismissible Tags -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Dismissible</h2>
      <div class="flex flex-wrap gap-3">
        <Tag
          v-if="showDismissible1"
          variant="primary"
          label="Dismissible"
          dismissible
          @dismiss="showDismissible1 = false"
        />
        <Tag
          v-if="showDismissible2"
          variant="warning"
          icon="fa fa-tag"
          label="Tag"
          dismissible
          rounded
          @dismiss="showDismissible2 = false"
        />
        <Tag
          v-if="showDismissible3"
          variant="success"
          label="Filter Applied"
          dismissible
          @dismiss="showDismissible3 = false"
        />
        <button
          v-if="!showDismissible1 || !showDismissible2 || !showDismissible3"
          @click="resetDismissible"
          class="text-primary-light-content text-sm hover:underline"
        >
          Reset dismissed Tags
        </button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Tag from '@/components/ui/Tag.vue'

const showDismissible1 = ref(true)
const showDismissible2 = ref(true)
const showDismissible3 = ref(true)

function resetDismissible() {
  showDismissible1.value = true
  showDismissible2.value = true
  showDismissible3.value = true
}
</script>
