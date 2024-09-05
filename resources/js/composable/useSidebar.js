import { ref } from "vue"

export const useSidebar = () => {
    const isSidebarOpen = ref(true)
    const toggleSidebar = () => {
        isSidebarOpen.value = !isSidebarOpen.value
    }
    
    return {
        isSidebarOpen,
        toggleSidebar
    }
    
}