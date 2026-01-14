/* ResetButton.vue */
<template>
  <button 
    type="button"
    class="reset-button"
    @click="$emit('click')"
    :disabled="disabled"
  >
    <span class="reset-icon">↺</span>
    <span class="button-text">Reset</span>
  </button>
</template>

<script setup>
defineProps({
  disabled: {
    type: Boolean,
    default: false
  }
})

defineEmits(['click'])
</script>

<style scoped>
.reset-button {
  display: flex;
  align-items: center;
  background-color: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 6px 12px;
  font-size: 0.9rem;
  cursor: pointer;
  color: #555;
  transition: background-color 0.2s;
}

.reset-button:hover:not(:disabled) {
  background-color: #e5e5e5;
}

.reset-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.reset-icon {
  margin-right: 6px;
  font-size: 0.9rem;
}
</style>