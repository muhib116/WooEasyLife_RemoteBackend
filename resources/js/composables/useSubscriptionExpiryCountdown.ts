import {
    DEFAULT_EXPIRY_WARN_DAYS,
    formatExpiryCountdown,
    getSubscriptionExpiryStatus,
    parseSubscriptionExpiry,
    subscriptionExpiryTitle,
    type SubscriptionExpiryStatus,
} from "@/utils/subscriptionExpiry";
import { computed, onMounted, onUnmounted, ref, type Ref } from "vue";

export function useSubscriptionExpiryCountdown(
    expiresAt: Ref<string | null | undefined>,
    warnWithinDays = DEFAULT_EXPIRY_WARN_DAYS,
) {
    const tick = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        timer = setInterval(() => {
            tick.value = Date.now();
        }, 1000);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
        }
    });

    const parsed = computed(() => parseSubscriptionExpiry(expiresAt.value));

    const status = computed<SubscriptionExpiryStatus>(() => {
        if (!parsed.value) {
            return "none";
        }

        return getSubscriptionExpiryStatus(parsed.value, warnWithinDays);
    });

    const countdown = computed(() => {
        if (!parsed.value || status.value === "none") {
            return null;
        }

        return formatExpiryCountdown(parsed.value, new Date(tick.value));
    });

    const title = computed(() => subscriptionExpiryTitle(status.value));

    const showWarning = computed(() => status.value !== "none");

    return {
        status,
        countdown,
        title,
        showWarning,
    };
}
