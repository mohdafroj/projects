import { useApiStore } from "@/store/apiData";
import { watch } from 'vue';

let isInitialized = false;

export function usePersistedStore() {
    const apiStore = useApiStore();
    
    // ✅ Load from localStorage on first use (only once)
    if (!isInitialized && typeof window !== 'undefined') {
        try {
            const stored = localStorage.getItem('it_equipment');
            if (stored) {
                const parsed = JSON.parse(stored);
                apiStore.it_equipment = parsed;
            }
        } catch (error) {
            console.error('Failed to load from localStorage:', error);
            localStorage.removeItem('it_equipment');
        }
        
        // ✅ Watch for changes and auto-save to localStorage
        watch(
            () => apiStore.it_equipment,
            (newValue) => {
                try {
                    localStorage.setItem('it_equipment', JSON.stringify(newValue));
                } catch (error) {
                    console.error('Failed to save to localStorage:', error);
                }
            },
            { deep: true }
        );
        
        isInitialized = true;
    }
    
    return apiStore;
}