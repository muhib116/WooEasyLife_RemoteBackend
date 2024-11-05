import { ref } from "vue";
import { useResizeObserver, ResizeObserverCallback } from "@vueuse/core";

export const useLayout = () => {
    const showLeftSidebar = ref(true)
    const target = ref(document.body)
    const windowWidth = ref(0)
    
    useResizeObserver(target, (entries) => {
        windowWidth.value = window.innerWidth
        if(windowWidth.value <= 1024) {
            showLeftSidebar.value = false
        } else {
            showLeftSidebar.value = true
        }
    })


    return {
        showLeftSidebar,
    }
}