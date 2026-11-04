import { watch } from 'vue';

// Follow-up plan by priority, in days from today. Mirrors Ticket::FOLLOW_UP_DAYS
// and Ticket::UPTAKE_DAYS on the server: High / Urgent are followed up within
// 48 hours and the referral taken up within 96; Low is followed up in 7 days.
export const FOLLOW_UP_DAYS = { high: 2, urgent: 2, low: 7 };
export const UPTAKE_DAYS    = { high: 4, urgent: 4 };

export const FOLLOW_UP_HINT = 'Set automatically: 48 hours for High / Urgent, 7 days for Low.';
export const UPTAKE_HINT    = 'Set automatically to 96 hours for High / Urgent.';

function inDays(days) {
    if (days == null) return '';
    const d = new Date();
    d.setDate(d.getDate() + days);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function followUpPlan(priority) {
    return {
        follow_up_date:       inDays(FOLLOW_UP_DAYS[priority]),
        referral_uptake_date: inDays(UPTAKE_DAYS[priority]),
    };
}

// Keeps a ticket form's follow-up and uptake dates in step with its priority.
// A date that is empty or still holds the previous automatic value follows the
// new priority; one the agent typed in is left alone. Returns refresh(), to
// call after form.reset().
export function watchFollowUpPlan(form) {
    let auto = { follow_up_date: '', referral_uptake_date: '' };

    const apply = (priority) => {
        const next = followUpPlan(priority);
        for (const field of Object.keys(next)) {
            if (!form[field] || form[field] === auto[field]) form[field] = next[field];
        }
        auto = next;
    };

    watch(() => form.priority, apply);
    apply(form.priority);

    return () => apply(form.priority);
}
