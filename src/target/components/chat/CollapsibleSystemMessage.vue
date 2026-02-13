<template>
  <!-- Non-collapsible: single short line without truncation -->
  <div
    v-if="!isCollapsible"
    class="text-primary-medium border border-gray-200 bg-white text-sm"
    :class="positionClasses"
  >
    <div class="flex items-center justify-between gap-2 px-3 py-2">
      <div class="min-w-0 flex-1">
        <p class="text-left">
          {{ firstLine }}
        </p>
      </div>
      <div v-if="hasError" class="flex shrink-0 items-center">
        <span
          class="size-2 rounded-full bg-red-500"
          aria-label="Error indicator"
        />
      </div>
    </div>
  </div>

  <!-- Collapsible: multi-line or truncated content -->
  <Collapsible.Root
    v-else
    :open="isExpanded"
    class="text-primary-medium w-full border border-gray-200 bg-white text-sm"
    :class="positionClasses"
    @update:open="handleToggle"
  >
    <Collapsible.Trigger
      class="flex w-full cursor-pointer items-center justify-between gap-2 p-3"
    >
      <div class="min-w-0 flex-1">
        <p class="text-left">
          {{ firstLine }}
        </p>
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <span
          v-if="hasError"
          class="size-2 rounded-full bg-red-500"
          aria-label="Error indicator"
        />
        <Icon
          :icon="isExpanded ? 'fa-chevron-up' : 'fa-chevron-down'"
          aria-label="Expand/Collapse"
          class="text-gray-500"
        />
      </div>
    </Collapsible.Trigger>
    <Collapsible.Content
      class="data-[state=closed]:animate-slideUp data-[state=open]:animate-slideDown overflow-hidden"
    >
      <div class="border-t border-gray-200 px-3 py-1.5">
        <div
          v-sanitize-html="renderedContent"
          class="markdown-content text-base-content whitespace-normal"
        />
      </div>
    </Collapsible.Content>
  </Collapsible.Root>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue';
import { Collapsible } from 'reka-ui/namespaced';
import { computed } from 'vue';
import { useMarkdown } from '~/composables/useMarkdown';
import { useStringUtils } from '~/composables/useStringUtils';
import type { Message } from '~/types/conversation';

interface Props {
  message: Message;
  isExpanded: boolean;
  isFirst?: boolean;
  isLast?: boolean;
}

interface Emits {
  (e: 'toggle'): void;
}

const {
  message,
  isExpanded,
  isFirst = true,
  isLast = true,
} = defineProps<Props>();
const emit = defineEmits<Emits>();

const { unescapeString } = useStringUtils();
const { toHtml } = useMarkdown({
  gfm: true,
  breaks: false,
});

// Extract text content from message contents
const messageContent = computed(() => {
  if (
    !message.contents ||
    !Array.isArray(message.contents) ||
    message.contents.length === 0
  ) {
    return '';
  }
  const content = message.contents[0] as { content: string };
  return unescapeString(content.content || '').trim();
});

// Get first line of message for collapsed display
const firstLine = computed(() => {
  const content = messageContent.value;
  if (!content) return '';

  // Split by newline and get first non-empty line
  const lines = content.split('\n').filter((line) => line.trim());
  return lines[0] || content;
});

// Single line: collapsible only if it would be truncated (ellipsis)
// ~80 chars is approximate threshold for truncation in the container
const firstLineReachTruncation = computed(() => {
  const TRUNCATION_THRESHOLD = 80;
  return firstLine.value.length > TRUNCATION_THRESHOLD;
});

// Get content without the first line (for expanded view to avoid repetition)
// Except if first line is not truncated, we keep it in expanded view
const messageContentFormatted = computed(() => {
  const content = messageContent.value;
  if (!content) return '';

  if (firstLineReachTruncation.value) {
    // If first line is truncated, show full content in expanded view
    return content;
  }

  const lines = content.split('\n');
  // Find index of first non-empty line
  const firstNonEmptyIndex = lines.findIndex((line) => line.trim());
  if (firstNonEmptyIndex === -1) return '';

  // Return everything after the first non-empty line
  const remainingLines = lines.slice(firstNonEmptyIndex + 1);
  return remainingLines.join('\n').trim();
});

// Render content without first line as HTML (for expanded description)
const renderedContent = toHtml(messageContentFormatted);

// Check if message has error status in metadata
const hasError = computed(() => {
  return message.metadata?.status === 'error';
});

// Check if message should be collapsible
// Not collapsible if: single line AND short enough to not be truncated
const isCollapsible = computed(() => {
  const content = messageContent.value;
  if (!content) return false;

  const lines = content.split('\n').filter((line) => line.trim());

  // Multi-line content is always collapsible
  if (lines.length > 1) return true;

  // Single line: collapsible only if it would be truncated
  return firstLineReachTruncation.value;
});

// Computed classes for stacking messages
const positionClasses = computed(() => {
  const classes: string[] = [];

  // Negative margin for non-first items to merge borders
  if (!isFirst) classes.push('-mt-px');

  // Border radius based on position
  if (isFirst && isLast) {
    classes.push('rounded-sm');
  } else if (isFirst) {
    classes.push('rounded-t-sm', 'rounded-b-none');
  } else if (isLast) {
    classes.push('rounded-b-sm', 'rounded-t-none');
  } else {
    classes.push('rounded-none');
  }

  return classes.join(' ');
});

// Handle toggle click
const handleToggle = (open: boolean) => {
  if (open !== isExpanded) {
    emit('toggle');
  }
};
</script>
