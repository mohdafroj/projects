import { ref, onMounted, onUnmounted } from 'vue';

export function useWindow() {
  // Initialize windowWidth as a reactive reference
  const windowWidth = ref(window.innerWidth);

  // Function to handle resize events
  const handleResize = () => {
    windowWidth.value = window.innerWidth; 
  };

  // Add event listener when component is mounted
  onMounted(() => {
    window.addEventListener('resize', handleResize);
    handleResize(); // Set initial value on mount
  });

  // Clean up event listener on component unmount
  onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
  });

  return windowWidth; 
}
