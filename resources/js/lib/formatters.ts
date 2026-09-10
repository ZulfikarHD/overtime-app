/**
 * Formats a numeric value into Indonesian Rupiah (IDR) currency format.
 * Example: 25000 -> "Rp 25.000,00"
 */
export function formatRupiah(
    amount: number | string | null | undefined,
    options: {
        minimumFractionDigits?: number;
        maximumFractionDigits?: number;
    } = {},
): string {
    if (amount === null || amount === undefined || amount === '') {
        return 'Rp 0,00';
    }

    const numericValue =
        typeof amount === 'string' ? parseFloat(amount) : amount;

    if (isNaN(numericValue)) {
        return 'Rp 0,00';
    }

    const { minimumFractionDigits = 2, maximumFractionDigits = 2 } = options;

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits,
        maximumFractionDigits,
    })
        .format(numericValue)
        .replace(/\u00A0/g, ' '); // Standardize non-breaking space to regular space
}

/**
 * Formats a date into Indonesian standard DD/MM/YYYY.
 */
export function formatDateIndo(
    dateStr: string | Date | null | undefined,
): string {
    if (!dateStr) {
        return '-';
    }

    const date = typeof dateStr === 'string' ? new Date(dateStr) : dateStr;
    if (isNaN(date.getTime())) {
        return '-';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
}

/**
 * Formats a numeric value into compact Indonesian Rupiah (IDR) format.
 * Example: 125500000 -> "Rp 125,5 jt"
 */
export function formatCompactRupiah(
    amount: number | string | null | undefined,
): string {
    if (amount === null || amount === undefined || amount === '') {
        return 'Rp 0';
    }

    const numericValue =
        typeof amount === 'string' ? parseFloat(amount) : amount;

    if (isNaN(numericValue) || numericValue === 0) {
        return 'Rp 0';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        notation: 'compact',
        maximumFractionDigits: 1,
    })
        .format(numericValue)
        .replace(/\u00A0/g, ' ');
}
