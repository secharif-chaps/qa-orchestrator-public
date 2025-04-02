<template>
  <div class="container mx-auto p-4">
    <Card title="Settings">
      <div class="space-y-6">
        <div>
          <h2 class="text-xl font-semibold mb-2">Language Settings</h2>
          <p class="text-gray-600 mb-4">Choose your preferred language for the application</p>
        </div>

        <div>
          <h2 class="text-xl font-semibold mb-2">Theme Settings</h2>
          <p class="text-gray-600 mb-4">Customize the appearance of your application</p>
        </div>

        <div>
          <h2 class="text-xl font-semibold mb-2">Notification Settings</h2>
          <p class="text-gray-600 mb-4">Manage your notification preferences</p>
        </div>
      </div>
    </Card>
  </div>
</template>

<style scoped>
.settings-section {
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.settings-section:last-child {
  border-bottom: none;
}
</style>
