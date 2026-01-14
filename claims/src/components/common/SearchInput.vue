<template>
    <div class="search-input-container">
      <input 
        type="text" 
        class="search-input"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        placeholder="Search..."
      />
      <span v-if="modelValue" class="clear-search" @click="$emit('update:modelValue', '')">
        ✕
      </span>
      <span v-else class="search-icon">
        🔍
      </span>
    </div>
  </template>
  
  <script setup>
  defineProps({
    modelValue: {
      type: String,
      default: ''
    }
  })
  
  defineEmits(['update:modelValue'])
  </script>
  
  <style scoped>
  .search-input-container {
    position: relative;
    width: 100%;
  }
  
  .search-input {
    width: 100%;
    padding: 8px 16px 8px 36px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    line-height: 1.5;
    transition: border-color 0.3s;
  }
  
  .search-input:focus {
    outline: none;
    border-color: #3949ab;
    box-shadow: 0 0 0 2px rgba(57, 73, 171, 0.1);
  }
  
  .search-icon,
  .clear-search {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    color: #777;
    cursor: pointer;
  }
  
  .search-icon {
    left: 10px;
    font-size: 14px;
  }
  
  .clear-search {
    right: 10px;
    font-size: 12px;
  }
  
  .clear-search:hover {
    color: #333;
  }
  </style>