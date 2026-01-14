// import { ref } from 'vue'

// export function useFullscreen() {
//   const elementRef = ref(null)

//   const toggleFullscreen = () => {
//     const el = elementRef.value?.$el || elementRef.value
//     if (!el) return

//     if (document.fullscreenElement) {
//       document.exitFullscreen()
//     } else {
//       el.requestFullscreen().catch((err) => {
//         console.error('Fullscreen error:', err)
//       })
//     }
//   }

//   const isFullscreen = () => document.fullscreenElement !== null

//   return {
//     elementRef,
//     toggleFullscreen,
//     isFullscreen
//   }
// }
import { ref, onMounted, onUnmounted } from 'vue'

export function useFullscreen() {
  const elementRef = ref(null)
  const isFullscreen = ref(false)

  const toggleFullscreen = () => {
    const el = elementRef.value?.$el || elementRef.value
    if (!el) return

    if (document.fullscreenElement) {
      document.exitFullscreen()
    } else {
      el.requestFullscreen().catch((err) => {
        console.error('Fullscreen error:', err)
      })
    }
  }

  const handleFullscreenChange = () => {
    isFullscreen.value = document.fullscreenElement !== null
  }

  onMounted(() => {
    document.addEventListener('fullscreenchange', handleFullscreenChange)
  })

  onUnmounted(() => {
    document.removeEventListener('fullscreenchange', handleFullscreenChange)
  })

  return {
    elementRef,
    toggleFullscreen,
    isFullscreen,
  }
}
