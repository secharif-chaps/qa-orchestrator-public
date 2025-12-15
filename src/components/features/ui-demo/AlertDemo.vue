<template>
  <div class="space-y-8">
    <!-- Basic Alerts -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Basic Alerts</h2>
      <Alert
        variant="neutral"
        title="Alert title"
        message="Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua."
        icon="fa fa-info-circle"
      />
      <Alert
        variant="info"
        title="Information"
        message="This is an informational message to keep you updated."
        icon="fa fa-info-circle"
      />
      <Alert
        variant="success"
        title="Success!"
        message="Your operation completed successfully."
        icon="fa fa-check-circle"
      />
      <Alert
        variant="warning"
        title="Warning"
        message="Please review this warning before proceeding."
        icon="fa fa-exclamation-triangle"
      />
      <Alert
        variant="error"
        title="Error Occurred"
        message="An error has occurred. Please try again."
        icon="fa fa-times-circle"
      />
      <Alert
        variant="accent"
        title="Special Notice"
        message="This is a special announcement or promotional message."
        icon="fa fa-star"
      />
    </section>

    <section
      class="space-y-4 dark dark:text-sage-100 dark:bg-sage-900 border border-primary-stroke rounded-card p-4"
    >
      <h2 class="text-xl font-semibold">Basic Dark Alerts</h2>
      <Alert
        variant="neutral"
        title="Alert title"
        message="Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua."
        icon="fa fa-info-circle"
      />
      <Alert
        variant="info"
        title="Information"
        message="This is an informational message to keep you updated."
        icon="fa fa-info-circle"
      />
      <Alert
        variant="success"
        title="Success!"
        message="Your operation completed successfully."
        icon="fa fa-check-circle"
      />
      <Alert
        variant="warning"
        title="Warning"
        message="Please review this warning before proceeding."
        icon="fa fa-exclamation-triangle"
      />
      <Alert
        variant="error"
        title="Error Occurred"
        message="An error has occurred. Please try again."
        icon="fa fa-times-circle"
      />
      <Alert
        variant="accent"
        title="Special Notice"
        message="This is a special announcement or promotional message."
        icon="fa fa-star"
      />
    </section>

    <!-- Dismissible Alerts -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Dismissible Alerts</h2>
      <Alert
        v-if="showAlert1"
        variant="info"
        title="Dismissible Alert"
        message="Click the X button to dismiss this alert."
        icon="fa fa-info-circle"
        dismissible
        @dismiss="showAlert1 = false"
      />
      <Alert
        v-if="showAlert2"
        variant="warning"
        title="Session Expiring"
        message="Your session will expire in 5 minutes. Please save your work."
        icon="fa fa-clock"
        dismissible
        @dismiss="showAlert2 = false"
      />
      <button
        v-if="!showAlert1 || !showAlert2"
        @click="resetAlerts"
        class="text-secondary text-sm hover:underline"
      >
        Reset dismissed alerts
      </button>
    </section>

    <!-- Alerts with Action Button -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">With Action Button</h2>
      <Alert
        variant="neutral"
        title="Alert title"
        message="Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua."
        icon="fa fa-info-circle"
      >
        <template #action>
          <Button variant="primary" label="Action" size="sm" />
        </template>
      </Alert>
      <Alert
        variant="success"
        title="Success!"
        message="Your operation completed successfully."
        icon="fa fa-check-circle"
      >
        <template #action>
          <Button variant="primary" label="Action" size="sm" />
        </template>
      </Alert>
      <Alert
        variant="info"
        title="New Update Available"
        message="A new version of the application is available."
        icon="fa fa-download"
        dismissible
        @dismiss="showAlert3 = false"
      >
        <template #action>
          <Button variant="primary" label="Update" size="sm" />
        </template>
      </Alert>
    </section>

    <!-- Alerts with Multiple Actions -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">With Multiple Actions</h2>
      <Alert
        variant="warning"
        title="Action Required"
        message="Please complete your profile to access all features."
        icon="fa fa-user-circle"
      >
        <template #actions>
          <Button variant="primary" size="sm" label="Complete Profile" />
          <Button variant="tertiary" size="sm" label="Remind Me Later" />
        </template>
      </Alert>
    </section>

    <!-- Custom Content -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Custom Content</h2>
      <Alert variant="success" icon="fa fa-gift" title="Welcome Gift!">
        <div>
          <p class="text-sm mb-3">As a new user, you've received 100 free tokens to get started.</p>
          <ul class="text-sm space-y-1">
            <li>• Valid for 30 days</li>
            <li>• Use for any module</li>
            <li>• No credit card required</li>
          </ul>
        </div>
      </Alert>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Alert from '@/components/ui/Alert.vue'
import { Button } from '@owlint/feathers-vue'

const showAlert1 = ref(true)
const showAlert2 = ref(true)
const showAlert3 = ref(true)

function resetAlerts() {
  showAlert1.value = true
  showAlert2.value = true
  showAlert3.value = true
}
</script>
