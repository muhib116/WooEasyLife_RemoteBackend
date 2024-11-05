import { computed } from 'vue';

export function useButton(variant) {
  const baseClasses = 'px-4 py-2 rounded border border-current hover:shadow';

  const variantClasses = computed(() => {
    switch (variant.value) {
      case 'primary':
        return `${baseClasses} bg-primary-500 text-white hover:bg-primary-600`;
      case 'secondary':
        return `${baseClasses} bg-gray-500 text-white hover:bg-gray-600`;
      case 'success':
        return `${baseClasses} bg-green-500 hover:bg-green-600`;
      case 'danger':
        return `${baseClasses} bg-red-500 hover:bg-red-600`;
      default:
        return `${baseClasses} bg-gray-500 hover:bg-gray-600`;
    }
  });

  return {
    variantClasses,
  };
}
