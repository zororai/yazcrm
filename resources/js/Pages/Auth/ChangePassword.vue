<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ password: '', password_confirmation: '' });
const showPassword = ref(false);

function submit() {
    form.post('/change-password', { onFinish: () => form.reset() });
}
</script>

<template>
    <div class="cp-root">
        <div class="cp-card">
            <div class="cp-logo">
                <svg width="40" height="34" viewBox="0 0 130 108" xmlns="http://www.w3.org/2000/svg">
                    <rect x="12" y="0" width="28" height="82" rx="14" fill="#e8512a" transform="rotate(34 26 66)"/>
                    <rect x="90" y="0" width="28" height="82" rx="14" fill="#6835a2" transform="rotate(-34 104 66)"/>
                    <ellipse cx="65" cy="14" rx="11" ry="14" fill="#ffffff"/>
                </svg>
            </div>

            <h1 class="cp-title">Set a new password</h1>
            <p class="cp-sub">For security, you must change your default password before continuing.</p>

            <form @submit.prevent="submit" class="cp-form">
                <div class="cp-field">
                    <label class="cp-label">New Password</label>
                    <div class="cp-input-wrap" :class="{ 'cp-input-err': form.errors.password }">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            placeholder="At least 8 characters"
                            class="cp-input"
                            required
                        />
                        <button type="button" class="cp-eye-btn" @click="showPassword = !showPassword" tabindex="-1">
                            <svg v-if="!showPassword" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg v-else width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="cp-err-msg">{{ form.errors.password }}</p>
                </div>

                <div class="cp-field">
                    <label class="cp-label">Confirm Password</label>
                    <div class="cp-input-wrap">
                        <input
                            v-model="form.password_confirmation"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            placeholder="Re-enter your new password"
                            class="cp-input"
                            required
                        />
                    </div>
                </div>

                <button type="submit" class="cp-submit-btn" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save & Continue' }}
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.cp-root {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #080c1e;
    font-family: 'Inter', sans-serif;
    padding: 24px;
}
.cp-card {
    width: 100%;
    max-width: 420px;
    background: rgba(13,17,27,.95);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 24px;
    padding: 44px 36px;
}
.cp-logo { margin-bottom: 24px; }
.cp-title {
    color: #fff;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 6px;
    letter-spacing: -.5px;
}
.cp-sub {
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 28px;
}
.cp-form { display: flex; flex-direction: column; gap: 16px; }
.cp-field { display: flex; flex-direction: column; gap: 6px; }
.cp-label { color: #cbd5e1; font-size: 13px; font-weight: 500; }
.cp-input-wrap {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 10px;
    padding: 0 14px;
    gap: 10px;
    transition: border-color .2s;
}
.cp-input-wrap:focus-within { border-color: rgba(124,58,237,.6); background: rgba(124,58,237,.05); }
.cp-input-wrap.cp-input-err { border-color: rgba(239,68,68,.5); }
.cp-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: #e2e8f0;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    padding: 13px 0;
}
.cp-input::placeholder { color: #334155; }
.cp-eye-btn { background: none; border: none; color: #475569; cursor: pointer; display: flex; align-items: center; padding: 0; }
.cp-eye-btn:hover { color: #94a3b8; }
.cp-err-msg { color: #f87171; font-size: 12px; }
.cp-submit-btn {
    margin-top: 8px;
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(90deg, #7c3aed 0%, #f97316 100%);
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: opacity .2s;
}
.cp-submit-btn:hover { opacity: .92; }
.cp-submit-btn:disabled { opacity: .6; cursor: not-allowed; }
</style>
