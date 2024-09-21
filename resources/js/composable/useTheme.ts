import { useDark } from "@vueuse/core"

export const useTheme = () => {
    const isDarkMode = useDark()

    return {
        isDarkMode
    }
}