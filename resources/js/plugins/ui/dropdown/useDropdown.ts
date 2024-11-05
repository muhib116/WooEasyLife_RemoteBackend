import { onClickOutside } from "@vueuse/core"
import { ref } from "vue"
import { isBoolean } from "lodash";

export const useDropdown = () => {
    const isOpen = ref(false);
    const toggleButtonRef = ref(null)

    const toggle = (state?: any) => {
        if(isBoolean(state)) {
            isOpen.value = state
            return
        }
        isOpen.value = !isOpen.value
    }

    // onClickOutside(toggleButtonRef, () => {
    //     toggle(false)
    // })
    return {
        toggle,
        isOpen,
        toggleButtonRef
    }
}