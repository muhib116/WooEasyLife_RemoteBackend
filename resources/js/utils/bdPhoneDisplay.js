export function formatBdPhoneDisplay(phone) {
    if (!phone) {
        return null;
    }

    const digits = String(phone).replace(/\D/g, '');

    if (digits.length === 13 && digits.startsWith('880')) {
        return `+880${digits.slice(3, 7)}-${digits.slice(7)}`;
    }

    if (digits.length === 11 && digits.startsWith('0')) {
        return `+880${digits.slice(1, 5)}-${digits.slice(5)}`;
    }

    return String(phone);
}
