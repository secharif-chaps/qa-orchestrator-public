<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-3xl font-bold">
              {{ $t('admin.workflows.title', 'Workflow Management') }}
            </h1>
            <p class="text-secondary mt-2">
              {{ $t('admin.workflows.description', 'Configure and manage automated workflows and processes') }}
            </p>
          </div>

          <!-- Back to Admin Dashboard -->
          <Button
            variant="tertiary"
            icon="fa fa-arrow-left"
            :label="$t('admin.dashboard.back', 'Back to Admin')"
            @click="$router.push('/admin')"
          />
        </div>
      </div>

      <!-- Coming Soon Content -->
      <div class="max-w-2xl mx-auto text-center py-16">
        <!-- Icon -->
        <div class="w-24 h-24 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-8">
          <i class="fa fa-project-diagram text-4xl"></i>
        </div>

        <!-- Title -->
        <h2 class="text-2xl font-bold mb-4">
          {{ $t('admin.workflows.comingSoon.title', 'Workflow Features Coming Soon') }}
        </h2>

        <!-- Description -->
        <p class="text-secondary text-lg mb-8">
          {{ $t('admin.workflows.comingSoon.description', 'We\'re working on powerful workflow management features that will allow you to automate processes and streamline operations.') }}
        </p>

        <!-- Planned Features -->
        <div class="bg-bg1 rounded-lg border border-border-2 p-8 text-left mb-8">
          <h3 class="text-lg font-semibold mb-4">
            {{ $t('admin.workflows.comingSoon.features.title', 'Planned Features') }}
          </h3>
          <ul class="space-y-3">
            <li class="flex items-start">
              <i class="fa fa-check-circle text-success mt-1 mr-3"></i>
              <div>
                <span class="font-medium">
                  {{ $t('admin.workflows.comingSoon.features.automation.title', 'Process Automation') }}
                </span>
                <p class="text-sm text-secondary">
                  {{ $t('admin.workflows.comingSoon.features.automation.description', 'Create automated workflows for routine tasks') }}
                </p>
              </div>
            </li>
            <li class="flex items-start">
              <i class="fa fa-check-circle text-success mt-1 mr-3"></i>
              <div>
                <span class="font-medium">
                  {{ $t('admin.workflows.comingSoon.features.triggers.title', 'Custom Triggers') }}
                </span>
                <p class="text-sm text-secondary">
                  {{ $t('admin.workflows.comingSoon.features.triggers.description', 'Set up triggers based on events and conditions') }}
                </p>
              </div>
            </li>
            <li class="flex items-start">
              <i class="fa fa-check-circle text-success mt-1 mr-3"></i>
              <div>
                <span class="font-medium">
                  {{ $t('admin.workflows.comingSoon.features.monitoring.title', 'Workflow Monitoring') }}
                </span>
                <p class="text-sm text-secondary">
                  {{ $t('admin.workflows.comingSoon.features.monitoring.description', 'Monitor workflow execution and performance') }}
                </p>
              </div>
            </li>
            <li class="flex items-start">
              <i class="fa fa-check-circle text-success mt-1 mr-3"></i>
              <div>
                <span class="font-medium">
                  {{ $t('admin.workflows.comingSoon.features.templates.title', 'Workflow Templates') }}
                </span>
                <p class="text-sm text-secondary">
                  {{ $t('admin.workflows.comingSoon.features.templates.description', 'Pre-built templates for common workflow patterns') }}
                </p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Alert for Development -->
        <Alert
          variant="info"
          title="Development in Progress"
          message="This feature is currently under development. Stay tuned for updates!"
          icon="fa fa-code"
        />

        <!-- Action Buttons -->
        <div class="flex justify-center gap-4 mt-8">
          <Button
            variant="primary"
            icon="fa fa-arrow-left"
            :label="$t('admin.workflows.backToDashboard', 'Back to Dashboard')"
            @click="$router.push('/admin')"
          />
          <Button
            variant="secondary"
            icon="fa fa-envelope"
            :label="$t('admin.workflows.requestFeature', 'Request Feature')"
            @click="handleFeatureRequest"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workflow
</route>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()

const handleFeatureRequest = () => {
  // This could open a modal or redirect to a feedback form
  // For now, we'll just show a simple alert
  alert('Feature request functionality will be implemented soon!')
}
</script>