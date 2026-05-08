<template>
  <div class="flex flex-col gap-4">
    <!-- Teams config -->
    <template v-if="channelType === 'teams'">
      <Input
        id="teams-workflow-url"
        v-model="teamsWorkflowUrl"
        :label="t('stream.form.teamsUrl')"
        :placeholder="t('stream.form.teamsUrlPlaceholder')"
        icon="fab fa-microsoft"
        required
      />
    </template>

    <!-- Slack Webhook config -->
    <template v-else-if="channelType === 'slack_webhook'">
      <Input
        id="slack-webhook-url"
        v-model="slackWebhookUrl"
        :label="t('stream.form.slackUrl')"
        :placeholder="t('stream.form.slackUrlPlaceholder')"
        icon="fab fa-slack"
        required
      />
    </template>

    <!-- Webhook config -->
    <template v-else-if="channelType === 'webhook'">
      <Input
        id="webhook-url"
        v-model="webhookUrl"
        :label="t('stream.form.webhookUrl')"
        :placeholder="t('stream.form.webhookUrlPlaceholder')"
        icon="fas fa-plug"
        required
      />

      <div class="flex flex-col gap-1">
        <Label id="webhook-method" size="sm">{{ t('stream.form.webhookMethod') }}</Label>
        <div class="flex gap-4">
          <Radio
            id="webhook-method-post"
            v-model="webhookMethod"
            value="POST"
            name="webhook-method"
            label="POST"
          />
          <Radio
            id="webhook-method-put"
            v-model="webhookMethod"
            value="PUT"
            name="webhook-method"
            label="PUT"
          />
        </div>
      </div>

      <!-- Custom headers -->
      <div class="flex flex-col gap-2">
        <Label id="webhook-headers" size="sm">{{ t('stream.form.webhookHeaders') }}</Label>
        <div v-for="(header, index) in webhookHeaders" :key="index" class="flex items-center gap-2">
          <Input
            :id="`webhook-header-key-${index}`"
            v-model="header.key"
            :placeholder="t('stream.form.webhookHeaderKey')"
            class="flex-1"
          />
          <Input
            :id="`webhook-header-value-${index}`"
            v-model="header.value"
            :placeholder="t('stream.form.webhookHeaderValue')"
            class="flex-1"
          />
          <Button
            variant="tertiary"
            icon="fa-times"
            :title="t('stream.form.webhookRemoveHeader')"
            @click="removeHeader(index)"
          />
        </div>
        <Button
          variant="tertiary"
          size="sm"
          icon="fa-plus"
          :label="t('stream.form.webhookAddHeader')"
          @click="addHeader"
        />
      </div>

      <Input
        id="webhook-secret"
        v-model="webhookSecret"
        :label="t('stream.form.webhookSecret')"
        :placeholder="t('stream.form.webhookSecretPlaceholder')"
        type="password"
      />
    </template>

    <!-- Test connection button -->
    <div class="flex items-center gap-3">
      <Button
        variant="secondary"
        size="sm"
        icon="fa-plug"
        :label="t('stream.form.testConnection')"
        :loading="isTesting"
        @click="handleTestConnection"
      />
      <span v-if="testResult !== null" class="text-sm">
        <template v-if="testResult">
          <Icon icon="fa-check-circle" class="text-success mr-1" />
          {{ t('stream.form.testSuccess') }}
        </template>
        <template v-else>
          <Icon icon="fa-times-circle" class="text-error mr-1" />
          {{ t('stream.form.testError') }}
          <span v-if="testError" class="text-secondary ml-1">{{
            t('common.inParentheses', { value: testError })
          }}</span>
        </template>
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from 'vue'
import { Button, Icon, Input, Label, Radio } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import { useTestConnection } from '@/mutations/streams'
import type {
  ChannelConfig,
  ChannelType,
  SlackWebhookConfig,
  TeamsConfig,
  WebhookConfig,
} from '@/types/stream'

interface Props {
  channelType: ChannelType
}

const { channelType } = defineProps<Props>()
const model = defineModel<ChannelConfig>({ required: true })
const { t } = useI18n()

const { testConnection, isLoading: isTesting } = useTestConnection()
const testResult = ref<boolean | null>(null)
const testError = ref<string>('')

// Local refs for each channel config type
const teamsWorkflowUrl = ref('')
const slackWebhookUrl = ref('')
const webhookUrl = ref('')
const webhookMethod = ref<'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'>('POST')
const webhookHeaders = reactive<{ key: string; value: string }[]>([])
const webhookSecret = ref('')

// Initialize from model value
const initFromModel = () => {
  const config = model.value
  if (!config) return

  if (channelType === 'teams') {
    const teams = config as TeamsConfig
    teamsWorkflowUrl.value = teams.workflow_url || ''
  } else if (channelType === 'slack_webhook') {
    const slack = config as SlackWebhookConfig
    slackWebhookUrl.value = slack.webhook_url || ''
  } else if (channelType === 'webhook') {
    const webhook = config as WebhookConfig
    webhookUrl.value = webhook.url || ''
    webhookMethod.value = webhook.method || 'POST'
    webhookSecret.value = webhook.secret || ''
    const headers = webhook.headers
    webhookHeaders.length = 0
    if (headers) {
      Object.entries(headers).forEach(([key, value]) => {
        webhookHeaders.push({ key, value })
      })
    }
  }
}

initFromModel()

// Sync local refs back to model
watch(
  [
    teamsWorkflowUrl,
    slackWebhookUrl,
    webhookUrl,
    webhookMethod,
    webhookSecret,
    () => [...webhookHeaders],
  ],
  () => {
    if (channelType === 'teams') {
      model.value = { workflow_url: teamsWorkflowUrl.value }
    } else if (channelType === 'slack_webhook') {
      model.value = { webhook_url: slackWebhookUrl.value }
    } else if (channelType === 'webhook') {
      const headers: Record<string, string> = {}
      webhookHeaders.forEach((h) => {
        if (h.key.trim()) headers[h.key] = h.value
      })
      model.value = {
        url: webhookUrl.value,
        method: webhookMethod.value,
        ...(Object.keys(headers).length > 0 ? { headers } : {}),
        ...(webhookSecret.value ? { secret: webhookSecret.value } : {}),
      }
    }
  },
  { deep: true },
)

// Reset all local state when channel type changes
watch(
  () => channelType,
  () => {
    teamsWorkflowUrl.value = ''
    slackWebhookUrl.value = ''
    webhookUrl.value = ''
    webhookMethod.value = 'POST'
    webhookHeaders.length = 0
    webhookSecret.value = ''
    testResult.value = null
    testError.value = ''
  },
)

const addHeader = () => {
  webhookHeaders.push({ key: '', value: '' })
}

const removeHeader = (index: number) => {
  webhookHeaders.splice(index, 1)
}

const handleTestConnection = async () => {
  testResult.value = null
  testError.value = ''

  try {
    const result = await testConnection({
      channel_type: channelType,
      channel_config: model.value,
    })
    testResult.value = result.success
    if (!result.success && result.error) {
      testError.value = result.error
    }
  } catch {
    testResult.value = false
    testError.value = t('common.errors.unexpected')
  }
}
</script>
