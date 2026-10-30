// Display helpers shared by the data collection pages.

// A field's answer as text: choice labels (or names), geopoints, repeats.
export function formatAnswer(field, value, useLabels = true) {
    if (value === null || value === undefined || value === '') return '';
    if (value === '•••') return value;
    if (!field) {
        if (Array.isArray(value)) return `${value.length} entr${value.length === 1 ? 'y' : 'ies'}`;
        return typeof value === 'object' ? JSON.stringify(value) : String(value);
    }

    if (field.type === 'select_one') {
        return useLabels ? (field.choices?.[value] ?? String(value)) : String(value);
    }
    if (field.type === 'select_multiple' && Array.isArray(value)) {
        return value.map(v => (useLabels ? (field.choices?.[v] ?? v) : v)).join(useLabels ? '; ' : ' ');
    }
    if (field.type === 'geopoint' && typeof value === 'object') {
        return `${value.lat}, ${value.lng}` + (value.accuracy != null ? ` (±${Math.round(value.accuracy)} m)` : '');
    }
    if (Array.isArray(value)) return `${value.length} entr${value.length === 1 ? 'y' : 'ies'}`;
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

export const STATUS_LABELS = {
    pending: 'Pending',
    approved: 'Approved',
    not_approved: 'Not approved',
    on_hold: 'On hold',
};

export const STATUS_COLORS = {
    pending: 'bg-gray-100 text-gray-700',
    approved: 'bg-green-100 text-green-800',
    not_approved: 'bg-red-100 text-red-800',
    on_hold: 'bg-amber-100 text-amber-800',
};

export function formatDateTime(value) {
    return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

export function formatDuration(seconds) {
    if (seconds == null) return '—';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m ? `${m} min ${s} s` : `${s} s`;
}

// The current page's query string as an object (for keeping filters
// across tabs and when changing one parameter).
export function currentQuery() {
    const params = new URLSearchParams(window.location.search);
    const out = {};
    for (const [key, value] of params.entries()) out[key] = value;
    return out;
}

// Filters only, as query params ({answers: {f: v}} → answers[f]=v).
export function filterQuery(filters) {
    const out = {};
    for (const [key, value] of Object.entries(filters ?? {})) {
        if (key === 'answers') {
            for (const [field, answer] of Object.entries(value ?? {})) {
                if (answer !== '' && answer != null) out[`answers[${field}]`] = answer;
            }
        } else if (value !== '' && value != null) {
            out[key] = value;
        }
    }
    return out;
}
