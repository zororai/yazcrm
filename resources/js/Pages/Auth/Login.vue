<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <div class="lp-root">
        <!-- Ambient glow blobs -->
        <div class="lp-glow lp-glow-tl"></div>
        <div class="lp-glow lp-glow-br"></div>
        <div class="lp-glow lp-glow-mid"></div>

        <!-- ── LEFT PANEL ── -->
        <div class="lp-left">

            <!-- Logo -->
            <div class="lp-logo">
                <div class="lp-logo-icon">
                    <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
                        <path d="M13 2C13 2 5 8 5 14C5 18.4 8.6 22 13 22C17.4 22 21 18.4 21 14C21 8 13 2 13 2Z" fill="url(#lg1)" opacity=".9"/>
                        <path d="M9 14L13 18L21 8" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <defs>
                            <linearGradient id="lg1" x1="5" y1="2" x2="21" y2="22" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#f97316"/>
                                <stop offset="1" stop-color="#7c3aed"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <div>
                    <div class="lp-logo-name">youth</div>
                    <div class="lp-logo-sub">advocates</div>
                </div>
            </div>

            <!-- Headline -->
            <div class="lp-hero">
                <h1 class="lp-headline">
                    Smarter Calls.<br>
                    Stronger Connections.<br>
                    <span class="lp-gradient-text">Better Business.</span>
                </h1>
                <p class="lp-sub">
                    Youth Advocates CRM PBX helps teams manage calls, customers, and conversations in one powerful platform.
                </p>

                <!-- Dashboard mockup -->
                <div class="lp-mockup">
                    <!-- Sidebar strip -->
                    <div class="lp-mock-sidebar">
                        <div class="lp-mock-sb-logo">
                            <svg width="16" height="16" viewBox="0 0 26 26" fill="none">
                                <path d="M13 2C13 2 5 8 5 14C5 18.4 8.6 22 13 22C17.4 22 21 18.4 21 14C21 8 13 2 13 2Z" fill="url(#lg2)" opacity=".9"/>
                                <defs><linearGradient id="lg2" x1="5" y1="2" x2="21" y2="22" gradientUnits="userSpaceOnUse"><stop stop-color="#f97316"/><stop offset="1" stop-color="#7c3aed"/></linearGradient></defs>
                            </svg>
                        </div>
                        <div class="lp-mock-sb-btn lp-mock-sb-active">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        </div>
                        <div class="lp-mock-sb-btn">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        </div>
                        <div class="lp-mock-sb-btn">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72 12.8 12.8 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.91A16 16 0 0 0 15.1 16.1l1-.88a2 2 0 0 1 2.11-.45 12.8 12.8 0 0 0 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>
                        </div>
                        <div class="lp-mock-sb-btn">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3h18v18H3z"/><path d="M9 9h6M9 13h6"/></svg>
                        </div>
                        <div class="lp-mock-sb-btn">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 0-14.14 0M4.93 19.07a10 10 0 0 0 14.14 0"/></svg>
                        </div>
                    </div>

                    <!-- Main content -->
                    <div class="lp-mock-main">
                        <div class="lp-mock-title">Dashboard</div>

                        <!-- Stat cards -->
                        <div class="lp-mock-stats">
                            <div class="lp-mock-stat">
                                <div class="lp-mock-stat-lbl">Live Calls <span class="lp-live-dot"></span></div>
                                <div class="lp-mock-stat-val">24</div>
                                <div class="lp-mock-stat-sub">Active now</div>
                                <div class="lp-mock-bars">
                                    <span style="height:8px;background:#f97316;opacity:.9"></span>
                                    <span style="height:13px;background:#f97316;opacity:.7"></span>
                                    <span style="height:18px;background:#f97316"></span>
                                    <span style="height:10px;background:#f97316;opacity:.8"></span>
                                    <span style="height:15px;background:#f97316;opacity:.6"></span>
                                    <span style="height:20px;background:#f97316"></span>
                                    <span style="height:12px;background:#f97316;opacity:.7"></span>
                                    <span style="height:17px;background:#f97316;opacity:.9"></span>
                                    <span style="height:9px;background:#f97316;opacity:.5"></span>
                                    <span style="height:14px;background:#f97316;opacity:.8"></span>
                                </div>
                            </div>
                            <div class="lp-mock-stat">
                                <div class="lp-mock-stat-lbl">Total Calls</div>
                                <div class="lp-mock-stat-val">1,842</div>
                                <div class="lp-mock-stat-sub">This month</div>
                                <svg class="lp-sparkline" viewBox="0 0 80 24" fill="none">
                                    <polyline points="0,18 13,14 26,16 39,8 52,11 65,5 80,9" stroke="#7c3aed" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                            <div class="lp-mock-stat">
                                <div class="lp-mock-stat-lbl">Avg. Call Duration</div>
                                <div class="lp-mock-stat-val">03:42</div>
                                <div class="lp-mock-stat-sub">mins</div>
                                <svg class="lp-sparkline" viewBox="0 0 80 24" fill="none">
                                    <polyline points="0,12 13,8 26,15 39,10 52,18 65,7 80,13" stroke="#f97316" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Recent calls -->
                        <div class="lp-mock-recent-title">Recent Calls</div>
                        <div class="lp-mock-calls">
                            <div class="lp-mock-call">
                                <div class="lp-mock-avatar" style="background:linear-gradient(135deg,#f97316,#fb923c)">E</div>
                                <div class="lp-mock-caller">
                                    <div class="lp-mock-caller-name">Esther Howard</div>
                                    <div class="lp-mock-caller-num">+1 (555) 123-4567</div>
                                </div>
                                <span class="lp-mock-badge lp-badge-in">● Inbound</span>
                                <span class="lp-mock-time">02:36 PM</span>
                                <span class="lp-mock-dur">04:21</span>
                            </div>
                            <div class="lp-mock-call">
                                <div class="lp-mock-avatar" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)">A</div>
                                <div class="lp-mock-caller">
                                    <div class="lp-mock-caller-name">Albert Flores</div>
                                    <div class="lp-mock-caller-num">+1 (555) 987-6543</div>
                                </div>
                                <span class="lp-mock-badge lp-badge-out">● Outbound</span>
                                <span class="lp-mock-time">01:15 PM</span>
                                <span class="lp-mock-dur">03:11</span>
                            </div>
                            <div class="lp-mock-call">
                                <div class="lp-mock-avatar" style="background:linear-gradient(135deg,#0d9488,#34d399)">J</div>
                                <div class="lp-mock-caller">
                                    <div class="lp-mock-caller-name">Jenny Wilson</div>
                                    <div class="lp-mock-caller-num">+1 (555) 456-7890</div>
                                </div>
                                <span class="lp-mock-badge lp-badge-in">● Inbound</span>
                                <span class="lp-mock-time">12:42 PM</span>
                                <span class="lp-mock-dur">02:01</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Uptime badge -->
                <div class="lp-uptime">
                    <div class="lp-uptime-shield">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <div class="lp-uptime-pct">99.99% Uptime</div>
                        <div class="lp-uptime-sub">Enterprise Grade Reliability</div>
                    </div>
                    <div class="lp-uptime-dot"></div>
                </div>
            </div>
        </div>

        <!-- ── RIGHT PANEL ── -->
        <div class="lp-right">
            <div class="lp-card">

                <h1 class="lp-card-title">Welcome back</h1>
                <p class="lp-card-sub">Sign in to continue to your account</p>

                <form @submit.prevent="submit" class="lp-form">

                    <!-- Email -->
                    <div class="lp-field">
                        <label class="lp-label">Email address</label>
                        <div class="lp-input-wrap" :class="{ 'lp-input-err': form.errors.email }">
                            <svg class="lp-input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                placeholder="you@example.com"
                                class="lp-input"
                                required
                            />
                        </div>
                        <p v-if="form.errors.email" class="lp-err-msg">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div class="lp-field">
                        <label class="lp-label">Password</label>
                        <div class="lp-input-wrap" :class="{ 'lp-input-err': form.errors.password }">
                            <svg class="lp-input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                class="lp-input"
                                required
                            />
                            <button type="button" class="lp-eye-btn" @click="showPassword = !showPassword" tabindex="-1">
                                <svg v-if="!showPassword" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg v-else width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="lp-err-msg">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember + Forgot -->
                    <div class="lp-remember-row">
                        <label class="lp-check-label">
                            <input v-model="form.remember" type="checkbox" class="lp-checkbox" />
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="lp-forgot">Forgot password?</a>
                    </div>

                    <!-- Sign in button -->
                    <button type="submit" class="lp-submit-btn" :disabled="form.processing">
                        <span>{{ form.processing ? 'Signing in…' : 'Sign in' }}</span>
                        <svg v-if="!form.processing" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12,5 19,12 12,19"/></svg>
                    </button>

                </form>

            </div>
        </div>

    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

