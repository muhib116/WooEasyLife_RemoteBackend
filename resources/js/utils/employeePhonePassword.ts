export const normalizeEmployeePhone = (phone: string): string => {
    let normalized = phone.replace(/\D/g, '')

    if (normalized.startsWith('880')) {
        normalized = `0${normalized.slice(3)}`
    }

    if (/^1\d{9}$/.test(normalized)) {
        normalized = `0${normalized}`
    }

    return normalized
}

export const isValidEmployeePhonePassword = (phone: string): boolean => {
    const normalized = normalizeEmployeePhone(phone)

    return normalized.length >= 10 && normalized.length <= 15
}
