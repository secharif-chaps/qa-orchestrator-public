<template>
  <div class="min-h-screen">
    <div class="">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">UI Components Showcase</h1>
        <p class="text-secondary">Custom theme-aware components for the application</p>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-bg1 border border-border-2 rounded-lg overflow-hidden">
        <div class="border-b border-border-2">
          <div class="flex">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'px-6 py-3 text-sm font-medium transition-all relative border-b-2',
                activeTab === tab.id
                  ? 'text-primary bg-primary/5  border-primary'
                  : 'text-secondary  hover:bg-bg2/50 border-transparent',
              ]"
            >
              <i :class="tab.icon" class="mr-2"></i>
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="p-8">
          <!-- Badge Tab -->
          <div v-if="activeTab === 'badge'" class="space-y-8">
            <!-- Basic Badges -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Basic Badges</h2>
              <div class="flex flex-wrap gap-3">
                <Badge variant="primary" label="Primary" />
                <Badge variant="success" label="Success" />
                <Badge variant="warning" label="Warning" />
                <Badge variant="error" label="Error" />
                <Badge variant="info" label="Info" />
                <Badge variant="slate" label="Slate" />
              </div>
            </section>

            <!-- Badges with Icons -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">With Icons</h2>
              <div class="flex flex-wrap gap-3">
                <Badge variant="primary" icon="fa fa-star" label="Featured" />
                <Badge variant="success" icon="fa fa-check" label="Approved" />
                <Badge variant="warning" icon="fa fa-exclamation-triangle" label="Pending" />
                <Badge variant="error" icon="fa fa-times-circle" label="Rejected" />
                <Badge variant="info" icon="fa fa-info-circle" label="Information" />
                <Badge variant="slate" icon="fa fa-clock" label="Scheduled" />
              </div>
            </section>

            <!-- Badges with Dots -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">With Status Dots</h2>
              <div class="flex flex-wrap gap-3">
                <Badge variant="success" dot label="Online" />
                <Badge variant="warning" dot label="Away" />
                <Badge variant="error" dot label="Offline" />
                <Badge variant="slate" dot label="Unknown" />
              </div>
            </section>

            <!-- Different Sizes -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Size Variations</h2>
              <div class="space-y-3">
                <div class="flex items-center gap-3">
                  <span class="text-sm text-secondary w-20">Extra Small:</span>
                  <Badge variant="primary" label="XS Badge" size="xs" />
                  <Badge variant="success" icon="fa fa-check" label="Done" size="xs" />
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm text-secondary w-20">Small:</span>
                  <Badge variant="primary" label="SM Badge" size="sm" />
                  <Badge variant="warning" icon="fa fa-star" label="Featured" size="sm" />
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm text-secondary w-20">Medium:</span>
                  <Badge variant="primary" label="MD Badge" size="md" />
                  <Badge variant="info" icon="fa fa-info" label="Information" size="md" />
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm text-secondary w-20">Large:</span>
                  <Badge variant="primary" label="LG Badge" size="lg" />
                  <Badge variant="error" icon="fa fa-exclamation" label="Alert" size="lg" />
                </div>
              </div>
            </section>

            <!-- Dismissible Badges -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Dismissible</h2>
              <div class="flex flex-wrap gap-3">
                <Badge
                  v-if="showDismissible1"
                  variant="primary"
                  label="Dismissible"
                  dismissible
                  @dismiss="showDismissible1 = false"
                />
                <Badge
                  v-if="showDismissible2"
                  variant="warning"
                  icon="fa fa-tag"
                  label="Tag"
                  dismissible
                  rounded
                  @dismiss="showDismissible2 = false"
                />
                <Badge
                  v-if="showDismissible3"
                  variant="success"
                  label="Filter Applied"
                  dismissible
                  @dismiss="showDismissible3 = false"
                />
                <button
                  v-if="!showDismissible1 || !showDismissible2 || !showDismissible3"
                  @click="resetDismissible"
                  class="text-primary text-sm hover:underline"
                >
                  Reset dismissed badges
                </button>
              </div>
            </section>
          </div>

          <!-- Alert Tab -->
          <div v-if="activeTab === 'alert'" class="space-y-8">
            <!-- Basic Alerts -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Basic Alerts</h2>
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
                @click="((showAlert1 = true), (showAlert2 = true))"
                class="text-primary text-sm hover:underline"
              >
                Reset dismissed alerts
              </button>
            </section>

            <!-- Alerts with Actions -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">With Actions</h2>
              <Alert
                variant="info"
                title="New Update Available"
                message="A new version of the application is available."
                icon="fa fa-download"
                decoration-icon="fa fa-rocket"
              >
                <template #actions>
                  <button
                    class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors text-sm"
                  >
                    Update Now
                  </button>
                  <button
                    class="px-4 py-2 text-secondary hover:text-base transition-colors text-sm"
                  >
                    Remind Me Later
                  </button>
                </template>
              </Alert>
              <Alert
                variant="warning"
                title="Action Required"
                message="Please complete your profile to access all features."
                icon="fa fa-user-circle"
              >
                <template #actions>
                  <button
                    class="px-4 py-2 bg-warning text-white rounded-lg hover:bg-warning/90 transition-colors text-sm"
                  >
                    Complete Profile
                  </button>
                </template>
              </Alert>
            </section>

            <!-- Custom Content -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Custom Content</h2>
              <Alert variant="success" icon="fa fa-gift">
                <div>
                  <h3 class="font-semibold mb-2">Welcome Gift!</h3>
                  <p class="text-sm text-secondary mb-3">
                    As a new user, you've received 100 free tokens to get started.
                  </p>
                  <ul class="text-sm text-secondary space-y-1">
                    <li>• Valid for 30 days</li>
                    <li>• Use for any module</li>
                    <li>• No credit card required</li>
                  </ul>
                </div>
              </Alert>
            </section>
          </div>

          <!-- Button Tab -->
          <div v-if="activeTab === 'button'" class="space-y-8">
            <!-- Button Hierarchy (Neutral Color) -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Button Hierarchy - Neutral</h2>
              <div class="flex flex-wrap gap-3">
                <Button variant="primary" label="Primary Action" />
                <Button variant="secondary" label="Secondary Action" />
                <Button variant="tertiary" label="Tertiary Action" />
              </div>
            </section>

            <!-- Danger Color Variants -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Danger Color Variants</h2>
              <div class="flex flex-wrap gap-3">
                <Button variant="primary" color="danger" label="Delete (Primary)" />
                <Button variant="secondary" color="danger" label="Remove (Secondary)" />
                <Button variant="tertiary" color="danger" label="Clear (Tertiary)" />
              </div>
            </section>

            <!-- Warning Color Variants -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Warning Color Variants</h2>
              <div class="flex flex-wrap gap-3">
                <Button variant="primary" color="warning" label="Archive (Primary)" />
                <Button variant="secondary" color="warning" label="Suspend (Secondary)" />
                <Button variant="tertiary" color="warning" label="Hide (Tertiary)" />
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
                  <Button variant="primary" color="danger" icon="fa fa-trash" label="Delete" />
                  <Button
                    variant="secondary"
                    color="warning"
                    icon="fa fa-exclamation-triangle"
                    label="Warning"
                  />
                </div>
                <div class="flex flex-wrap gap-3">
                  <span class="text-sm text-secondary w-24">Right Icons:</span>
                  <Button
                    variant="primary"
                    icon="fa fa-arrow-right"
                    icon-position="right"
                    label="Continue"
                  />
                  <Button
                    variant="secondary"
                    icon="fa fa-external-link"
                    icon-position="right"
                    label="Open"
                  />
                  <Button
                    variant="tertiary"
                    icon="fa fa-chevron-down"
                    icon-position="right"
                    label="More"
                  />
                </div>
                <div class="flex flex-wrap gap-3">
                  <span class="text-sm text-secondary w-24">Icon Only:</span>
                  <Button variant="primary" icon="fa fa-heart" icon-only />
                  <Button variant="secondary" icon="fa fa-bookmark" icon-only />
                  <Button variant="tertiary" icon="fa fa-share" icon-only />
                  <Button variant="tertiary" color="danger" icon="fa fa-times" icon-only />
                  <Button variant="tertiary" color="warning" icon="fa fa-exclamation" icon-only />
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
                  <Button variant="tertiary" icon="fa fa-info" icon-only size="sm" />
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm text-secondary w-20">Medium:</span>
                  <Button variant="primary" label="Medium" size="md" />
                  <Button variant="secondary" icon="fa fa-save" label="Save" size="md" />
                  <Button variant="tertiary" icon="fa fa-more" icon-only size="md" />
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm text-secondary w-20">Large:</span>
                  <Button variant="primary" label="Large" size="lg" />
                  <Button variant="secondary" icon="fa fa-upload" label="Upload" size="lg" />
                  <Button variant="tertiary" icon="fa fa-search" icon-only size="lg" />
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
                    variant="tertiary"
                    label="Loading"
                    :loading="loadingButtons.tertiary"
                    @click="simulateLoading('tertiary')"
                  />
                  <Button
                    variant="primary"
                    color="danger"
                    label="Deleting"
                    :loading="loadingButtons.danger"
                    @click="simulateLoading('danger')"
                  />
                </div>
                <div class="flex flex-wrap gap-3">
                  <span class="text-sm text-secondary w-20">Disabled:</span>
                  <Button variant="primary" label="Disabled" disabled />
                  <Button variant="secondary" label="Disabled" disabled />
                  <Button variant="tertiary" label="Disabled" disabled />
                  <Button variant="primary" color="danger" label="Disabled" disabled />
                  <Button variant="secondary" color="warning" label="Disabled" disabled />
                </div>
              </div>
            </section>

            <!-- Rounded Buttons -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Rounded (Pill) Style</h2>
              <div class="flex flex-wrap gap-3">
                <Button variant="primary" label="Rounded" rounded />
                <Button variant="secondary" icon="fa fa-heart" label="Like" rounded />
                <Button variant="tertiary" icon="fa fa-share" icon-only rounded />
                <Button variant="primary" icon="fa fa-play" icon-only rounded size="lg" />
              </div>
            </section>

            <!-- Real-world Button Examples -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Real-world Use Cases</h2>
              <div class="space-y-4">
                <!-- Action Bar -->
                <div class="bg-bg1 p-4 rounded-lg border border-border-2">
                  <h3 class="text-sm font-medium mb-3">Action Bar</h3>
                  <div class="flex flex-wrap gap-2">
                    <Button variant="primary" icon="fa fa-plus" label="Create" />
                    <Button variant="secondary" icon="fa fa-download" label="Export" />
                    <Button variant="tertiary" icon="fa fa-filter" label="Filter" />
                    <Button variant="secondary" color="danger" icon="fa fa-trash" label="Delete" />
                    <Button variant="tertiary" icon="fa fa-more-vertical" icon-only />
                  </div>
                </div>

                <!-- Form Actions -->
                <div class="bg-bg1 p-4 rounded-lg border border-border-2">
                  <h3 class="text-sm font-medium mb-3">Form Actions</h3>
                  <div class="flex justify-end gap-2">
                    <Button variant="tertiary" label="Cancel" />
                    <Button variant="secondary" label="Save Draft" />
                    <Button variant="primary" label="Publish" />
                  </div>
                </div>

                <!-- Destructive Actions -->
                <div class="bg-bg1 p-4 rounded-lg border border-border-2">
                  <h3 class="text-sm font-medium mb-3">
                    Destructive Actions (Different Hierarchies)
                  </h3>
                  <div class="space-y-2">
                    <div class="text-xs text-secondary mb-2">When delete is the main action:</div>
                    <div class="flex gap-2">
                      <Button variant="tertiary" label="Cancel" />
                      <Button
                        variant="primary"
                        color="danger"
                        icon="fa fa-trash"
                        label="Delete Item"
                      />
                    </div>
                    <div class="text-xs text-secondary mb-2 mt-4">
                      When delete is a secondary action:
                    </div>
                    <div class="flex gap-2">
                      <Button variant="primary" icon="fa fa-save" label="Save Changes" />
                      <Button
                        variant="secondary"
                        color="danger"
                        icon="fa fa-trash"
                        label="Delete"
                      />
                    </div>
                    <div class="text-xs text-secondary mb-2 mt-4">
                      When delete is a tertiary action:
                    </div>
                    <div class="flex gap-2">
                      <Button variant="primary" icon="fa fa-edit" label="Edit" />
                      <Button variant="secondary" icon="fa fa-copy" label="Duplicate" />
                      <Button variant="tertiary" color="danger" icon="fa fa-trash" label="Delete" />
                    </div>
                  </div>
                </div>

                <!-- Navigation -->
                <div class="bg-bg1 p-4 rounded-lg border border-border-2">
                  <h3 class="text-sm font-medium mb-3">Navigation</h3>
                  <div class="flex justify-between">
                    <Button variant="secondary" icon="fa fa-chevron-left" label="Previous" />
                    <div class="flex gap-2">
                      <Button variant="tertiary" label="1" size="sm" />
                      <Button variant="primary" label="2" size="sm" />
                      <Button variant="tertiary" label="3" size="sm" />
                    </div>
                    <Button
                      variant="secondary"
                      icon="fa fa-chevron-right"
                      icon-position="right"
                      label="Next"
                    />
                  </div>
                </div>

                <!-- Social Actions -->
                <div class="bg-bg1 p-4 rounded-lg border border-border-2">
                  <h3 class="text-sm font-medium mb-3">Social Actions</h3>
                  <div class="flex gap-2">
                    <Button variant="tertiary" icon="fa fa-heart" icon-only rounded />
                    <Button variant="tertiary" icon="fa fa-bookmark" icon-only rounded />
                    <Button variant="tertiary" icon="fa fa-share" icon-only rounded />
                    <Button variant="secondary" icon="fa fa-comment" label="Comment" rounded />
                  </div>
                </div>
              </div>
            </section>
          </div>

          <!-- Input Tab -->
          <div v-if="activeTab === 'input'" class="space-y-8">
            <!-- Basic Inputs -->
            <section class="space-y-4">
              <h2 class="text-xl font-semibold">Basic Inputs</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Input
                  v-model="inputValues.basic"
                  label="Basic Input"
                  placeholder="Enter text..."
                />
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

          <!-- Combined Examples Tab -->
          <div v-if="activeTab === 'examples'" class="space-y-8">
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
                  <Badge
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
                <div class="bg-bg2 p-6 rounded-lg border border-border-2">
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
                    <button
                      @click="submitForm"
                      class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors"
                    >
                      Submit
                    </button>
                    <button
                      @click="resetForm"
                      class="px-4 py-2 text-secondary hover:text-base transition-colors"
                    >
                      Reset
                    </button>
                  </div>
                </div>
              </div>

              <!-- Status Dashboard -->
              <div class="space-y-4">
                <h3 class="text-lg font-medium">Status Dashboard</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div class="bg-bg2 p-4 rounded-lg border border-border-2">
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-sm font-medium">API Status</span>
                      <Badge variant="success" dot label="Operational" size="sm" />
                    </div>
                    <div class="text-2xl font-bold">99.9%</div>
                    <div class="text-xs text-secondary">Uptime</div>
                  </div>
                  <div class="bg-bg2 p-4 rounded-lg border border-border-2">
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-sm font-medium">Token Usage</span>
                      <Badge variant="warning" icon="fa fa-coins" label="25 left" size="sm" />
                    </div>
                    <div class="text-2xl font-bold">475/500</div>
                    <div class="text-xs text-secondary">Tokens used</div>
                  </div>
                  <div class="bg-bg2 p-4 rounded-lg border border-border-2">
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-sm font-medium">Tasks</span>
                      <Badge variant="info" label="3 pending" size="sm" />
                    </div>
                    <div class="text-2xl font-bold">12</div>
                    <div class="text-xs text-secondary">Active tasks</div>
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
                    <p class="text-sm text-secondary mb-2">
                      System maintenance is scheduled for tonight at 2:00 AM EST.
                    </p>
                    <div class="flex gap-2">
                      <Badge variant="warning" icon="fa fa-clock" label="In 6 hours" size="sm" />
                      <Badge variant="slate" label="~30 minutes" size="sm" />
                    </div>
                  </div>
                </Alert>
              </div>
            </section>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Alert from '@/components/ui/Alert.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'

