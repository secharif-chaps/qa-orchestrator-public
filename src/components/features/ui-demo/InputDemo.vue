<template>
  <div class="space-y-8">
    <!-- Basic Inputs -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Basic Inputs</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input v-model="inputValues.basic" label="Basic Input" placeholder="Enter text..." />
        <Input
          v-model="inputValues.required"
          label="Required Field"
          placeholder="This field is required"
          required
        />
      </div>
    </section>

    <!-- Input with Icons -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">With Icons</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input
          v-model="inputValues.search"
          label="Search"
          placeholder="Search..."
          icon="fa fa-search"
        />
        <Input
          v-model="inputValues.email"
          label="Email"
          placeholder="Enter your email"
          type="email"
          icon="fa fa-envelope"
        />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input
          v-model="inputValues.phone"
          label="Phone"
          placeholder="Enter phone number"
          icon="fa fa-phone"
        />
        <Input
          v-model="inputValues.website"
          label="Website"
          placeholder="https://example.com"
          icon="fa fa-globe"
        />
      </div>
    </section>

    <!-- Clearable Inputs -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Clearable</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input
          v-model="inputValues.clearable1"
          label="Clearable Input"
          placeholder="Type something and clear it"
          clearable
        />
        <Input
          v-model="inputValues.clearable2"
          label="Search with Clear"
          placeholder="Search and clear..."
          icon="fa fa-search"
          clearable
        />
      </div>
    </section>

    <!-- Input Sizes -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Size Variations</h2>
      <div class="space-y-4">
        <Input
          v-model="inputValues.small"
          label="Small Input"
          placeholder="Small size"
          size="sm"
        />
        <Input
          v-model="inputValues.medium"
          label="Medium Input (Default)"
          placeholder="Medium size"
          size="md"
        />
        <Input
          v-model="inputValues.large"
          label="Large Input"
          placeholder="Large size"
          size="lg"
        />
      </div>
    </section>

    <!-- Input States -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">States</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input
          v-model="inputValues.error"
          label="With Error"
          placeholder="Invalid input"
          error="Please enter a valid value"
        />
        <Input
          v-model="inputValues.disabled"
          label="Disabled"
          placeholder="Cannot edit"
          disabled
        />
      </div>
    </section>

    <!-- Input Types -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Input Types</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Input
          v-model="inputValues.password"
          label="Password"
          type="password"
          placeholder="Enter password"
          icon="fa fa-lock"
        />
        <Input
          v-model="inputValues.number"
          label="Number"
          type="number"
          placeholder="Enter number"
          icon="fa fa-hashtag"
        />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import Input from '@/components/ui/Input.vue'

const inputValues = reactive({
  basic: '',
  required: '',
  search: '',
  email: '',
  phone: '',
  website: '',
  clearable1: 'Clear me!',
  clearable2: '',
  small: '',
  medium: '',
  large: '',
  error: 'Invalid',
  disabled: 'Cannot edit',
  password: '',
  number: '',
})
</script>
