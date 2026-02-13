import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

export function useCssVar(
  name: string,
  target: Ref<HTMLElement | null> | null = null,
) {
  const value = ref('');

  const update = () => {
    const el = target?.value ?? document.documentElement;
    value.value = getComputedStyle(el).getPropertyValue(name).trim();
  };

  let observer: MutationObserver | null = null;

  onMounted(() => {
    update();

    const el = document.documentElement;

    observer = new MutationObserver((mutations) => {
      for (const m of mutations) {
        if (m.type === 'attributes' && m.attributeName === 'data-theme') {
          update();
        }
      }
    });

    observer.observe(el, {
      attributes: true,
      attributeFilter: ['data-theme'],
    });
  });

  onBeforeUnmount(() => {
    observer?.disconnect();
  });

  return { value, update };
}
