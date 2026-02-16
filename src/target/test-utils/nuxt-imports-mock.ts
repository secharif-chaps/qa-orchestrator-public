import { ref } from 'vue';

/**
 * Mock for Nuxt auto-imports (#imports)
 * Used in vitest tests to replace Nuxt's auto-imports
 */

export function useCookie<T = unknown>(_name: string, _options?: unknown) {
  return ref<T | null>(null);
}

export function useNuxtApp() {
  return {
    $pinia: {},
  };
}

export function useRoute() {
  return {
    path: '/',
    params: {},
    query: {},
  };
}

export function useRouter() {
  return {
    push: () => Promise.resolve(),
    replace: () => Promise.resolve(),
    back: () => {},
  };
}

export function navigateTo(_path: string) {
  return Promise.resolve();
}

// Mock for useAppFetch - returns a function with a raw method
const mockRawResponse = {
  _data: { id: '123', name: 'Test Resource' },
  status: 200,
  headers: new Headers(),
};

const mockFetch = async () => mockRawResponse;
mockFetch.raw = async <T>() =>
  mockRawResponse as { _data: T; status: number; headers: Headers };

export function useAppFetch() {
  return mockFetch;
}
