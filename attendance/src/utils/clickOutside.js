export default {
    beforeMount(el, binding) {
      // Delay the listener to avoid triggering on the same click that opens the dropdown
      setTimeout(() => {
        el.__clickOutsideHandler__ = (event) => {
          // If the click is outside the element
          if (!(el === event.target || el.contains(event.target))) {
            console.log('✅ Clicked outside')
            binding.value(event)
          }
        }
  
        document.addEventListener('click', el.__clickOutsideHandler__)
      }, 0)
    },
    unmounted(el) {
      document.removeEventListener('click', el.__clickOutsideHandler__)
    }
  }
  