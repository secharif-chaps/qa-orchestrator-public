import { ref } from 'vue';

interface Toast {
  id: number;
  title: string;
  description: string | null;
  icon:
    | 'fa-check-circle'
    | 'fa-xmark'
    | 'fa-circle-info'
    | 'fa-circle-exclamation';
  canExit: boolean;
  color: 'green' | 'red' | 'blue' | 'yellow';
}

const toasts = ref<Toast[]>([]);

const addToast = (
  title: string,
  description: string | null = null,
  icon: Toast['icon'] = 'fa-check-circle',
  color: Toast['color'] = 'green',
  canExit: boolean = false,
) => {
  const id = Date.now();
  toasts.value.push({
    id,
    title,
    description,
    icon,
    color,
    canExit,
  });
  setTimeout(() => {
    removeToast(id);
  }, 3000);
};

const removeToast = (id: number) => {
  const index = toasts.value.findIndex((toast) => toast.id === id);
  if (index !== -1) {
    toasts.value.splice(index, 1);
  }
};

export const useToast = () => {
  return {
    toasts,
    success: (title: string, description: string | null = null) =>
      addToast(title, description, 'fa-check-circle', 'green'),
    error: (title: string, description: string | null = null) =>
      addToast(title, description, 'fa-xmark', 'red'),
    info: (title: string, description: string | null = null) =>
      addToast(title, description, 'fa-circle-info', 'blue'),
    warning: (title: string, description: string | null = null) =>
      addToast(title, description, 'fa-circle-exclamation', 'yellow'),
  };
};