.lp-root {
    min-height: 100vh;
    display: flex;
    background: #080c1e;
    font-family: 'Inter', sans-serif;
    position: relative;
    overflow: hidden;
}

/* ── Glows ── */
.lp-glow {
    position: fixed;
    border-radius: 50%;
    pointer-events: none;
    z-index: 0;
}
.lp-glow-tl {
    top: -180px; left: -120px;
    width: 560px; height: 560px;
    background: radial-gradient(circle, rgba(124,58,237,.22) 0%, transparent 70%);
}
.lp-glow-br {
    bottom: -200px; right: -100px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(249,115,22,.14) 0%, transparent 70%);
}
.lp-glow-mid {
    top: 30%; left: 40%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(124,58,237,.08) 0%, transparent 70%);
}

/* ── Left panel ── */
.lp-left {
    flex: 0 0 58%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 44px 52px;
    position: relative;
    z-index: 1;
}

/* Logo */
.lp-logo {
    display: flex;
    align-items: center;
    gap: 12px;
}
.lp-logo-icon {
    width: 44px; height: 44px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
}
.lp-logo-name {
    color: #fff;
    font-weight: 700;
    font-size: 17px;
    line-height: 1;
}
.lp-logo-sub {
    color: #64748b;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 2px;
}

/* Hero */
.lp-hero { flex: 1; display: flex; flex-direction: column; justify-content: center; }
.lp-headline {
    font-size: 48px;
    font-weight: 800;
    color: #fff;
    line-height: 1.12;
    margin-bottom: 20px;
    letter-spacing: -1px;
}
.lp-gradient-text {
    background: linear-gradient(90deg, #f97316 0%, #7c3aed 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.lp-sub {
    color: #64748b;
    font-size: 15px;
    line-height: 1.65;
    max-width: 400px;
    margin-bottom: 32px;
}

/* Dashboard mockup */
.lp-mockup {
    display: flex;
    background: rgba(13,17,27,.9);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 24px;
    backdrop-filter: blur(10px);
}
.lp-mock-sidebar {
    width: 44px;
    background: rgba(255,255,255,.03);
    border-right: 1px solid rgba(255,255,255,.06);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 0;
    gap: 6px;
}
.lp-mock-sb-logo {
    margin-bottom: 8px;
    opacity: .9;
}
.lp-mock-sb-btn {
    width: 28px; height: 28px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #475569;
    cursor: default;
}
.lp-mock-sb-active {
    background: rgba(124,58,237,.25);
    color: #a78bfa;
}
.lp-mock-main {
    flex: 1;
    padding: 14px 16px;
}
.lp-mock-title {
    color: #fff;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 12px;
}
.lp-mock-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}
.lp-mock-stat {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 10px;
    padding: 10px 12px;
}
.lp-mock-stat-lbl {
    color: #64748b;
    font-size: 9.5px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 3px;
}
.lp-live-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #22c55e;
    animation: pulse 2s infinite;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
