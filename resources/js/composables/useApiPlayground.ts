import { onMounted, ref, watch } from "vue";

const STORAGE_KEY = "developer_api_playground";

export function useApiPlayground() {
    const token = ref("");
    const origin = ref("");

    const persist = () => {
        sessionStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                token: token.value,
                origin: origin.value,
            }),
        );
    };

    onMounted(() => {
        try {
            const saved = sessionStorage.getItem(STORAGE_KEY);
            if (saved) {
                const parsed = JSON.parse(saved);
                token.value = parsed.token || "";
                origin.value = parsed.origin || "";
            }
        } catch {
            // ignore corrupt storage
        }
    });

    watch([token, origin], persist);

    return { token, origin, persist };
}
