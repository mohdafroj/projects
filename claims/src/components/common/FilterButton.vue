/* FilterButton.vue */
<template>
  <button 
    type="button"
    class="filter-button"
    @click="$emit('click')"
  >
    <span class="filter-icon">🔍</span>
    <span class="button-text">Filter</span>
  </button>
</template>

<script setup>
defineEmits(['click'])
</script>

<style scoped>
.filter-button {
  display: flex;
  align-items: center;
  background-color: #f0f4ff;
  border: 1px solid #d0d7f7;
  border-radius: 4px;
  padding: 6px 12px;
  font-size: 0.9rem;
  cursor: pointer;
  color: #3949ab;
  transition: background-color 0.2s;
}

.filter-button:hover {
  background-color: #e0e7ff;
}

.filter-icon {
  margin-right: 6px;
  font-size: 0.9rem;
}
</style>