// Tab management
const activeTab = ref('badge')
const tabs = [
  { id: 'badge', label: 'Badge', icon: 'fa fa-tag' },
  { id: 'alert', label: 'Alert', icon: 'fa fa-exclamation-circle' },
  { id: 'input', label: 'Input', icon: 'fa fa-keyboard' },
  { id: 'button', label: 'Button', icon: 'fa fa-hand-pointer' },
  { id: 'examples', label: 'Examples', icon: 'fa fa-flask' },
]

// Badge demo state
const showDismissible1 = ref(true)
const showDismissible2 = ref(true)
const showDismissible3 = ref(true)

const resetDismissible = () => {
  showDismissible1.value = true
  showDismissible2.value = true
  showDismissible3.value = true
}

// Alert demo state
const showAlert1 = ref(true)
const showAlert2 = ref(true)

// Input demo state
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

// Button demo state
const loadingButtons = reactive({
  primary: false,
  secondary: false,
  tertiary: false,
  danger: false,
  warning: false,
})

const simulateLoading = (variant: keyof typeof loadingButtons) => {
  loadingButtons[variant] = true
  setTimeout(() => {
    loadingButtons[variant] = false
  }, 2000)
}

// Combined examples state
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

const removeFilter = (filter: string) => {
  const index = activeFilters.value.indexOf(filter)
  if (index > -1) {
    activeFilters.value.splice(index, 1)
  }
}

const submitForm = () => {
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

const resetForm = () => {
  formData.name = ''
  formData.email = ''
  formErrors.email = ''
  formSubmitted.value = false
}
</script>
