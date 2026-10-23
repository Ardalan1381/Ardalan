export function formatMoney(minorUnits) {
    const decimals = window.CAFE?.currencyMinorUnits ?? 2;
    const currency = window.CAFE?.currency ?? '';
    const amount = minorUnits / Math.pow(10, decimals);

    // Matches app/Support/Money.php's number_format() — Western-Arabic
    // digits with thousands separators, regardless of the active locale.
    const formatted = amount.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });

    return `${formatted} ${currency}`.trim();
}
