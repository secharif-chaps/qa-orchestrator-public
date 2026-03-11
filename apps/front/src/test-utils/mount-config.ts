import type { MountingOptions } from '@vue/test-utils'

type GlobalMountOptions = NonNullable<MountingOptions<unknown>['global']>

export const vSanitizeHtml = {
  mounted: (el: HTMLElement, binding: { value: string }) => {
    el.innerHTML = binding.value || ''
  },
  updated: (el: HTMLElement, binding: { value: string }) => {
    el.innerHTML = binding.value || ''
  },
}

export const iconStub = {
  props: ['icon'],
  template: '<span class="icon" :data-icon="icon"></span>',
}

export const buttonStub = {
  props: ['label', 'icon', 'variant', 'size'],
  template: '<button><span v-if="icon" :class="icon"></span>{{ label }}</button>',
}

export const badgeStub = {
  props: ['number', 'size', 'variant'],
  template: '<span class="badge"></span>',
}

export const defaultGlobalConfig: GlobalMountOptions = {
  directives: {
    'sanitize-html': vSanitizeHtml,
  },
  stubs: {
    Icon: iconStub,
    Button: buttonStub,
  },
}
