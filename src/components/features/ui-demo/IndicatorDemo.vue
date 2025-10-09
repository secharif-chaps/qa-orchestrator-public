<template>
  <div class="space-y-8">
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Indicator Component</h2>
      <p class="text-primary-light-content">
        Simple status indicators with light halo effect. Perfect for online status, activity
        indicators, and subtle notifications.
      </p>

      <!-- All Colors -->
      <div class="space-y-2">
        <h3 class="text-lg font-medium">Colors</h3>
        <p class="text-sm text-primary-light-content mb-4">
          All semantic colors available with -200 background and -900 dot
        </p>
        <div
          class="p-4 border border-primary-stroke rounded-card flex flex-wrap items-center gap-6"
        >
          <div class="flex flex-col items-center gap-2">
            <Indicator color="primary" />
            <span class="text-xs text-primary-light-content">Primary</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="success" />
            <span class="text-xs text-primary-light-content">Success</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="warning" />
            <span class="text-xs text-primary-light-content">Warning</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="error" />
            <span class="text-xs text-primary-light-content">Error</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="info" />
            <span class="text-xs text-primary-light-content">Info</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="accent" />
            <span class="text-xs text-primary-light-content">Accent</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="slate" />
            <span class="text-xs text-primary-light-content">Slate</span>
          </div>
        </div>

        <div
          class="dark dark:text-sage-100 dark:bg-sage-900 border border-primary-stroke rounded-card p-4 flex flex-wrap items-center gap-6"
        >
          <div class="flex flex-col items-center gap-2">
            <Indicator color="primary" />
            <span class="text-xs">Primary</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="success" />
            <span class="text-xs">Success</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="warning" />
            <span class="text-xs">Warning</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="error" />
            <span class="text-xs">Error</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="info" />
            <span class="text-xs">Info</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="accent" />
            <span class="text-xs">Accent</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="slate" />
            <span class="text-xs">Slate</span>
          </div>
        </div>
      </div>

      <!-- Sizes -->
      <div class="space-y-2">
        <h3 class="text-lg font-medium">Sizes</h3>
        <p class="text-sm text-primary-light-content mb-4">Small, medium, and large sizes</p>
        <div class="flex flex-wrap items-center gap-8">
          <div class="flex flex-col items-center gap-2">
            <Indicator color="success" size="sm" />
            <span class="text-xs text-primary-light-content">Small</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="success" size="md" />
            <span class="text-xs text-primary-light-content">Medium</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Indicator color="success" size="lg" />
            <span class="text-xs text-primary-light-content">Large</span>
          </div>
        </div>
      </div>

      <!-- Usage with Text -->
      <div class="space-y-2">
        <h3 class="text-lg font-medium">Usage with Text</h3>
        <p class="text-sm text-primary-light-content mb-4">Indicators paired with status text</p>
        <div class="space-y-3">
          <div class="flex items-center gap-2">
            <Indicator color="success" size="sm" />
            <span class="text-sm">All systems operational</span>
          </div>
          <div class="flex items-center gap-2">
            <Indicator color="warning" size="sm" />
            <span class="text-sm">Degraded performance</span>
          </div>
          <div class="flex items-center gap-2">
            <Indicator color="error" size="sm" />
            <span class="text-sm">Service outage</span>
          </div>
          <div class="flex items-center gap-2">
            <Indicator color="info" size="sm" />
            <span class="text-sm">Maintenance scheduled</span>
          </div>
        </div>
      </div>

      <!-- Real-world Examples -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium">Real-world Examples</h3>

        <!-- User Status List -->
        <div class="bg-base-200 p-6 rounded-lg border border-primary-stroke space-y-4">
          <h4 class="text-sm font-semibold">User Status List</h4>
          <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-base-100 rounded-lg">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-full bg-success-light flex items-center justify-center"
                >
                  <span class="text-sm font-semibold text-success-light-content">AM</span>
                </div>
                <div>
                  <div class="font-medium text-sm">Alice Morgan</div>
                  <div class="text-xs text-primary-light-content">Product Designer</div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <Indicator color="success" size="sm" />
                <span class="text-xs text-primary-light-content">Online</span>
              </div>
            </div>

            <div class="flex items-center justify-between p-3 bg-base-100 rounded-lg">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-full bg-warning-light flex items-center justify-center"
                >
                  <span class="text-sm font-semibold text-warning-light-content">BT</span>
                </div>
                <div>
                  <div class="font-medium text-sm">Ben Taylor</div>
                  <div class="text-xs text-primary-light-content">Frontend Developer</div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <Indicator color="warning" size="sm" />
                <span class="text-xs text-primary-light-content">Away</span>
              </div>
            </div>

            <div class="flex items-center justify-between p-3 bg-base-100 rounded-lg">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center">
                  <span class="text-sm font-semibold text-primary-light-content">CW</span>
                </div>
                <div>
                  <div class="font-medium text-sm">Clara White</div>
                  <div class="text-xs text-primary-light-content">Backend Developer</div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <Indicator color="slate" size="sm" />
                <span class="text-xs text-primary-light-content">Offline</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Server Status Dashboard -->
        <div class="bg-base-200 p-6 rounded-lg border border-primary-stroke space-y-4">
          <h4 class="text-sm font-semibold">Server Status Dashboard</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-base-100 p-4 rounded-lg">
              <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium">API Server</span>
                <Indicator color="success" size="md" />
              </div>
              <div class="text-xs text-primary-light-content">
                Response time: 45ms
                <br />
                Uptime: 99.99%
              </div>
            </div>

            <div class="bg-base-100 p-4 rounded-lg">
              <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium">Database</span>
                <Indicator color="warning" size="md" />
              </div>
              <div class="text-xs text-primary-light-content">
                Response time: 120ms
                <br />
                Uptime: 98.5%
              </div>
            </div>

            <div class="bg-base-100 p-4 rounded-lg">
              <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium">Cache Server</span>
                <Indicator color="success" size="md" />
              </div>
              <div class="text-xs text-primary-light-content">
                Response time: 12ms
                <br />
                Uptime: 99.95%
              </div>
            </div>

            <div class="bg-base-100 p-4 rounded-lg">
              <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium">Storage</span>
                <Indicator color="error" size="md" />
              </div>
              <div class="text-xs text-primary-light-content">
                Status: Maintenance
                <br />
                ETA: 30 minutes
              </div>
            </div>
          </div>
        </div>

        <!-- Task List with Status -->
        <div class="bg-base-200 p-6 rounded-lg border border-primary-stroke space-y-4">
          <h4 class="text-sm font-semibold">Task List</h4>
          <div class="space-y-2">
            <div class="flex items-start gap-3 p-3 bg-base-100 rounded-lg">
              <Indicator color="success" size="sm" class="mt-1" />
              <div class="flex-1">
                <div class="text-sm font-medium">Implement user authentication</div>
                <div class="text-xs text-primary-light-content mt-1">
                  Completed on March 15, 2024
                </div>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 bg-base-100 rounded-lg">
              <Indicator color="info" size="sm" class="mt-1" />
              <div class="flex-1">
                <div class="text-sm font-medium">Design dashboard layout</div>
                <div class="text-xs text-primary-light-content mt-1">
                  In progress - 60% complete
                </div>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 bg-base-100 rounded-lg">
              <Indicator color="warning" size="sm" class="mt-1" />
              <div class="flex-1">
                <div class="text-sm font-medium">API integration testing</div>
                <div class="text-xs text-primary-light-content mt-1">
                  Pending - Waiting for backend
                </div>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 bg-base-100 rounded-lg">
              <Indicator color="slate" size="sm" class="mt-1" />
              <div class="flex-1">
                <div class="text-sm font-medium">Performance optimization</div>
                <div class="text-xs text-primary-light-content mt-1">Not started</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Notification Center with Indicators -->
        <div class="bg-base-200 p-6 rounded-lg border border-primary-stroke space-y-4">
          <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold">Notifications</h4>
            <div class="flex items-center gap-2">
              <Indicator color="error" size="sm" />
              <span class="text-xs text-primary-light-content">3 unread</span>
            </div>
          </div>

          <div class="space-y-2">
            <div class="flex items-start gap-3 p-3 bg-base-100 rounded-lg">
              <Indicator color="info" size="sm" class="mt-1" />
              <div class="flex-1">
                <div class="text-sm font-medium">System Update Available</div>
                <div class="text-xs text-primary-light-content mt-1">
                  Version 2.4.0 is ready to install
                </div>
              </div>
              <span class="text-xs text-primary-light-content whitespace-nowrap">5m ago</span>
            </div>

            <div class="flex items-start gap-3 p-3 bg-base-100 rounded-lg">
              <Indicator color="success" size="sm" class="mt-1" />
              <div class="flex-1">
                <div class="text-sm font-medium">Backup Completed</div>
                <div class="text-xs text-primary-light-content mt-1">
                  Daily backup finished successfully
                </div>
              </div>
              <span class="text-xs text-primary-light-content whitespace-nowrap">1h ago</span>
            </div>

            <div class="flex items-start gap-3 p-3 bg-base-100 rounded-lg">
              <Indicator color="warning" size="sm" class="mt-1" />
              <div class="flex-1">
                <div class="text-sm font-medium">Storage Almost Full</div>
                <div class="text-xs text-primary-light-content mt-1">
                  85% of storage capacity used
                </div>
              </div>
              <span class="text-xs text-primary-light-content whitespace-nowrap">2h ago</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import Indicator from '@/components/ui/Indicator.vue'
</script>
