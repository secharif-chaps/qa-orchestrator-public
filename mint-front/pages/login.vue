<template>
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
      <Card class="p-8">
        <div class="mb-8">
          <h2 class="text-2xl font-semibold text-center">
            {{ $t('login.title') }}
          </h2>
        </div>

        <form class="space-y-6" @submit.prevent="handleLogin">
          <div class="space-y-4">
            <div>
              <label for="email" class="block text-sm mb-1">
                {{ $t('login.email.label') }}
              </label>
              <OInput
                id="email"
                v-model="email"
                name="email"
                type="email"
                required
                :placeholder="$t('login.email.placeholder')"
                data-testid="email-input"
              />
            </div>
            <div>
              <label for="password" class="block text-sm mb-1">
                {{ $t('login.password.label') }}
              </label>
              <OInput
                id="password"
                v-model="password"
                name="password"
                type="password"
                required
                :placeholder="$t('login.password.placeholder')"
                data-testid="password-input"
              />
              <div class="flex justify-end mt-1">
                <a href="#" class="text-sm text-[#6366F1] hover:text-[#4F46E5]">
                  {{ $t('login.password.forgot') }}
                </a>
              </div>
            </div>
          </div>

          <div v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700" data-testid="error-message">
            {{ error }}
          </div>

          <div>
            <OButton
              type="submit"
              :loading="loading"
              variant="primary"
              class="w-full"
              @click="handleLogin"
              data-testid="submit-button"
            >
              {{ $t('login.submit') }}
            </OButton>
          </div>
        </form>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { OInput, OButton } from '@owlint/feathers-vue'
import Card from '~/components/global/card.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const handleLogin = async () => {
  error.value = ''
  loading.value = true

  try {
    // TODO: Implement actual authentication logic here
    // For now, we'll simulate a successful login with a delay
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    if (email.value !== '' && password.value !== '') {
      router.push('/companies')
    } else {
      error.value = t('login.errors.invalidCredentials')
    }
  } catch (e) {
    error.value = t('login.errors.connectionError')
  } finally {
    loading.value = false
  }
}
</script> 