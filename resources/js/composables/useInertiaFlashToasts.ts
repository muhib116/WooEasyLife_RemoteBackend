import { usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { watch } from "vue";

export function useInertiaFlashToasts(group = "br") {
    const toast = useToast();
    const page = usePage();

    let timeout: ReturnType<typeof setTimeout>;

    watch(
        page,
        () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                // @ts-ignore
                if (page.props.flash?.success) {
                    const data: Record<string, unknown> = {
                        summary: "Success",
                        severity: "success",
                        life: 3000,
                        group,
                    };
                    // @ts-ignore
                    if (typeof page.props.flash?.success == "object") {
                        // @ts-ignore
                        data.detail = page.props.flash?.success?.detail;
                    } else {
                        // @ts-ignore
                        data.detail = page.props.flash?.success;
                    }
                    toast.add(data as never);
                }
                // @ts-ignore
                if (page.props.flash?.error) {
                    toast.add({
                        severity: "error",
                        summary: "Error",
                        // @ts-ignore
                        detail: page.props.flash?.error,
                        life: 3000,
                        group,
                    });
                }
                // @ts-ignore
                if (page.props.flash?.warning) {
                    toast.add({
                        severity: "warn",
                        summary: "Warning",
                        // @ts-ignore
                        detail: page.props.flash?.warning,
                        life: 5000,
                        group,
                    });
                }
                // @ts-ignore
                const validationErrors = page.props.errors ?? {};
                const errorMessages = Object.values(validationErrors).filter(
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
        { deep: true },
    );
}
