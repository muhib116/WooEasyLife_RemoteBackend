import { ref, watch } from "vue"

export const useTheme = () => {
    const isDarkMode = ref(false)


    watch(() => [isDarkMode], () => {
        let _html = document.querySelector('html')
        if(_html && isDarkMode.value) {
            _html.classList.add('dark')
        } else {
            _html.classList.remove('dark')
        }
    }, {
        immediate: true
    })
    
    return {
        isDarkMode
    }
}