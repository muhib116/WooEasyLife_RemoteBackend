import axios from "axios";
import { extractDomainValidationError } from "@/utils/domainValidationMessages";
import { computed, ref, watch, type Ref } from "vue";

type UseDomainValidationOptions = {
    userId: Ref<number | null | undefined>;
    domain: Ref<string | null | undefined>;
    enabled: Ref<boolean>;
    requireNewWebsite?: Ref<boolean>;
    debounceMs?: number;
};

export function useDomainValidation({
    userId,
    domain,
    enabled,
    requireNewWebsite,
    debounceMs = 500,
}: UseDomainValidationOptions) {
    const validating = ref(false);
    const domainError = ref<string | null>(null);
    const validatedDomain = ref<string | null>(null);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let requestSerial = 0;

    const isValid = computed(
        () =>
            Boolean(validatedDomain.value) &&
            !domainError.value &&
            !validating.value,
    );

    const reset = () => {
        domainError.value = null;
        validatedDomain.value = null;
    };

    const invalidate = () => {
        validatedDomain.value = null;
        domainError.value = null;
    };

    const validateNow = async (): Promise<boolean> => {
        if (!enabled.value || !userId.value) {
            return true;
        }

        const raw = String(domain.value ?? "").trim();
        if (!raw) {
            reset();
            domainError.value = "Enter a store domain to continue.";
            return false;
        }

        const serial = ++requestSerial;
        validating.value = true;
        domainError.value = null;
        validatedDomain.value = null;

        try {
            const { data } = await axios.post(
                route("users.setup.validateDomain", userId.value),
                {
                    domain: raw,
                    require_new_website: requireNewWebsite?.value ?? false,
                },
            );

            if (serial !== requestSerial) {
                return false;
            }

            validatedDomain.value = data.domain;
            domain.value = data.domain;

            return true;
        } catch (error: any) {
            if (serial !== requestSerial) {
                return false;
            }

            domainError.value = extractDomainValidationError(error);

            return false;
        } finally {
            if (serial === requestSerial) {
                validating.value = false;
            }
        }
    };

    watch(
        [domain, enabled],
        () => {
            invalidate();

            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            if (!enabled.value) {
                return;
            }

            const raw = String(domain.value ?? "").trim();
            if (!raw) {
                domainError.value = null;
                return;
            }

            debounceTimer = setTimeout(() => {
                void validateNow();
            }, debounceMs);
        },
        { immediate: true },
    );

    return {
        validating,
        domainError,
        validatedDomain,
        isValid,
        validateNow,
        reset,
        invalidate,
    };
}
