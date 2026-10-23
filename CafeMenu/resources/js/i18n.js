export function trans(key, replacements = {}) {
    const value = key.split('.').reduce((acc, part) => (acc && typeof acc === 'object' ? acc[part] : undefined), window.__translations);

    if (typeof value !== 'string') {
        return key;
    }

    return Object.entries(replacements).reduce(
        (text, [search, replace]) => text.replaceAll(`:${search}`, replace),
        value,
    );
}

window.trans = trans;
