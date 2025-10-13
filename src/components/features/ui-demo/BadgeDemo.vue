<template>
  <div class="space-y-8">
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">Badge Component</h2>
      <p class="text-secondary">
        Circular badges for icons, numbers, and logos. Perfect for notification counts, status
        indicators, and avatar decorations.
      </p>

      <!-- Icon Badges - Primary Variant -->
      <div class="space-y-2">
        <h3 class="text-lg font-medium">Icon Badges - Primary (Solid)</h3>
        <p class="text-sm text-secondary mb-4">
          Solid backgrounds with white icons, ideal for alerts and notifications
        </p>
        <div class="grid grid-cols-2 gap-4">
          <div
            class="p-6 border border-primary-stroke rounded-2xl flex flex-wrap items-center gap-4"
          >
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="sage" icon="fa fa-home" />
              <span class="text-xs">Sage</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="almond" icon="fa fa-home" />
              <span class="text-xs">Almond</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="success" icon="fa fa-check" />
              <span class="text-xs">Success</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="warning" icon="fa fa-exclamation-triangle" />
              <span class="text-xs">Warning</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="error" icon="fa fa-times" />
              <span class="text-xs">Error</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="info" icon="fa fa-info" />
              <span class="text-xs">Info</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="accent" icon="fa fa-star" />
              <span class="text-xs">Accent</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="slate" icon="fa fa-cog" />
              <span class="text-xs">Slate</span>
            </div>
          </div>
          <div
            class="dark dark:text-sage-100 bg-sage-900 p-6 border border-primary-stroke rounded-2xl flex flex-wrap items-center gap-4"
          >
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="sage" icon="fa fa-home" />
              <span class="text-xs">Sage</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="almond" icon="fa fa-home" />
              <span class="text-xs">Almond</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="success" icon="fa fa-check" />
              <span class="text-xs">Success</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="warning" icon="fa fa-exclamation-triangle" />
              <span class="text-xs">Warning</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="error" icon="fa fa-times" />
              <span class="text-xs">Error</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="info" icon="fa fa-info" />
              <span class="text-xs">Info</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="accent" icon="fa fa-star" />
              <span class="text-xs">Accent</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="primary" color="slate" icon="fa fa-cog" />
              <span class="text-xs">Slate</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Icon Badges - Secondary Variant -->
      <div class="space-y-2">
        <h3 class="text-lg font-medium">Icon Badges - Secondary (Light)</h3>
        <p class="text-sm mb-4">Light backgrounds with colored icons, more subtle appearance</p>
        <div class="grid grid-cols-2 gap-4">
          <div
            class="p-6 border border-primary-stroke rounded-2xl flex flex-wrap items-center gap-4"
          >
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="sage" icon="fa fa-home" />
              <span class="text-xs">Sage</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="almond" icon="fa fa-home" />
              <span class="text-xs">Almond</span>
            </div>

            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="success" icon="fa fa-check" />
              <span class="text-xs">Success</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="warning" icon="fa fa-exclamation-triangle" />
              <span class="text-xs">Warning</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="error" icon="fa fa-times" />
              <span class="text-xs">Error</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="info" icon="fa fa-info" />
              <span class="text-xs">Info</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="accent" icon="fa fa-star" />
              <span class="text-xs">Accent</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="slate" icon="fa fa-cog" />
              <span class="text-xs">Slate</span>
            </div>
          </div>
          <div
            class="dark dark:text-sage-100 bg-sage-900 p-6 border border-primary-stroke rounded-2xl flex flex-wrap items-center gap-4"
          >
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="sage" icon="fa fa-home" />
              <span class="text-xs">Sage</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="almond" icon="fa fa-home" />
              <span class="text-xs">Almond</span>
            </div>

            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="success" icon="fa fa-check" />
              <span class="text-xs">Success</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="warning" icon="fa fa-exclamation-triangle" />
              <span class="text-xs">Warning</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="error" icon="fa fa-times" />
              <span class="text-xs">Error</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="info" icon="fa fa-info" />
              <span class="text-xs">Info</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="accent" icon="fa fa-star" />
              <span class="text-xs">Accent</span>
            </div>
            <div class="flex flex-col items-center gap-2">
              <Badge variant="secondary" color="slate" icon="fa fa-cog" />
              <span class="text-xs">Slate</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Number Badges -->
      <div class="space-y-2">
        <h3 class="text-lg font-medium">Number Badges</h3>
        <p class="text-sm text-secondary mb-4">
          Display counts and notifications with automatic max limit (99+)
        </p>
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex flex-col items-center gap-2">
            <Badge variant="primary" color="error" :number="5" />
            <span class="text-xs text-secondary">5 notifications</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Badge variant="primary" color="warning" :number="23" />
            <span class="text-xs text-secondary">23 pending</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Badge variant="primary" color="success" :number="150" />
            <span class="text-xs text-secondary">99+ limit</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Badge variant="secondary" color="info" :number="8" />
            <span class="text-xs text-secondary">8 messages</span>
          </div>
        </div>
      </div>

      <!-- Sizes -->
      <div class="space-y-2">
        <h3 class="text-lg font-medium">Sizes</h3>
        <p class="text-sm text-secondary mb-4">Extra small, small, medium, and large sizes</p>
        <div class="flex flex-wrap items-center gap-6">
          <div class="flex flex-col items-center gap-2">
            <Badge variant="primary" color="sage" icon="fa fa-bell" size="xs" />
            <span class="text-xs text-secondary">XS (20px)</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Badge variant="primary" color="sage" icon="fa fa-bell" size="sm" />
            <span class="text-xs text-secondary">Small (24px)</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Badge variant="primary" color="sage" icon="fa fa-bell" size="md" />
            <span class="text-xs text-secondary">Medium (40px)</span>
          </div>
          <div class="flex flex-col items-center gap-2">
            <Badge variant="primary" color="sage" icon="fa fa-bell" size="lg" />
            <span class="text-xs text-secondary">Large (48px)</span>
          </div>
        </div>
      </div>

      <!-- Usage in Context -->
      <div class="space-y-2">
        <h3 class="text-lg font-medium">Usage Examples</h3>
        <p class="text-sm text-secondary mb-4">Real-world badge applications</p>

        <!-- Notification Icons -->
        <div class="bg-base-200 p-6 rounded-lg border border-primary-stroke space-y-4">
          <h4 class="text-sm font-semibold">Notification Bar (xs size for floating badges)</h4>
          <div class="flex gap-4">
            <div class="relative">
              <i class="fa fa-bell text-2xl text-secondary"></i>
              <div class="absolute -top-2 -right-2">
                <Badge variant="primary" color="error" :number="3" size="xs" />
              </div>
            </div>
            <div class="relative">
              <i class="fa fa-envelope text-2xl text-secondary"></i>
              <div class="absolute -top-1 -right-1">
                <Badge variant="primary" color="info" :number="12" size="xs" />
              </div>
            </div>
            <div class="relative">
              <i class="fa fa-comment text-2xl text-secondary"></i>
              <div class="absolute -top-1 -right-1">
                <Badge variant="primary" color="success" :number="5" size="xs" />
              </div>
            </div>
          </div>
        </div>

        <!-- Status Indicators -->
        <div class="bg-base-200 p-6 rounded-lg border border-primary-stroke space-y-4">
          <h4 class="text-sm font-semibold">User List with Status</h4>
          <div class="space-y-3">
            <div class="flex items-center gap-3">
              <div class="relative">
                <div class="w-10 h-10 rounded-full bg-sage-light flex items-center justify-center">
                  <span class="text-sm font-semibold text-secondary">JD</span>
                </div>
                <div class="absolute -bottom-1 -right-1">
                  <Badge variant="primary" color="success" icon="fa fa-check" size="xs" />
                </div>
              </div>
              <div>
                <div class="font-medium">John Doe</div>
                <div class="text-xs text-secondary">Online</div>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="relative">
                <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center">
                  <span class="text-sm font-semibold text-rose-700">AS</span>
                </div>
                <div class="absolute -bottom-1 -right-1">
                  <Badge variant="primary" color="warning" icon="fa fa-moon" size="xs" />
                </div>
              </div>
              <div>
                <div class="font-medium">Alice Smith</div>
                <div class="text-xs text-secondary">Away</div>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="relative">
                <div class="w-10 h-10 rounded-full bg-info-light flex items-center justify-center">
                  <span class="text-sm font-semibold text-info-light-content">BJ</span>
                </div>
                <div class="absolute -bottom-1 -right-1">
                  <Badge variant="primary" color="slate" icon="fa fa-minus" size="xs" />
                </div>
              </div>
              <div>
                <div class="font-medium">Bob Johnson</div>
                <div class="text-xs text-secondary">Offline</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue'
</script>
