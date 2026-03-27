<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { signIn } = useAuth()

const error = ref('')
const isLoading = ref(false)

const handleLogin = async () => {
  try {
    isLoading.value = true
    error.value = ''

    // Redirect to Keycloak login
    await signIn()
  } catch (err) {
    console.error('Login error:', err)
    error.value = t('common.login.errors.genericError', 'An error occurred during login')
    isLoading.value = false
  }
}

handleLogin()
</script>
