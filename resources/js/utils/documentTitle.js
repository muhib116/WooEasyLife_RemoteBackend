export function withAppTitle(title, appName = 'WooEasyLife') {
    const raw = String(title || '').trim();
    const brand = appName || 'WooEasyLife';

    if (!raw) {
        return brand;
    }

    if (raw.includes('WooEasyLife') || raw.includes(brand)) {
        return raw;
    }

    return `${raw} - ${brand}`;
}
