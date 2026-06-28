import axios from "axios";
import { ref } from "vue";
import { useClipboard } from "@vueuse/core";
import { useToast } from "primevue/usetoast";

export function useLicenseTokenReveal() {
    const revealingId = ref<number | null>(null);
    const { copy } = useClipboard();
    const toast = useToast();

    const revealAndCopy = async (tokenId: number) => {
        revealingId.value = tokenId;

        try {
            const { data } = await axios.post(route("apiKeys.reveal", tokenId));

            if (!data?.token) {
                throw new Error("No license key returned.");
            }

            await copy(data.token);
            toast.add({
                severity: "success",
                summary: "Copied",
                detail: "License key copied to clipboard",
                life: 3000,
            });
        } catch (error: any) {
            toast.add({
                severity: "error",
                summary: "Could not reveal key",
                detail:
                    error?.response?.data?.message ||
                    error?.message ||
                    "Unable to fetch the license key.",
                life: 4000,
            });
        } finally {
            revealingId.value = null;
        }
    };

    const isRevealing = (tokenId: number) => revealingId.value === tokenId;

    return {
        revealingId,
        revealAndCopy,
        isRevealing,
    };
}
