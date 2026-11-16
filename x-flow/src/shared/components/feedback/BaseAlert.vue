<script setup>
const props = defineProps({
  type: {
    type: String,
    default: 'info',
  },
})

const classes = {
  info: 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300',
  success: 'border-app-success/20 bg-app-success/10 text-app-success',
  warning: 'border-app-warning/20 bg-app-warning/10 text-app-warning',
  danger: 'border-app-danger/20 bg-app-danger/10 text-app-danger',
}
</script>

<template>
  <div
    class="rounded-xl border px-4 py-3 text-sm"
    :class="classes[props.type] || classes.info"
  >
    <slot />
  </div>
</template>