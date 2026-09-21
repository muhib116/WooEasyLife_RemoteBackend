import { router } from "@inertiajs/vue3";
import { onUnmounted, ref, type Ref } from "vue";

type VisitLike = {
    method?: string;
    preserveState?: boolean | string;
    only?: string[];
};

function visitFromStartArg(arg: unknown): VisitLike | undefined {
    if (!arg || typeof arg !== "object") {
        return undefined;
    }

    const withDetail = arg as { detail?: { visit?: VisitLike } };
    if (withDetail.detail?.visit) {
        return withDetail.detail.visit;
    }

    const maybeVisit = arg as VisitLike;
    if (maybeVisit.method || Array.isArray(maybeVisit.only)) {
        return maybeVisit;
    }

    return undefined;
}

function shouldUsePageSkeleton(visit: VisitLike | undefined): boolean {
    if (!visit) {
        return false;
    }

    const method = String(visit.method || "get").toLowerCase();
    if (method !== "get") {
        return false;
    }

    if (visit.preserveState && Array.isArray(visit.only) && visit.only.length > 0) {
        return false;
    }

    return true;
}

export function useInertiaPageLoading(): Ref<boolean> {
    const loading = ref(false);

    const offStart = router.on("start", (event) => {
        loading.value = shouldUsePageSkeleton(visitFromStartArg(event));
    });
    const offFinish = router.on("finish", () => {
        loading.value = false;
    });
    const offError = router.on("error", () => {
        loading.value = false;
    });

    onUnmounted(() => {
        offStart();
        offFinish();
        offError();
    });

    return loading;
}