.lp-mock-stat-val {
    color: #fff;
    font-weight: 700;
    font-size: 16px;
    line-height: 1.1;
    margin-bottom: 1px;
}
.lp-mock-stat-sub {
    color: #475569;
    font-size: 8.5px;
    margin-bottom: 6px;
}
.lp-mock-bars {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 22px;
}
.lp-mock-bars span {
    flex: 1;
    border-radius: 2px;
    display: block;
}
.lp-sparkline {
    width: 100%;
    height: 20px;
}
.lp-mock-recent-title {
    color: #64748b;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
}
.lp-mock-calls {
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.lp-mock-call {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 0;
    border-bottom: 1px solid rgba(255,255,255,.04);
}
.lp-mock-call:last-child { border-bottom: none; }
.lp-mock-avatar {
    width: 26px; height: 26px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; color: #fff;
    flex-shrink: 0;
}
.lp-mock-caller { flex: 1; min-width: 0; }
.lp-mock-caller-name {
    color: #e2e8f0;
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}
.lp-mock-caller-num {
    color: #475569;
    font-size: 8.5px;
}
.lp-mock-badge {
    font-size: 8.5px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    flex-shrink: 0;
}
.lp-badge-in  { color: #4ade80; background: rgba(74,222,128,.12); }
.lp-badge-out { color: #f87171; background: rgba(248,113,113,.12); }
.lp-mock-time {
    color: #475569;
    font-size: 9px;
    flex-shrink: 0;
}
.lp-mock-dur {
    color: #64748b;
    font-size: 9px;
    min-width: 28px;
    text-align: right;
}

/* Uptime badge */
.lp-uptime {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(13,17,27,.8);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px;
    padding: 12px 16px;
    width: fit-content;
}
.lp-uptime-shield {
    width: 36px; height: 36px;
    background: rgba(124,58,237,.2);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #a78bfa;
    flex-shrink: 0;
}
.lp-uptime-pct {
    color: #fff;
    font-weight: 700;
    font-size: 13px;
}
.lp-uptime-sub {
    color: #475569;
    font-size: 10px;
    margin-top: 1px;
}
.lp-uptime-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #22c55e;
    margin-left: auto;
    animation: pulse 2s infinite;
}

/* ── Right panel ── */
.lp-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 32px;
    position: relative;
    z-index: 1;
}
.lp-card {
    width: 100%;
    max-width: 440px;
    background: rgba(13,17,27,.95);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 24px;
    padding: 48px 40px;
}
.lp-card-title {
    color: #fff;
    font-size: 30px;
    font-weight: 700;
    margin-bottom: 6px;
    letter-spacing: -.5px;
}
.lp-card-sub {
    color: #475569;
    font-size: 14px;
    margin-bottom: 32px;
}

/* Form */
.lp-form { display: flex; flex-direction: column; gap: 18px; }
.lp-field { display: flex; flex-direction: column; gap: 6px; }
.lp-label {
    color: #cbd5e1;
    font-size: 13px;
    font-weight: 500;
}
.lp-input-wrap {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 10px;
    padding: 0 14px;
    gap: 10px;
    transition: border-color .2s;
}
.lp-input-wrap:focus-within {
    border-color: rgba(124,58,237,.6);
    background: rgba(124,58,237,.05);
}
.lp-input-wrap.lp-input-err { border-color: rgba(239,68,68,.5); }
.lp-input-icon { color: #475569; flex-shrink: 0; }
.lp-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: #e2e8f0;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    padding: 14px 0;
}
.lp-input::placeholder { color: #334155; }
.lp-eye-btn {
    background: none;
    border: none;
    color: #475569;
    cursor: pointer;
    display: flex;
    align-items: center;
    padding: 0;
    transition: color .2s;
}
.lp-eye-btn:hover { color: #94a3b8; }
.lp-err-msg { color: #f87171; font-size: 12px; }

/* Remember + Forgot */
.lp-remember-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.lp-check-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    font-size: 13px;
    cursor: pointer;
}
.lp-checkbox {
    width: 16px; height: 16px;
    accent-color: #7c3aed;
    cursor: pointer;
}
.lp-forgot {
    color: #a78bfa;
    font-size: 13px;
    text-decoration: none;
    font-weight: 500;
    transition: color .2s;
}
.lp-forgot:hover { color: #c4b5fd; }

/* Submit button */
.lp-submit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 15px 24px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(90deg, #7c3aed 0%, #f97316 100%);
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: opacity .2s, transform .1s;
}
.lp-submit-btn:hover { opacity: .92; transform: translateY(-1px); }
.lp-submit-btn:active { transform: translateY(0); }
.lp-submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

/* OR divider */
.lp-divider {
    display: flex;
    align-items: center;
    gap: 12px;
}
.lp-divider-line {
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,.08);
}
.lp-divider-text {
    color: #334155;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: .5px;
    white-space: nowrap;
}

/* Social buttons */
.lp-social-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.lp-social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 10px;
    color: #cbd5e1;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: all .2s;
}
.lp-social-btn:hover {
    background: rgba(255,255,255,.07);
    border-color: rgba(255,255,255,.15);
}

/* Security note */
.lp-security {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    color: #334155;
    font-size: 12px;
}

/* Contact */
.lp-contact {
    text-align: center;
    color: #475569;
    font-size: 13px;
    margin-top: 24px;
}
.lp-contact-link {
    color: #a78bfa;
    font-weight: 500;
    text-decoration: none;
}
.lp-contact-link:hover { color: #c4b5fd; }

/* ── Responsive ── */
@media (max-width: 960px) {
    .lp-left { display: none; }
    .lp-right { padding: 24px 16px; }
    .lp-card { padding: 36px 24px; }
    .lp-headline { font-size: 36px; }
}
</style>
