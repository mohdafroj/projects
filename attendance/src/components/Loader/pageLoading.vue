<template>
    <div class="overlay-backdrop w-screen h-screen"
    :class="customClasses"
    >
      <div class="loader-wrapper">
        <!-- Spinning border -->
        <div class="spinner-border"></div>
        
        <!-- Image container -->
        <div class="image-container">
          <!-- Hardcoded image -->
          <img 
            src="./rss-building.png" 
            alt="Loading"
            class="loader-image"
          />
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  const props = defineProps({
    customClasses: {
      type: String,
      default: 'fixed top-0 left-0'
    }
  });
  </script>
  
  <style scoped>
  .overlay-backdrop {
    background-color: rgba(255, 255, 255, .5); /* 50% transparent black backdrop 30, 102, 245 */
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999; /* High z-index to appear above other content */
    /* backdrop-filter: blur(2px);  */
  }
  
  .loader-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
    user-select: none;
  }
  
  .spinner-border {
    width: 100%;
    height: 100%;
    border: 6px solid rgba(255, 255, 255, 0.3); /* Semi-transparent white border */
    border-top: 6px solid #0574cb;
    /* border-top: 6px solid #ffffff;  */
    border-radius: 50%;
    animation: spin 1s linear infinite;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); /* Subtle shadow for depth */
  }
  
  .image-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
  }
  
  .loader-image {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.2)); /* Subtle shadow for the image */
  }
  
  @keyframes spin {
    from {
      transform: rotate(0deg);
    }
    to {
      transform: rotate(360deg);
    }
  }
  
  /* Responsive sizing */
  @media (max-width: 768px) {
    .loader-wrapper {
      width: 100px;
      height: 100px;
    }
    
    .spinner-border {
      border-width: 5px;
    }
    
    .image-container {
      padding: 15px;
    }
  }
  
  @media (max-width: 480px) {
    .loader-wrapper {
      width: 80px;
      height: 80px;
    }
    
    .spinner-border {
      border-width: 4px;
    }
    
    .image-container {
      padding: 12px;
    }
  }
  
  /* Animation for smooth appearance */
  .overlay-backdrop {
    animation: fadeIn 0.2s ease-out;
  }
  
  .loader-wrapper {
    animation: scaleIn 0.3s ease-out;
  }
  
  @keyframes fadeIn {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }
  
  @keyframes scaleIn {
    from {
      opacity: 0;
      transform: scale(0.8);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }
  </style>