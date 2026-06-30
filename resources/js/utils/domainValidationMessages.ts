export function extractDomainValidationError(error: unknown): string {
    const response = (error as { response?: { data?: Record<string, unknown>; status?: number } })
        ?.response;

    if (!response) {
        return "Could not reach the server. Check your connection and try again.";
    }

    const data = response.data ?? {};
    const message = data.message;

    if (typeof message === "string" && message.trim()) {
        return message;
    }

    const domainErrors = data.errors as Record<string, string[] | undefined> | undefined;
    const domainMessage = domainErrors?.domain?.[0];
    if (domainMessage) {
        return domainMessage;
    }

    if (response.status === 419) {
        return "Your session expired. Refresh the page and try again.";
    }

    if (response.status === 403) {
        return "You do not have permission to validate domains for this merchant.";
    }

    return "Could not validate this domain. Check the hostname and try again.";
}

export function domainValidationErrorTitle(message: string): string {
    const lower = message.toLowerCase();

    if (
        lower.includes("already registered")
        || lower.includes("already used")
        || lower.includes("already has a website")
        || lower.includes("not available")
    ) {
        return "Domain already in use";
    }

    if (lower.includes("dns")) {
        return "DNS check failed";
    }

    if (
        lower.includes("invalid domain")
        || lower.includes("valid website domain")
        || lower.includes("enter a valid")
    ) {
        return "Invalid domain format";
    }

    if (lower.includes("enter a store domain")) {
        return "Domain required";
    }

    if (lower.includes("session expired")) {
        return "Session expired";
    }

    if (lower.includes("could not reach the server")) {
        return "Connection problem";
    }

    return "Domain cannot be used";
}
