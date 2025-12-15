<template>
  <div class="space-y-8">
    <!-- Button Variants -->
    <section class="space-y-4 border border-primary-stroke p-4 rounded-card">
      <h2 class="text-xl font-semibold">Button Variants (Design System)</h2>
      <p class="text-sm text-secondary">
        All buttons have fully rounded style as per design system. Hover, focus, and click to see
        different states.
      </p>
      <div class="flex flex-wrap gap-3">
        <Button variant="primary" label="Primary" iconRight="fa fa-arrow-right" />
        <Button variant="secondary" label="Secondary" iconRight="fa fa-arrow-right" />
        <Button variant="tertiary" label="Tertiary" iconRight="fa fa-arrow-right" />
        <Button variant="accent" label="Accent" iconRight="fa fa-arrow-right" />
      </div>
    </section>

    <!-- Buttons with Icons -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">With Icons</h2>
      <div class="space-y-3">
        <div class="flex flex-wrap gap-3">
          <span class="text-sm text-secondary w-24">Left Icons:</span>
          <Button variant="primary" icon="fa fa-plus" label="Create" />
          <Button variant="secondary" icon="fa fa-download" label="Download" />
          <Button variant="tertiary" icon="fa fa-edit" label="Edit" />
          <Button variant="accent" icon="fa fa-sparkles" label="Special" />
        </div>
        <div class="flex flex-wrap gap-3">
          <span class="text-sm text-secondary w-24">Right Icons:</span>
          <Button variant="primary" iconRight="fa fa-arrow-right" label="Continue" />
          <Button variant="secondary" iconRight="fa fa-external-link" label="Open" />
          <Button variant="accent" iconRight="fa fa-arrow-right" label="Next" />
        </div>
        <div class="flex flex-wrap gap-3">
          <span class="text-sm text-secondary w-24">Icon Only:</span>
          <Button variant="primary" icon="fa fa-heart" />
          <Button variant="secondary" icon="fa fa-bookmark" />
          <Button variant="tertiary" icon="fa fa-share" />
          <Button variant="accent" icon="fa fa-star" />
        </div>
      </div>
    </section>

    <!-- Button Sizes -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Size Variations</h2>
      <div class="space-y-3">
        <div class="flex items-center gap-3">
          <span class="text-sm text-secondary w-20">Small:</span>
          <Button variant="primary" label="Small" size="sm" />
          <Button variant="secondary" icon="fa fa-cog" label="Settings" size="sm" />
          <Button variant="accent" label="Accent" size="sm" />
          <Button variant="tertiary" icon="fa fa-info" size="sm" />
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm text-secondary w-20">Medium:</span>
          <Button variant="primary" label="Medium" size="md" />
          <Button variant="secondary" icon="fa fa-save" label="Save" size="md" />
          <Button variant="accent" label="Accent" size="md" />
          <Button variant="tertiary" icon="fa fa-more" size="md" />
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm text-secondary w-20">Large:</span>
          <Button variant="primary" label="Large" size="lg" />
          <Button variant="secondary" icon="fa fa-upload" label="Upload" size="lg" />
          <Button variant="accent" label="Accent" size="lg" />
          <Button variant="tertiary" icon="fa fa-search" size="lg" />
        </div>
      </div>
    </section>

    <!-- Button States -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">States</h2>
      <div class="space-y-3">
        <div class="flex flex-wrap gap-3">
          <span class="text-sm text-secondary w-20">Loading:</span>
          <Button
            variant="primary"
            label="Loading"
            :loading="loadingButtons.primary"
            @click="simulateLoading('primary')"
          />
          <Button
            variant="secondary"
            label="Loading"
            :loading="loadingButtons.secondary"
            @click="simulateLoading('secondary')"
          />
          <Button
            variant="accent"
            label="Loading"
            :loading="loadingButtons.accent"
            @click="simulateLoading('accent')"
          />
          <Button
            variant="tertiary"
            label="Loading"
            :loading="loadingButtons.tertiary"
            @click="simulateLoading('tertiary')"
          />
        </div>
        <div class="flex flex-wrap gap-3">
          <span class="text-sm text-secondary w-20">Disabled:</span>
          <Button variant="primary" label="Disabled" disabled />
          <Button variant="secondary" label="Disabled" disabled />
          <Button variant="tertiary" label="Disabled" disabled />
          <Button variant="accent" label="Disabled" disabled />
        </div>
      </div>
    </section>

    <!-- Real-world Button Examples -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Real-world Use Cases</h2>
      <div class="space-y-4">
        <!-- Action Bar -->
        <div class="bg-base-100 p-4 rounded-lg border border-primary-stroke">
          <h3 class="text-sm font-medium mb-3">Action Bar</h3>
          <div class="flex flex-wrap gap-2">
            <Button variant="primary" icon="fa fa-plus" label="Create" />
            <Button variant="secondary" icon="fa fa-download" label="Export" />
            <Button variant="tertiary" icon="fa fa-filter" label="Filter" />
          </div>
        </div>

        <!-- Form Actions -->
        <div class="bg-base-100 p-4 rounded-lg border border-primary-stroke">
          <h3 class="text-sm font-medium mb-3">Form Actions</h3>
          <div class="flex justify-end gap-2">
            <Button variant="tertiary" label="Cancel" />
            <Button variant="secondary" label="Save Draft" />
            <Button variant="primary" label="Publish" />
          </div>
        </div>

        <!-- Call to Action -->
        <div class="bg-base-100 p-4 rounded-lg border border-primary-stroke">
          <h3 class="text-sm font-medium mb-3">Special Actions with Accent</h3>
          <div class="flex gap-2">
            <Button variant="accent" icon="fa fa-sparkles" label="Try Premium" />
            <Button variant="accent" icon="fa fa-star" label="Upgrade Now" />
            <Button variant="accent" icon="fa fa-gift" label="Get Started" />
          </div>
        </div>

        <!-- Navigation -->
        <div class="bg-base-100 p-4 rounded-lg border border-primary-stroke">
          <h3 class="text-sm font-medium mb-3">Navigation</h3>
          <div class="flex justify-between">
            <Button variant="secondary" icon="fa fa-chevron-left" label="Previous" />
            <div class="flex gap-2">
              <Button variant="tertiary" label="1" size="sm" />
              <Button variant="primary" label="2" size="sm" />
            </div>
            <Button variant="secondary" iconRight="fa fa-chevron-right" label="Next" />
          </div>
        </div>

        <!-- Icon Actions -->
        <div class="bg-base-100 p-4 rounded-lg border border-primary-stroke">
          <h3 class="text-sm font-medium mb-3">Icon Actions</h3>
          <div class="flex gap-2">
            <Button variant="tertiary" icon="fa fa-heart" />
            <Button variant="tertiary" icon="fa fa-bookmark" />
            <Button variant="tertiary" icon="fa fa-share" />
            <Button variant="secondary" icon="fa fa-comment" label="Comment" />
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { Button } from '@owlint/feathers-vue'

const loadingButtons = reactive({
  primary: false,
  secondary: false,
  tertiary: false,
  accent: false,
})

function simulateLoading(variant: keyof typeof loadingButtons) {
  loadingButtons[variant] = true
  setTimeout(() => {
    loadingButtons[variant] = false
  }, 2000)
}
</script>
