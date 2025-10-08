<template>
  <div class="space-y-8">
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Real-world Examples</h2>

      <!-- Search Bar with Results -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium">Search Interface</h3>
        <Input
          v-model="searchQuery"
          placeholder="Search companies..."
          icon="fa fa-search"
          clearable
          size="lg"
        />
        <div v-if="searchQuery" class="flex flex-wrap gap-2">
          <Tag
            v-for="filter in activeFilters"
            :key="filter"
            variant="primary"
            :label="filter"
            dismissible
            @dismiss="removeFilter(filter)"
          />
        </div>
      </div>

      <!-- Form with Validation -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium">User Registration Form</h3>
        <div class="bg-base-200 p-6 rounded-lg border border-primary-stroke">
          <div class="space-y-4">
            <Input
              v-model="formData.name"
              label="Full Name"
              placeholder="John Doe"
              icon="fa fa-user"
              required
            />
            <Input
              v-model="formData.email"
              label="Email Address"
              type="email"
              placeholder="john@example.com"
              icon="fa fa-envelope"
              required
              :error="formErrors.email"
            />
            <Alert
              v-if="formSubmitted"
              variant="success"
              title="Registration Successful!"
              message="Your account has been created."
              icon="fa fa-check-circle"
              dismissible
              @dismiss="formSubmitted = false"
            />
          </div>
          <div class="mt-4 flex gap-2">
            <Button @click="submitForm"> Submit </Button>
            <Button @click="resetForm"> Reset </Button>
          </div>
        </div>
      </div>

      <!-- Status Dashboard -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium">Status Dashboard</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-base-200 p-4 rounded-lg border border-primary-stroke">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium">API Status</span>
              <Tag variant="success" dot label="Operational" size="sm" />
            </div>
            <div class="text-2xl font-bold">99.9%</div>
            <div class="text-xs text-primary-light-content">Uptime</div>
          </div>
          <div class="bg-base-200 p-4 rounded-lg border border-primary-stroke">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium">Token Usage</span>
              <Tag variant="warning" icon="fa fa-coins" label="25 left" size="sm" />
            </div>
            <div class="text-2xl font-bold">475/500</div>
            <div class="text-xs text-primary-light-content">Tokens used</div>
          </div>
          <div class="bg-base-200 p-4 rounded-lg border border-primary-stroke">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium">Tasks</span>
              <Tag variant="info" label="3 pending" size="sm" />
            </div>
            <div class="text-2xl font-bold">12</div>
            <div class="text-xs text-primary-light-content">Active tasks</div>
          </div>
        </div>
      </div>

      <!-- Notification Examples -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium">Notifications</h3>
        <Alert
          variant="info"
          title="New Feature: Export to CSV"
          message="You can now export your data to CSV format from the companies page."
          icon="fa fa-sparkles"
          dismissible
        />
        <Alert variant="warning" title="Maintenance Scheduled" icon="fa fa-wrench">
          <div>
            <p class="text-sm text-primary-light-content mb-2">
              System maintenance is scheduled for tonight at 2:00 AM EST.
            </p>
            <div class="flex gap-2">
              <Tag variant="warning" icon="fa fa-clock" label="In 6 hours" size="sm" />
              <Tag variant="slate" label="~30 minutes" size="sm" />
            </div>
          </div>
        </Alert>
      </div>

      <!-- User Profiles with Badges -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium">User Profiles with Status Badges</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- User 1 - Online -->
          <div class="bg-base-200 p-4 rounded-lg border border-primary-stroke">
            <div class="flex items-center gap-3 mb-3">
              <div class="relative">
                <div
                  class="w-12 h-12 rounded-full bg-success-light flex items-center justify-center"
                >
                  <span class="text-lg font-semibold text-success-light-content">JD</span>
                </div>
                <div class="absolute -bottom-1 -right-1">
                  <Badge variant="primary" color="success" icon="fa fa-check" size="sm" />
                </div>
              </div>
              <div class="flex-1">
                <div class="font-semibold">John Doe</div>
                <div class="text-xs text-primary-light-content">Product Manager</div>
              </div>
            </div>
            <div class="flex gap-2">
              <Tag variant="success" label="Online" size="sm" />
              <Tag variant="slate" label="Available" size="sm" />
            </div>
          </div>

          <!-- User 2 - Away -->
          <div class="bg-base-200 p-4 rounded-lg border border-primary-stroke">
            <div class="flex items-center gap-3 mb-3">
              <div class="relative">
                <div class="w-12 h-12 rounded-full bg-warning-light flex items-center justify-center">
                  <span class="text-lg font-semibold text-warning-light-content">AS</span>
                </div>
                <div class="absolute -bottom-1 -right-1">
                  <Badge variant="primary" color="warning" icon="fa fa-clock" size="sm" />
                </div>
              </div>
              <div class="flex-1">
                <div class="font-semibold">Alice Smith</div>
                <div class="text-xs text-primary-light-content">Designer</div>
              </div>
            </div>
            <div class="flex gap-2">
              <Tag variant="warning" label="Away" size="sm" />
              <Tag variant="slate" label="Back in 15m" size="sm" />
            </div>
          </div>

          <!-- User 3 - Busy -->
          <div class="bg-base-200 p-4 rounded-lg border border-primary-stroke">
            <div class="flex items-center gap-3 mb-3">
              <div class="relative">
                <div class="w-12 h-12 rounded-full bg-error-light flex items-center justify-center">
                  <span class="text-lg font-semibold text-error-light-content">BJ</span>
                </div>
                <div class="absolute -bottom-1 -right-1">
                  <Badge variant="primary" color="error" icon="fa fa-minus-circle" size="sm" />
                </div>
              </div>
              <div class="flex-1">
                <div class="font-semibold">Bob Johnson</div>
                <div class="text-xs text-primary-light-content">Developer</div>
              </div>
            </div>
            <div class="flex gap-2">
              <Tag variant="error" label="Busy" size="sm" />
              <Tag variant="slate" label="Do not disturb" size="sm" />
            </div>
          </div>
        </div>
      </div>

      <!-- Notification Center -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium">Notification Center</h3>
        <div class="bg-base-200 rounded-lg border border-primary-stroke overflow-hidden">
          <div class="p-4 border-b border-primary-stroke flex items-center justify-between">
            <h4 class="font-semibold">Notifications</h4>
            <div class="flex items-center gap-3">
              <Badge variant="primary" color="error" :number="5" size="sm" />
              <button class="text-sm text-primary-light-content hover:text-primary">
                Mark all as read
              </button>
            </div>
          </div>
          <div class="divide-y divide-primary-stroke">
            <!-- Notification 1 -->
            <div class="p-4 flex items-start gap-3 hover:bg-base-100 transition-colors">
              <Badge variant="primary" color="info" icon="fa fa-comment" size="md" />
              <div class="flex-1">
                <div class="font-medium text-sm">New comment on your task</div>
                <div class="text-xs text-primary-light-content mt-1">
                  Sarah commented: "Great work on the design!"
                </div>
                <div class="text-xs text-primary-light-content mt-2">2 minutes ago</div>
              </div>
              <Badge variant="secondary" color="info" :number="1" size="sm" />
            </div>

            <!-- Notification 2 -->
            <div class="p-4 flex items-start gap-3 hover:bg-base-100 transition-colors">
              <Badge variant="primary" color="success" icon="fa fa-check-circle" size="md" />
              <div class="flex-1">
                <div class="font-medium text-sm">Task completed</div>
                <div class="text-xs text-primary-light-content mt-1">
                  "Update documentation" was marked as complete
                </div>
                <div class="text-xs text-primary-light-content mt-2">1 hour ago</div>
              </div>
            </div>

            <!-- Notification 3 -->
            <div class="p-4 flex items-start gap-3 hover:bg-base-100 transition-colors">
              <Badge variant="primary" color="warning" icon="fa fa-exclamation-triangle" size="md" />
              <div class="flex-1">
                <div class="font-medium text-sm">Deadline approaching</div>
                <div class="text-xs text-primary-light-content mt-1">
                  "Q4 Report" is due in 2 days
                </div>
                <div class="text-xs text-primary-light-content mt-2">3 hours ago</div>
              </div>
              <Badge variant="secondary" color="warning" :number="2" size="sm" />
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Tag from '@/components/ui/Tag.vue'
import Alert from '@/components/ui/Alert.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'

const searchQuery = ref('')
const activeFilters = ref(['Active', 'Verified', 'Premium'])
const formSubmitted = ref(false)
const formData = reactive({
  name: '',
  email: '',
})
const formErrors = reactive({
  email: '',
})

function removeFilter(filter: string) {
  const index = activeFilters.value.indexOf(filter)
  if (index > -1) {
    activeFilters.value.splice(index, 1)
  }
}

function submitForm() {
  formErrors.email = ''
  if (!formData.email.includes('@')) {
    formErrors.email = 'Please enter a valid email address'
    return
  }
  formSubmitted.value = true
  setTimeout(() => {
    resetForm()
  }, 3000)
}

function resetForm() {
  formData.name = ''
  formData.email = ''
  formErrors.email = ''
  formSubmitted.value = false
}
</script>
