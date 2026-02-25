<template>
  <StepperRoot
    v-model="modelValue"
    :orientation="orientation"
    :linear="linear"
    class="mb-12"
    :class="['flex w-full', orientation === 'horizontal' ? 'gap-2' : 'flex-col gap-4']"
  >
    <StepperItem
      v-for="(step, index) in steps"
      :key="step.value"
      :step="step.value"
      :completed="step.completed"
      :disabled="step.disabled"
      v-slot="{ state }"
      :class="[
        'group relative cursor-pointer',
        orientation === 'horizontal'
          ? 'flex flex-1 justify-center gap-2 px-2'
          : 'flex items-start gap-3',
      ]"
    >
      <!-- Step Indicator (circular button with icon) -->
      <StepperTrigger
        :class="[
          'inline-flex shrink-0 items-center justify-center rounded-full shadow-sm transition-all duration-200',
          'focus-visible:ring-accent border-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
          sizeClasses.indicator,
          getIndicatorClasses(state),
        ]"
        :disabled="step.disabled"
      >
        <StepperIndicator class="flex items-center justify-center">
          <!-- Completed state: checkmark -->
          <i v-if="state === 'completed'" class="fa-solid fa-check" :class="sizeClasses.icon" />
          <!-- Active or Inactive: show custom icon or step number -->
          <template v-else>
            <i v-if="step.icon" :class="[step.icon, sizeClasses.icon]" />
            <span v-else :class="sizeClasses.number">{{ index + 1 }}</span>
          </template>
        </StepperIndicator>
      </StepperTrigger>

      <!-- Separator line (horizontal mode - absolutely positioned) -->
      <StepperSeparator
        v-if="orientation === 'horizontal' && index !== steps.length - 1"
        :class="[
          'absolute block h-0.5 shrink-0 rounded-full transition-colors duration-200',
          sizeClasses.separatorPosition,
          getSeparatorClasses(state),
        ]"
      />

      <!-- Title and Description (horizontal mode - below indicator) -->
      <div
        v-if="orientation === 'horizontal'"
        :class="[
          'absolute left-0 mt-2 w-full text-center transition-opacity duration-200',
          sizeClasses.textPosition,
          step.disabled ? 'opacity-50' : '',
        ]"
      >
        <StepperTitle
          :class="[
            'font-medium transition-colors duration-200',
            sizeClasses.title,
            getTitleClasses(state),
          ]"
        >
          {{ step.title }}
        </StepperTitle>
        <StepperDescription
          v-if="step.description"
          :class="[
            'hidden transition-colors duration-200 sm:block',
            sizeClasses.description,
            getDescriptionClasses(state),
          ]"
        >
          {{ step.description }}
        </StepperDescription>
      </div>

      <!-- Title and Description (vertical mode - inline with indicator) -->
      <div v-else class="flex flex-col pt-1">
        <StepperTitle
          :class="[
            'font-medium transition-colors duration-200',
            sizeClasses.title,
            getTitleClasses(state),
          ]"
        >
          {{ step.title }}
        </StepperTitle>
        <StepperDescription
          v-if="step.description"
          :class="[
            'transition-colors duration-200',
            sizeClasses.description,
            getDescriptionClasses(state),
          ]"
        >
          {{ step.description }}
        </StepperDescription>

        <!-- Separator line (vertical mode) -->
        <StepperSeparator
          v-if="index !== steps.length - 1"
          :class="[
            'mt-2 ml-4 h-8 w-0.5 rounded-full transition-colors duration-200',
            getSeparatorClasses(state),
          ]"
        />
      </div>
    </StepperItem>
  </StepperRoot>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  StepperRoot,
  StepperItem,
  StepperTrigger,
  StepperIndicator,
  StepperTitle,
  StepperDescription,
  StepperSeparator,
} from 'reka-ui'

export interface StepperStep {
  /** Unique step value/identifier */
  value: number
  /** Step title text */
  title: string
  /** Optional step description */
  description?: string
  /** Optional FontAwesome icon class (e.g., 'fa-solid fa-upload') */
  icon?: string
  /** Whether the step is completed */
  completed?: boolean
  /** Whether the step is disabled */
  disabled?: boolean
}

export type StepperSize = 'sm' | 'md' | 'lg'
export type StepperOrientation = 'horizontal' | 'vertical'

interface Props {
  /** Array of step definitions */
  steps: StepperStep[]
  /** Size variant */
  size?: StepperSize
  /** Stepper orientation */
  orientation?: StepperOrientation
  /** Whether steps must be completed sequentially */
  linear?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  orientation: 'horizontal',
  linear: true,
})

const modelValue = defineModel<number>({ default: 1 })

// Size classes for different elements
const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return {
        indicator: 'w-8 h-8',
        icon: 'text-xs',
        number: 'text-xs font-semibold',
        title: 'text-xs',
        description: 'text-xs',
        separatorPosition: 'top-4 left-[calc(50%+20px)] right-[calc(-50%+12px)]',
        textPosition: 'top-full',
      }
    case 'lg':
      return {
        indicator: 'w-12 h-12',
        icon: 'text-lg',
        number: 'text-lg font-semibold',
        title: 'text-base',
        description: 'text-sm',
        separatorPosition: 'top-6 left-[calc(50%+32px)] right-[calc(-50%+24px)]',
        textPosition: 'top-full',
      }
    default: // md
      return {
        indicator: 'w-10 h-10',
        icon: 'text-sm',
        number: 'text-sm font-semibold',
        title: 'text-sm',
        description: 'text-xs',
        separatorPosition: 'top-5 left-[calc(50%+26px)] right-[calc(-50%+18px)]',
        textPosition: 'top-full',
      }
  }
})

type StepState = 'active' | 'completed' | 'inactive'

// Indicator (circle) styling based on state
function getIndicatorClasses(state: StepState): string {
  switch (state) {
    case 'completed':
      return 'bg-green-200 text-green-600 border-green-500 hover:bg-green-500'
    case 'active':
      return 'bg-accent text-accent-content border-accent shadow-lg shadow-accent/30'
    default: // inactive
      return 'bg-base-100 text-sage-400 border-primary-stroke group-data-[disabled]:opacity-50 group-data-[disabled]:cursor-not-allowed'
  }
}

// Title styling based on state
function getTitleClasses(state: StepState): string {
  switch (state) {
    case 'completed':
      return 'text-sage-700 dark:text-sage-200'
    case 'active':
      return 'text-sage-900 dark:text-white'
    default: // inactive
      return 'text-sage-400 dark:text-sage-500'
  }
}

// Description styling based on state
function getDescriptionClasses(state: StepState): string {
  switch (state) {
    case 'completed':
      return 'text-sage-500 dark:text-sage-400'
    case 'active':
      return 'text-sage-600 dark:text-sage-300'
    default: // inactive
      return 'text-sage-400 dark:text-sage-600'
  }
}

// Separator styling based on state
function getSeparatorClasses(state: StepState): string {
  switch (state) {
    case 'completed':
      return 'bg-primary'
    default: // active or inactive
      return 'bg-sage-200 dark:bg-sage-700'
  }
}
</script>
