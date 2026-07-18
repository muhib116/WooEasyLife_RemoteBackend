import { usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { watch } from "vue";

export function useInertiaFlashToasts(group = "br") {
    const toast = useToast();
    const page = usePage();

    let timeout: ReturnType<typeof setTimeout>;
    let lastSignature = "";

    watch(
        () => ({
            success: (page.props.flash as { success?: unknown } | undefined)?.success ?? null,
            error: (page.props.flash as { error?: unknown } | undefined)?.error ?? null,
            warning: (page.props.flash as { warning?: unknown } | undefined)?.warning ?? null,
            errors: page.props.errors ?? {},
        }),
        (flash) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const signature = JSON.stringify(flash);
                if (signature === lastSignature) {
                    return;
                }
                lastSignature = signature;

                if (flash.success) {
                    const data: Record<string, unknown> = {
                        summary: "Success",
                        severity: "success",
                        life: 7000,
                        group,
                    };
                    if (typeof flash.success === "object" && flash.success !== null) {
                        data.detail = (flash.success as { detail?: string }).detail;
                    } else {
                        data.detail = flash.success;
                    }
                    toast.add(data as never);
                }

                if (flash.error) {
                    toast.add({
                        severity: "error",
                        summary: "Error",
                        detail: String(flash.error),
                        life: 9000,
                        group,
                    });
                }

                if (flash.warning) {
                    toast.add({
                        severity: "warn",
                        summary: "Warning",
                        detail: String(flash.warning),
                        life: 5000,
                        group,
                    });
                }

                const errorMessages = Object.values(flash.errors ?? {}).filter(
                    (message): message is string =>
                        typeof message === "string" && message.length > 0,
                );
                if (errorMessages.length > 0) {
                    toast.add({
                        severity: "error",
                        summary: "Error",
                        detail: errorMessages.join(" "),
                        life: 5000,
                        group,
                    });
                }
            }, 100);
        },
        { deep: true, immediate: true },
    );
}
