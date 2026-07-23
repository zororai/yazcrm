@php
// Self-contained icon set (replaces img.icons8.com <img> tags) so this page
// never depends on an external CDN being reachable — see incident where the
// server's outbound network/DNS was down and every icon + chart broke.
$dashIcons = [
    'phone'          => '<path d="M4 5c0-1 1-2 2-2h2l2 5-2 2c1 3 3 5 6 6l2-2 5 2v2c0 1-1 2-2 2-8 0-15-7-15-15Z"/>',
    'incoming-call'  => '<path d="M17 7 7 17M7 9V17H15"/>',
    'outgoing-call'  => '<path d="M7 17 17 7M9 7H17V15"/>',
    'high-priority'  => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17h.01"/>',
    'ok'             => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    'checked'        => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    'timer'          => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M10 2h4"/>',
    'conference-call'=> '<circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><path d="M2 20c0-3.3 2.7-6 6-6s6 2.7 6 6M10 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/>',
    'goal'           => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
    'combo-chart'    => '<path d="M3 20V10M9 20V4M15 20v-7M21 20V8"/>',
    'bar-chart'      => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'team'           => '<circle cx="9" cy="8" r="3"/><path d="M2 20c0-3.3 3.1-6 7-6s7 2.7 7 6"/><circle cx="18" cy="9" r="2.5"/><path d="M16.5 14c2.8.3 4.5 2.3 4.5 5"/>',
    'calendar'       => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'bot'            => '<rect x="4" y="8" width="16" height="12" rx="2"/><circle cx="9" cy="14" r="1.3"/><circle cx="15" cy="14" r="1.3"/><path d="M12 8V4M9 4h6"/>',
    'add-user-male'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5M18 8v6M15 11h6"/>',
    'flash-on'       => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>',
];
$dashIcon = fn (string $name, string $color = '#3b82f6') =>
    '<svg viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
    . ($dashIcons[$name] ?? '') . '</svg>';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Helpline Analytics</title>
<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
<script src="{{ asset('vendor/lucide.min.js') }}"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f0f4f8;color:#1e293b;min-height:100vh;overflow-x:hidden}
body::before{content:'';position:fixed;top:0;left:0;right:0;bottom:0;
  background:radial-gradient(ellipse 60% 50% at 10% 10%,rgba(219,234,254,.8) 0%,transparent 60%),
             radial-gradient(ellipse 50% 40% at 90% 90%,rgba(209,250,229,.5) 0%,transparent 60%);
  pointer-events:none;z-index:0}
.app{min-height:100vh;position:relative;z-index:1}

/* ── Floating sidebar ── */
.sidebar{
  position:fixed;
  left:18px;
  top:50%;
  transform:translateY(-50%);
  width:56px;
  background:#fff;
  border-radius:24px;
  padding:12px 8px;
  display:flex;flex-direction:column;align-items:center;gap:4px;
  box-shadow:0 8px 32px rgba(0,0,0,.13),0 2px 8px rgba(0,0,0,.07);
  border:1px solid rgba(0,0,0,.06);
  z-index:100;
}
.sb-logo{
  width:36px;height:36px;
  background:linear-gradient(135deg,#3b82f6,#1e40af);
  border-radius:14px;display:flex;align-items:center;justify-content:center;
  margin-bottom:8px;flex-shrink:0;
}
.sb-logo svg{width:22px;height:18px}
.sb-btn{
  width:40px;height:40px;border:none;background:transparent;
  border-radius:14px;display:flex;align-items:center;justify-content:center;
  cursor:pointer;color:#94a3b8;transition:all .2s;flex-shrink:0;
}
.sb-btn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:all .2s}
.sb-btn:hover{background:#f1f5f9;color:#475569}
.sb-btn.active{background:#eff6ff;color:#3b82f6}
.sb-divider{width:28px;height:1px;background:#f1f5f9;margin:4px 0;flex-shrink:0}

/* Main */
.main{padding:24px 24px 20px 92px;display:flex;flex-direction:column;gap:16px;min-height:100vh}

/* Cards */
.glass{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.05)}
.s-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;box-shadow:0 1px 6px rgba(0,0,0,.04)}
.s-card h3{font-size:13px;font-weight:600;color:#374151;margin-bottom:12px}

/* Header */
.page-hdr{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px}
.page-title{font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-.5px}
.page-sub{font-size:12px;color:#94a3b8;margin-top:3px}
.hdr-right{display:flex;align-items:center;gap:10px}
.live-pill{display:flex;align-items:center;gap:6px;background:#f0fdf4;border:1px solid #bbf7d0;
  border-radius:20px;padding:5px 13px;font-size:11px;color:#16a34a;font-weight:600}
.live-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.total-pill{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:8px 16px;
  text-align:right;box-shadow:0 1px 6px rgba(0,0,0,.06)}
.total-pill .tv{font-size:20px;font-weight:800;color:#0f172a}
.total-pill .tl{font-size:10px;color:#94a3b8;margin-top:1px}

/* Section header row */
.sec-hdr{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px}
.sec-title{font-size:15px;font-weight:700;color:#0f172a}

/* Period buttons */
.period-wrap{display:flex;gap:4px;background:#f1f5f9;border-radius:12px;padding:4px;align-items:center}
.period-btn{padding:6px 13px;border:none;background:transparent;border-radius:8px;cursor:pointer;
  font-family:'Inter',sans-serif;font-size:12px;font-weight:500;color:#64748b;transition:all .15s;white-space:nowrap}
.period-btn:hover{color:#1e293b}
.period-btn.active-period{background:#fff;color:#1e293b;box-shadow:0 1px 4px rgba(0,0,0,.1);font-weight:600}
/* Period dropdowns (month / year pickers) */
.period-select-wrap{position:relative;display:flex;align-items:center}
.period-select{padding:5px 24px 5px 10px;border:none;background:transparent;border-radius:8px;cursor:pointer;
  font-family:'Inter',sans-serif;font-size:12px;font-weight:500;color:#64748b;appearance:none;-webkit-appearance:none;
  transition:all .15s;white-space:nowrap}
.period-select:hover{color:#1e293b}
.period-select.active-period{background:#fff;color:#1e293b;box-shadow:0 1px 4px rgba(0,0,0,.1);font-weight:600}
.period-select-wrap::after{content:"▾";position:absolute;right:6px;top:50%;transform:translateY(-50%);
  font-size:10px;color:#94a3b8;pointer-events:none}

/* KPI grid */
.kpi-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.kpi{flex:1;min-width:110px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;box-shadow:0 1px 6px rgba(0,0,0,.04)}
.kpi-icon{margin-bottom:6px;display:flex;align-items:center}.kpi-icon img,.kpi-icon svg{width:28px;height:28px}
.kpi-val{font-size:22px;font-weight:800;color:#0f172a;line-height:1}
.kpi-lbl{font-size:10px;color:#94a3b8;margin-top:3px;font-weight:500}
.svc-kpi-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.svc-kpi-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.svc-kpi-title{font-weight:700;font-size:14px;color:#1e293b;margin-bottom:14px}
.svc-kpi-body{display:flex;align-items:center;gap:14px}
.svc-kpi-icon{width:48px;height:48px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}.svc-kpi-icon img,.svc-kpi-icon svg{width:30px;height:30px}
.svc-kpi-num{font-size:28px;font-weight:900;color:#6366f1;letter-spacing:-1px;line-height:1}
.svc-kpi-foot{margin-top:16px;padding-top:12px;border-top:2px solid #f97316;display:flex;align-items:center;justify-content:space-between}
.svc-kpi-foot-lbl{font-size:11px;color:#64748b;font-weight:600}
.svc-kpi-rate{font-size:15px;font-weight:800;color:#f97316}

/* Grids */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px}

/* Progress bars */
.pb{margin-bottom:10px}
.pb-hdr{display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px}
.pb-lbl{color:#475569;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:72%}
.pb-val{color:#94a3b8;font-size:10px}
.pb-track{background:#f1f5f9;border-radius:3px;height:5px}
.pb-fill{height:100%;border-radius:3px;transition:width .5s}

/* Chart wrappers */
.ch140{height:140px;position:relative}
.ch180{height:180px;position:relative}
.ch220{height:220px;position:relative}
.ch120{height:120px;position:relative}

/* Mini stat stack */
.stat-stack{display:flex;flex-direction:column;gap:10px}
.stat-mini{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 6px rgba(0,0,0,.04)}
.sm-icon{width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.sm-val{font-size:14px;font-weight:700;color:#0f172a;line-height:1.1}
.sm-lbl{font-size:10px;color:#94a3b8;margin-top:2px}

/* Donut card */
.donut-wrap{position:relative;width:110px;height:110px;margin:0 auto 12px}
.donut-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center}
.donut-pct{font-size:18px;font-weight:800;color:#0f172a;line-height:1}
.donut-sub{font-size:9px;color:#94a3b8;margin-top:2px}
.ov-rows{display:flex;flex-direction:column;gap:8px}
.ov-row{display:flex;align-items:center;gap:7px}
.ov-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.ov-lbl{flex:1;font-size:11px;color:#64748b}
.ov-val{font-size:11px;font-weight:700;color:#0f172a}
.ov-chg{font-size:10px;margin-left:3px;font-weight:500}
.ov-chg.up{color:#16a34a}
.ov-chg.muted{color:#94a3b8}

/* Bottom grid */
.bot-grid{display:grid;grid-template-columns:1fr 220px 165px;gap:14px}

/* Challenge items */
.ch-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f1f5f9}
.ch-item:last-child{border-bottom:none;padding-bottom:0}
.ch-ring{width:22px;height:22px;border-radius:50%;border:2px solid #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:9px;flex-shrink:0;color:#cbd5e1}
.ch-ring.done{background:#22c55e;border-color:#22c55e;color:#fff;font-size:11px}
.ch-body{flex:1;min-width:0}
.ch-name{font-size:12px;font-weight:500;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ch-prog{font-size:10px;color:#94a3b8;margin-top:1px}
.ch-badge{font-size:10px;font-weight:600;padding:3px 9px;border-radius:20px;white-space:nowrap;flex-shrink:0}
.badge-go{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.badge-done{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe}
.badge-alert{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}

/* Calendar */
.cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.cal-nav span{font-size:13px;font-weight:700;color:#0f172a}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;text-align:center}
.cal-dn{font-size:9px;color:#94a3b8;font-weight:600;padding:0 0 5px;text-transform:uppercase}
.cal-d{font-size:11px;color:#64748b;padding:5px 2px;border-radius:7px;font-weight:400}
.cal-d.today{background:#3b82f6;color:#fff;font-weight:700}
.cal-d.empty{color:transparent;pointer-events:none}

/* Output */
.out-val{font-size:38px;font-weight:900;color:#0f172a;line-height:1;margin-bottom:4px}
.out-lbl{font-size:10px;color:#94a3b8;margin-bottom:12px}
.out-badge{display:inline-block;background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;border-radius:20px;padding:4px 12px;font-size:11px;font-weight:600}

/* Table */
table{width:100%;border-collapse:collapse;font-size:12px}
th{padding:8px 10px;text-align:left;font-weight:600;font-size:10px;color:#94a3b8;border-bottom:1px solid #f1f5f9;text-transform:uppercase;letter-spacing:.4px}
td{padding:7px 10px;border-bottom:1px solid #f8fafc;color:#475569}
tr:hover td{background:#f8fafc}
.tbl-wrap{overflow-x:auto}

/* Top grid */
.top-grid{display:grid;grid-template-columns:1fr 165px 260px;gap:14px}

/* Card header */
.card-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.card-title{font-size:13px;font-weight:600;color:#374151}
.card-tag{font-size:11px;color:#94a3b8;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:3px 10px;cursor:pointer;border:none;font-family:'Inter',sans-serif}

/* Footer */
.footer{text-align:center;padding:12px;font-size:10px;color:#94a3b8;border-top:1px solid #e2e8f0}

/* ── Social Listening Matrix ── */
.slm-5col{display:grid;grid-template-columns:175px 215px 1fr 235px 165px;gap:0;padding:0;overflow:hidden}
.slm-panel{padding:11px 12px;border-right:1px solid #e2e8f0;min-width:0}
.slm-panel:last-child{border-right:none}
.slm-ptitle{font-size:10px;font-weight:700;color:#fff;background:#1e3a5f;padding:6px 10px;margin:-11px -12px 10px;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.slm-src-row{display:flex;align-items:center;gap:5px;padding:3px 0;border-bottom:1px dotted #f1f5f9;font-size:10.5px}
.slm-src-row:last-child{border-bottom:none}
.slm-src-name{flex:1;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.slm-src-cnt{font-weight:700;color:#0f172a;font-size:11px;flex-shrink:0}
.slm-src-sub{font-size:9px;font-weight:700;color:#fff;background:#3b82f6;border-radius:3px;padding:2px 6px;margin:5px 0 3px;text-transform:uppercase;letter-spacing:.3px}
.slm-total-row{display:flex;justify-content:space-between;background:#0f172a;color:#fff;padding:4px 7px;border-radius:4px;margin-top:5px;font-weight:700;font-size:11px}
.slm-issue-row{display:flex;align-items:center;gap:4px;padding:2px 0;border-bottom:1px dotted #f1f5f9;font-size:10px}
.slm-issue-num{color:#94a3b8;min-width:16px;font-size:9px}
.slm-issue-name{flex:1;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.slm-issue-cnt{font-weight:700;color:#0f172a;flex-shrink:0}
.slm-cat-title{font-size:10px;font-weight:700;padding:2px 5px;border-radius:3px;margin:5px 0 3px;display:flex;align-items:center;gap:4px}
.slm-risk-row{padding:6px 0;border-bottom:1px solid #f1f5f9}
.slm-risk-row:last-child{border-bottom:none}
.slm-risk-hdr{display:flex;align-items:center;gap:6px;margin-bottom:3px}
.slm-ref-row{display:flex;align-items:center;gap:6px;padding:4px 0;border-bottom:1px dotted #f1f5f9;font-size:10.5px}
.slm-ref-row:last-child{border-bottom:none}
.slm-ref-name{flex:1;color:#475569}
.slm-ref-cnt{font-weight:700;color:#0f172a;font-size:13px;flex-shrink:0}
.slm-wf-steps{display:flex;gap:8px;padding:10px 14px}
.slm-wf-step{flex:1;background:rgba(255,255,255,.07);border-radius:10px;padding:10px 6px;text-align:center;border:1px solid rgba(255,255,255,.1)}
.slm-wf-icon{font-size:20px;margin-bottom:3px}
.slm-wf-name{font-weight:700;font-size:11px;color:#fff;text-transform:uppercase;letter-spacing:.3px}
.slm-wf-desc{font-size:8.5px;color:#64748b;margin:2px 0 5px;line-height:1.3}
.slm-wf-stat{font-size:14px;font-weight:800;color:#60a5fa}
.slm-wf-slbl{font-size:8px;color:#475569}
.slm-urg-card{flex:1;min-width:80px;background:#fff;border:2px solid #dc2626;border-radius:12px;padding:10px;text-align:center}
.slm-urg-icon{font-size:18px;margin-bottom:3px}
.slm-urg-lbl{font-size:9px;color:#6b7280;line-height:1.2;margin-bottom:3px}
.slm-urg-num{font-size:24px;font-weight:900;color:#dc2626;line-height:1}
.rpill-low{background:#dcfce7;color:#16a34a;border-radius:4px;padding:1px 7px;font-size:9.5px;font-weight:700}
.rpill-med{background:#fef9c3;color:#a16207;border-radius:4px;padding:1px 7px;font-size:9.5px;font-weight:700}
.rpill-high{background:#fee2e2;color:#dc2626;border-radius:4px;padding:1px 7px;font-size:9.5px;font-weight:700}
.rpill-emerg{background:#7f1d1d;color:#fff;border-radius:4px;padding:1px 7px;font-size:9.5px;font-weight:700}

@media(max-width:900px){
  .sidebar{display:none}
  .slm-5col{grid-template-columns:1fr}
  .top-grid,.bot-grid,.g2,.g3{grid-template-columns:1fr}
  .main{padding:14px}
}
@media(min-width:901px){
  .sb-btn i{display:flex;align-items:center;justify-content:center;width:18px;height:18px}
}
@media print{
  /* Sections already hidden via inline style="display:none" (set by showSection())
     stay hidden — only the currently active tab is visible and prints. */
  .sidebar,#print-dashboard-btn,.period-wrap,#svc-filter-clear,.live-pill{display:none!important}
  .main{padding:16px!important}
  body{background:#fff!important}
  body::before{display:none!important}
}
</style>
</head>
<body>

@php
  $validPct  = $total ? round($validTotal/$total*100)  : 0;
  $repeatPct = $total ? round($repeatTotal/$total*100) : 0;
  $immPct    = $total ? round($immediateAct/$total*100): 0;
  $monthArr  = $months->toArray();
  $last7     = array_slice($monthArr, -7, 7, true);
  $monthSum  = array_sum($monthArr);
  $maxMonth  = max(array_values($monthArr) ?: [1]);
  $top3      = $byPurpose->take(3);
  $now       = \Carbon\Carbon::now('Africa/Johannesburg');
  $today     = $now->day;
  $calMonthName  = $now->format('F Y');
  $calFirstDay   = $now->copy()->startOfMonth()->dayOfWeek;
  $calDays       = $now->daysInMonth;
@endphp

<div class="app">

<!-- ── Floating Sidebar ── -->
<aside class="sidebar">
  <div class="sb-logo" title="Youth Advocates">
    <!-- Youth Advocates mark (same as the login page): two overlapping rounded-pill arms + teardrop -->
    <svg width="22" height="18" viewBox="0 0 130 108" xmlns="http://www.w3.org/2000/svg">
      <rect x="12" y="0" width="28" height="82" rx="14" fill="#e8512a" transform="rotate(34 26 66)"/>
      <rect x="90" y="0" width="28" height="82" rx="14" fill="#6835a2" transform="rotate(-34 104 66)"/>
      <ellipse cx="65" cy="14" rx="11" ry="14" fill="#ffffff"/>
    </svg>
  </div>

  <button class="sb-btn active" onclick="showSection('overview',this)" title="Overview">
    <i data-lucide="layout-dashboard"></i>
  </button>
  <button class="sb-btn" onclick="showSection('geographic',this)" title="Geographic">
    <i data-lucide="map-pin"></i>
  </button>
  <button class="sb-btn" onclick="showSection('demographics',this)" title="Demographics">
    <i data-lucide="users"></i>
  </button>
  <button class="sb-btn" onclick="showSection('services',this)" title="Services">
    <i data-lucide="git-branch"></i>
  </button>

  <div class="sb-divider"></div>

  <button class="sb-btn" onclick="showSection('calls',this)" title="Call Details">
    <i data-lucide="phone-call"></i>
  </button>
  <button class="sb-btn" onclick="showSection('trends',this)" title="Trends">
    <i data-lucide="trending-up"></i>
  </button>
  <button class="sb-btn" onclick="showSection('social',this)" title="Social Listening Matrix">
    <i data-lucide="activity"></i>
  </button>
  <button class="sb-btn" onclick="showSection('targets',this)" title="Call Targets">
    <i data-lucide="target"></i>
  </button>
  <button class="sb-btn" onclick="showSection('bot',this)" title="Bot Analytics">
    <i data-lucide="bot"></i>
  </button>
</aside>

<!-- ── Main ── -->
<main class="main">

<!-- Header -->
<div class="page-hdr">
  <div style="display:flex;align-items:center;gap:18px">
    <img src="{{ asset('logo.png') }}" alt="Youth Advocates" style="height:84px;width:auto;flex-shrink:0">
    <div>
      <div class="page-title">Helpline Analytics</div>
      <div class="page-sub">National Youth Helpline &middot; {{ $lastUpdated ? \Carbon\Carbon::parse($lastUpdated)->format('d M Y, H:i') : 'N/A' }}</div>
    </div>
  </div>
  <div class="hdr-right">
    <div style="display:flex;align-items:center;gap:6px">
      <label style="font-size:11px;color:#64748b;font-weight:600;white-space:nowrap">From</label>
      <input type="month" id="range-from" value="{{ $dateFrom ?? '' }}"
        style="font-size:11px;border:1px solid #e2e8f0;border-radius:10px;padding:4px 8px;background:#fff;color:#374151;font-family:'Inter',sans-serif;">
      <label style="font-size:11px;color:#64748b;font-weight:600;white-space:nowrap">To</label>
      <input type="month" id="range-to" value="{{ $dateTo ?? '' }}"
        style="font-size:11px;border:1px solid #e2e8f0;border-radius:10px;padding:4px 8px;background:#fff;color:#374151;font-family:'Inter',sans-serif;">
      <button onclick="applyDateRange()"
        style="font-size:11px;font-weight:600;padding:5px 12px;border-radius:10px;border:none;background:#8b5cf6;color:#fff;cursor:pointer;font-family:'Inter',sans-serif;white-space:nowrap">Apply Range</button>
      <a id="range-clear" href="#" onclick="clearDateRange();return false;"
        style="font-size:10px;color:#ef4444;text-decoration:none;font-weight:600;white-space:nowrap;{{ ($dateFrom ?? '') ? '' : 'display:none' }}">✕ Clear</a>
    </div>
    <div style="display:flex;align-items:center;gap:6px">
      <label style="font-size:11px;color:#64748b;font-weight:600;white-space:nowrap">Project</label>
      <select id="project-filter"
        style="font-size:11px;border:1px solid #e2e8f0;border-radius:10px;padding:5px 10px;background:#fff;color:#374151;cursor:pointer;font-family:'Inter',sans-serif;max-width:160px">
        <option value="">All Projects</option>
        @foreach($allProjects as $proj)
          <option value="{{ $proj }}" {{ $projectFilter === $proj ? 'selected' : '' }}>{{ $proj }}</option>
        @endforeach
      </select>
      <button onclick="applyProjectFilter(document.getElementById('project-filter').value)"
        style="font-size:11px;font-weight:600;padding:5px 12px;border-radius:10px;border:none;background:#3b82f6;color:#fff;cursor:pointer;font-family:'Inter',sans-serif;white-space:nowrap">Apply</button>
      <a id="proj-filter-clear" href="#" onclick="applyProjectFilter('');document.getElementById('project-filter').value='';return false;"
        style="font-size:10px;color:#ef4444;text-decoration:none;font-weight:600;white-space:nowrap;{{ $projectFilter ? '' : 'display:none' }}">✕ Clear</a>
    </div>
    <div style="display:flex;align-items:center;gap:6px">
      <label style="font-size:11px;color:#64748b;font-weight:600;white-space:nowrap">Service</label>
      <select id="service-filter"
        style="font-size:11px;border:1px solid #e2e8f0;border-radius:10px;padding:5px 10px;background:#fff;color:#374151;cursor:pointer;font-family:'Inter',sans-serif;max-width:180px">
        <option value="">All Services</option>
        @foreach($allServices as $svc)
          <option value="{{ $svc }}" {{ $serviceFilter === $svc ? 'selected' : '' }}>{{ $svc }}</option>
        @endforeach
      </select>
      <button onclick="applyServiceFilter(document.getElementById('service-filter').value)"
        style="font-size:11px;font-weight:600;padding:5px 12px;border-radius:10px;border:none;background:#3b82f6;color:#fff;cursor:pointer;font-family:'Inter',sans-serif;white-space:nowrap">Apply</button>
      <a id="svc-filter-clear" href="#" onclick="applyServiceFilter('');document.getElementById('service-filter').value='';return false;"
        style="font-size:10px;color:#ef4444;text-decoration:none;font-weight:600;white-space:nowrap;{{ $serviceFilter ? '' : 'display:none' }}">✕ Clear</a>
    </div>
    <div style="display:flex;align-items:center;gap:6px">
      <label style="font-size:11px;color:#64748b;font-weight:600;white-space:nowrap">Gender</label>
      <select id="gender-filter"
        style="font-size:11px;border:1px solid #e2e8f0;border-radius:10px;padding:5px 10px;background:#fff;color:#374151;cursor:pointer;font-family:'Inter',sans-serif;max-width:130px">
        <option value="">All Genders</option>
        <option value="male"   {{ $genderFilter==='male'   ? 'selected':'' }}>Male</option>
        <option value="female" {{ $genderFilter==='female' ? 'selected':'' }}>Female</option>
      </select>
      <button onclick="applyGenderFilter(document.getElementById('gender-filter').value)"
        style="font-size:11px;font-weight:600;padding:5px 12px;border-radius:10px;border:none;background:#3b82f6;color:#fff;cursor:pointer;font-family:'Inter',sans-serif;white-space:nowrap">Apply</button>
      <a href="#" onclick="applyGenderFilter('');document.getElementById('gender-filter').value='';return false;"
        style="font-size:10px;color:#ef4444;text-decoration:none;font-weight:600;white-space:nowrap;{{ $genderFilter ? '' : 'display:none' }}" id="gender-filter-clear">✕ Clear</a>
    </div>
    <div style="display:flex;align-items:center;gap:6px">
      <label style="font-size:11px;color:#64748b;font-weight:600;white-space:nowrap">Age Group</label>
      <select id="age-filter"
        style="font-size:11px;border:1px solid #e2e8f0;border-radius:10px;padding:5px 10px;background:#fff;color:#374151;cursor:pointer;font-family:'Inter',sans-serif;max-width:130px">
        <option value="">All Ages</option>
        <option value="u18"   {{ $ageFilter==='u18'   ? 'selected':'' }}>Under 18</option>
        <option value="18-24" {{ $ageFilter==='18-24'  ? 'selected':'' }}>18 – 24</option>
        <option value="25-34" {{ $ageFilter==='25-34'  ? 'selected':'' }}>25 – 34</option>
        <option value="35-44" {{ $ageFilter==='35-44'  ? 'selected':'' }}>35 – 44</option>
        <option value="45p"   {{ $ageFilter==='45p'   ? 'selected':'' }}>45+</option>
      </select>
      <button onclick="applyAgeFilter(document.getElementById('age-filter').value)"
        style="font-size:11px;font-weight:600;padding:5px 12px;border-radius:10px;border:none;background:#3b82f6;color:#fff;cursor:pointer;font-family:'Inter',sans-serif;white-space:nowrap">Apply</button>
      <a href="#" onclick="applyAgeFilter('');document.getElementById('age-filter').value='';return false;"
        style="font-size:10px;color:#ef4444;text-decoration:none;font-weight:600;white-space:nowrap;{{ $ageFilter ? '' : 'display:none' }}" id="age-filter-clear">✕ Clear</a>
    </div>
    <div class="live-pill"><span class="live-dot" id="live-dot"></span> Live &mdash; <span id="live-updated">now</span></div>
    <div class="total-pill"><div class="tv" id="hdr-total">{{ number_format($total - $displayTotalOffset) }}</div><div class="tl">{{ $projectFilter || $serviceFilter || $genderFilter || $ageFilter ? 'Filtered Cases' : 'All-time Interactions' }}</div></div>
    <button id="print-dashboard-btn" onclick="printCurrentTab()"
      style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:8px 14px;border-radius:16px;border:1px solid #e2e8f0;background:#fff;color:#374151;cursor:pointer;white-space:nowrap">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Download
    </button>
  </div>
</div>


@php
  $dd   = $periodData[$ticketDefaultPeriod];
  $ddT  = $dd['total'] ?: 1;
  $ddVp = round($dd['valid']   / $ddT * 100);
  $ddRp = round($dd['repeat']  / $ddT * 100);
  $ddIp = round($dd['imm_act'] / $ddT * 100);
  $sc   = $callStats[$ticketDefaultPeriod]; // match same period as tickets
@endphp

{{-- ══════════════════════════════════ OVERVIEW ══════════════════════════════════ --}}
<div id="sec-overview" class="section">
@php
  $age35p   = ($ageGroups['35–44'] ?? 0) + ($ageGroups['45+'] ?? 0);
  $circ     = 2 * 3.14159 * 26; // circumference for r=26
@endphp

{{-- ── Period filter ── --}}
<div class="sec-hdr" style="margin-bottom:12px">
  <span class="sec-title">Overview</span>
  <div class="period-wrap">
    <button class="period-btn {{ $ticketDefaultPeriod==='day'  ?'active-period':'' }}" onclick="setPeriod('overview','day',this)">Today</button>
    <button class="period-btn {{ $ticketDefaultPeriod==='week' ?'active-period':'' }}" onclick="setPeriod('overview','week',this)">This Week</button>
    <div class="period-select-wrap"><select id="month-select-overview" class="period-select {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onchange="onMonthSelect('overview',this)"></select></div>
    <div class="period-select-wrap"><select id="year-select-overview" class="period-select {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onchange="onYearSelect('overview',this)"></select></div>
    @if($dateFrom)<button class="period-btn active-period" onclick="setPeriod('overview','range',this)">{{ \Carbon\Carbon::parse($dateFrom.'-01')->format('M Y') }}{{ $dateTo && $dateTo!==$dateFrom ? ' – '.\Carbon\Carbon::parse($dateTo.'-01')->format('M Y') : '' }}</button>@endif
  </div>
</div>

{{-- ── Row 1: 4 KPI cards ── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:12px">

  {{-- Card 1: Total Calls --}}
  <div style="background:#fff;border-radius:14px;padding:16px 14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
      <div style="width:36px;height:36px;border-radius:50%;background:#0891b2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.8 19.8 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3.07-8.67A2 2 0 013.6 4.11h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 11.9a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 18v3z"/></svg>
      </div>
      <span style="font-weight:700;font-size:13px;color:#374151">Total Cases</span>
    </div>
    <div style="font-size:34px;font-weight:900;color:#0891b2;line-height:1.1;margin-bottom:8px" id="ov-total-new">{{ number_format($total) }}</div>
    <div style="height:110px"><canvas id="ovAgeGenderChart"></canvas></div>
  </div>

  {{-- Card 2: Referred Cases = tickets with a referral destination --}}
  @php $referredCasesTotal = $referredTotal; $referredCasesPct = $total > 0 ? round($referredCasesTotal/$total*100,1) : 0; @endphp
  <div style="background:#fff;border-radius:14px;padding:16px 14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
      <div style="width:36px;height:36px;border-radius:50%;background:#2563eb;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      </div>
      <span style="font-weight:700;font-size:13px;color:#374151">Referred Cases</span>
    </div>
    <div style="font-size:34px;font-weight:900;color:#2563eb;line-height:1.1;margin-bottom:10px" id="ov-referred-new">{{ number_format($referredCasesTotal) }}</div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
      <svg width="60" height="60" viewBox="0 0 60 60">
        <circle cx="30" cy="30" r="26" fill="none" stroke="#dbeafe" stroke-width="6"/>
        <circle id="ov-ref-comp-arc" cx="30" cy="30" r="26" fill="none" stroke="#2563eb" stroke-width="6"
          stroke-dasharray="{{ $circ * min($referredCasesPct,100) / 100 }} {{ $circ }}"
          stroke-linecap="round" transform="rotate(-90 30 30)"/>
        <text id="ov-ref-comp-txt" x="30" y="34" text-anchor="middle" font-size="10" font-weight="800" fill="#2563eb">{{ $referredCasesPct }}%</text>
      </svg>
      <div>
        <div style="font-size:11px;font-weight:700;color:#374151">% of Total</div>
        <div id="ov-uptake-cnt" style="font-size:10px;color:#9ca3af">{{ number_format(max(0, $uptakeTotal - $displayUptakeOffset)) }} uptakes confirmed</div>
      </div>
    </div>

  </div>

  {{-- Card 3: High Risk Cases = immediate_action_required --}}
  @php $highRiskPct = $total > 0 ? round($immediateAct/$total*100,1) : 0; @endphp
  <div style="background:#fff;border-radius:14px;padding:16px 14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
      <div style="width:36px;height:36px;border-radius:50%;background:#ea580c;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      </div>
      <span style="font-weight:700;font-size:13px;color:#374151">High Risk Cases</span>
    </div>
    <div style="font-size:34px;font-weight:900;color:#dc2626;line-height:1.1;margin-bottom:10px" id="ov-resolved-new">{{ number_format($immediateAct) }}</div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
      <svg width="60" height="60" viewBox="0 0 60 60">
        <circle cx="30" cy="30" r="26" fill="none" stroke="#fee2e2" stroke-width="6"/>
        <circle id="ov-pending-arc" cx="30" cy="30" r="26" fill="none" stroke="#ea580c" stroke-width="6"
          stroke-dasharray="{{ $circ * $highRiskPct / 100 }} {{ $circ }}"
          stroke-linecap="round" transform="rotate(-90 30 30)"/>
        <text id="ov-pending-txt" x="30" y="34" text-anchor="middle" font-size="11" font-weight="800" fill="#ea580c">{{ $highRiskPct }}%</text>
      </svg>
      <div>
        <div style="font-size:11px;font-weight:700;color:#374151">% of Total</div>
        <div id="ov-pending-open" style="font-size:10px;color:#9ca3af">{{ number_format($pendingTotal) }} pending</div>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px">
      <svg width="60" height="60" viewBox="0 0 60 60">
        <circle cx="30" cy="30" r="26" fill="none" stroke="#fef3c7" stroke-width="6"/>
        <circle cx="30" cy="30" r="26" fill="none" stroke="#d97706" stroke-width="6"
          stroke-dasharray="{{ $total > 0 ? $circ * ($invalidTotal/$total*100) / 100 : 0 }} {{ $circ }}"
          stroke-linecap="round" transform="rotate(-90 30 30)"/>
        <text x="30" y="34" text-anchor="middle" font-size="11" font-weight="800" fill="#d97706">{{ $total > 0 ? round($invalidTotal/$total*100,1) : 0 }}%</text>
      </svg>
      <div>
        <div style="font-size:11px;font-weight:700;color:#374151">❌ Invalid Calls</div>
        <div style="font-size:16px;font-weight:900;color:#374151">{{ number_format($invalidTotal) }}</div>
      </div>
    </div>
  </div>

  {{-- Card 4: YALeP / SBC --}}
  <div style="background:#fff;border-radius:14px;padding:16px 14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
      <div style="width:36px;height:36px;border-radius:50%;background:#16a34a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      </div>
      <span style="font-weight:700;font-size:13px;color:#374151">YALeP</span>
    </div>
    <div style="font-size:34px;font-weight:900;color:#dc2626;line-height:1.1">{{ number_format($sbcTotal) }}</div>
    <div style="font-size:11px;color:#6b7280;margin-bottom:8px">YALeP Course Completions</div>
    <div style="font-size:11px;color:#6b7280;margin-bottom:4px">Total Youth Engaged</div>
    <div style="font-size:28px;font-weight:900;color:#16a34a;line-height:1.1;margin-bottom:10px">{{ number_format($sbcEngagedTotal) }}</div>


    <div style="display:flex;align-items:center;gap:10px">
      <svg width="50" height="50" viewBox="0 0 60 60">
        <circle cx="30" cy="30" r="26" fill="none" stroke="#dbeafe" stroke-width="6"/>
        <circle id="ov-male-arc" cx="30" cy="30" r="26" fill="none" stroke="#2563eb" stroke-width="6"
          stroke-dasharray="{{ $circ * min($malePct,100) / 100 }} {{ $circ }}"
          stroke-linecap="round" transform="rotate(-90 30 30)"/>
        <text id="ov-male-txt" x="30" y="34" text-anchor="middle" font-size="11" font-weight="800" fill="#2563eb">{{ $malePct }}%</text>
      </svg>
      <div style="font-size:10px;color:#6b7280">Males</div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <svg width="50" height="50" viewBox="0 0 60 60">
        <circle cx="30" cy="30" r="26" fill="none" stroke="#fce7f3" stroke-width="6"/>
        <circle id="ov-female-arc" cx="30" cy="30" r="26" fill="none" stroke="#db2777" stroke-width="6"
          stroke-dasharray="{{ $circ * min($femalePct,100) / 100 }} {{ $circ }}"
          stroke-linecap="round" transform="rotate(-90 30 30)"/>
        <text id="ov-female-txt" x="30" y="34" text-anchor="middle" font-size="11" font-weight="800" fill="#db2777">{{ $femalePct }}%</text>
      </svg>
      <div style="font-size:10px;color:#6b7280">Females</div>
    </div>
  </div>

</div>{{-- /row 1 --}}

{{-- ── Row 1b: Call Activity ── --}}
<div style="background:#fff;border-radius:14px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb;margin-bottom:12px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
    <span style="font-size:13px;font-weight:700;color:#374151">📞 Call Activity</span>
    <span style="font-size:11px;color:#6b7280" id="ov-trend-label">Calls by Hour — Today</span>
  </div>
  <div style="height:160px"><canvas id="ovTrendChart"></canvas></div>
  <!-- Call KPIs from PBX -->
  @php $ovCallStats = $callStats[$callDefaultPeriod] ?? ['total'=>0,'inbound'=>0,'outbound'=>0,'answered'=>0]; @endphp
  <div style="display:flex;gap:0;border-top:1px solid #f3f4f6;margin-top:12px;padding-top:10px">
    @foreach([['📞','Total Calls','ov-c-total',$ovCallStats['total']],['📥','Inbound','ov-c-inbound',$ovCallStats['inbound']],
              ['📤','Outbound','ov-c-outbound',$ovCallStats['outbound']],['🚨','Urgent','ov-c-urgent',$urgentOpen],
              ['✅','Answered','ov-c-answered',$ovCallStats['answered']]] as [$ico,$lbl,$id,$val])
    <div style="flex:1;text-align:center;border-right:1px solid #f3f4f6;padding:0 8px">
      <div style="font-size:16px">{{ $ico }}</div>
      <div style="font-size:16px;font-weight:800;color:#1f2937" id="{{ $id }}">{{ is_numeric($val) ? number_format($val) : $val }}</div>
      <div style="font-size:10px;color:#9ca3af">{{ $lbl }}</div>
    </div>
    @endforeach
  </div>
</div>

{{-- ── Row 2: 4 chart cards ── --}}
<div style="display:grid;grid-template-columns:1fr 1.4fr 1.8fr 1fr;gap:12px">

  {{-- Case Type Donut --}}
  <div style="background:#fff;border-radius:14px;padding:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb">
    <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:10px">Calls by Case Type</div>
    <div style="height:140px"><canvas id="ovCaseTypeDonut"></canvas></div>
    <div style="margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:3px">
      @foreach($byPurpose->take(6) as $p)
      <div style="display:flex;align-items:center;gap:4px">
        <div style="width:8px;height:8px;border-radius:2px;background:#{{ ['f97316','7c3aed','2563eb','16a34a','0891b2','1e293b','e11d48','ca8a04'][($loop->index)%8] }};flex-shrink:0"></div>
        <span style="font-size:9px;color:#374151;truncate;overflow:hidden;white-space:nowrap;max-width:80px">{{ Str::limit($p->purpose_of_call,14) }}</span>
      </div>
      @endforeach
    </div>
  </div>

  {{-- Calls Per Month --}}
  <div style="background:#fff;border-radius:14px;padding:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb">
    <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:10px">Calls Per Month</div>
    <div style="height:180px"><canvas id="ovMonthLine"></canvas></div>
  </div>

  {{-- Referral By Service --}}
  <div style="background:#fff;border-radius:14px;padding:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb">
    <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:6px">Referral By Service</div>
    <div style="display:flex;gap:12px;margin-bottom:8px">
      <span style="font-size:10px;display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;background:#7c3aed;display:inline-block;border-radius:2px"></span>Referred Cases</span>
      <span style="font-size:10px;display:flex;align-items:center;gap:4px"><span style="width:10px;height:10px;background:#f97316;display:inline-block;border-radius:2px"></span>Confirmed Service Uptake</span>
    </div>
    <div style="height:160px"><canvas id="ovServiceBar"></canvas></div>
    <!-- Category breakdown -->
    <div id="ov-service-cats" style="margin-top:10px;display:flex;flex-direction:column;gap:4px"></div>
  </div>

  {{-- Bot / Program --}}
  <div style="background:#fff;border-radius:14px;padding:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);border:1px solid #e5e7eb;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
    <div style="position:relative;width:100px;height:100px;margin:0 auto 10px">
      <svg width="100" height="100" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="42" fill="none" stroke="#f3f4f6" stroke-width="8"/>
        <circle cx="50" cy="50" r="42" fill="none" stroke="#f97316" stroke-width="8"
          stroke-dasharray="{{ 2*3.14159*42*0.7 }} {{ 2*3.14159*42 }}"
          stroke-linecap="round" transform="rotate(-90 50 50)"/>
      </svg>
      <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
        <div>
          <div style="font-size:22px;font-weight:900;color:#1f2937" id="ov-urgent-badge">{{ number_format($urgentOpen) }}</div>
          <div style="font-size:9px;color:#9ca3af">Urgent</div>
        </div>
      </div>
    </div>
    <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:4px">uChat Bot Analytics</div>
    <div style="font-size:10px;color:#6b7280">Active Bot Users</div>
    <div style="font-size:20px;font-weight:900;color:#7c3aed;margin:4px 0">{{ number_format($uchat['total_bot_users'] ?? 0) }}</div>
    <div style="font-size:10px;color:#6b7280">New (30d): <strong>{{ $uchat['new_bot_users'] ?? 0 }}</strong></div>
  </div>

</div>{{-- /row 2 --}}
</div>{{-- /sec-overview --}}

{{-- ══════════════════════════════════ GEOGRAPHIC ══════════════════════════════════ --}}
<div id="sec-geographic" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Geographic Distribution</span>
    <div class="period-wrap">
      <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('geographic','day',this)">Today</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('geographic','week',this)">This Week</button>
      <div class="period-select-wrap"><select id="month-select-geographic" class="period-select {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onchange="onMonthSelect('geographic',this)"></select></div>
      <div class="period-select-wrap"><select id="year-select-geographic" class="period-select {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onchange="onYearSelect('geographic',this)"></select></div>
      @if($dateFrom)<button class="period-btn active-period" onclick="setPeriod('geographic','range',this)">{{ \Carbon\Carbon::parse($dateFrom.'-01')->format('M Y') }}{{ $dateTo && $dateTo!==$dateFrom ? ' – '.\Carbon\Carbon::parse($dateTo.'-01')->format('M Y') : '' }}</button>@endif
    </div>
  </div>
  <div class="g2">
    <div class="s-card"><h3>Calls by Province</h3><div class="ch220"><canvas id="geoBarChart"></canvas></div></div>
    <div class="s-card"><h3>Province Share</h3><div class="ch220"><canvas id="geoPieChart"></canvas></div></div>
  </div>
  <div class="s-card">
    <h3>Province Details</h3>
    <div class="tbl-wrap"><table>
      <thead><tr><th>#</th><th>Province</th><th>Interactions</th><th>% Share</th><th>Volume</th></tr></thead>
      <tbody id="geo-table"></tbody>
    </table></div>
  </div>
</div>

{{-- ══════════════════════════════════ DEMOGRAPHICS ══════════════════════════════════ --}}
<div id="sec-demographics" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Demographics</span>
    <div class="period-wrap">
      <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('demographics','day',this)">Today</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('demographics','week',this)">This Week</button>
      <div class="period-select-wrap"><select id="month-select-demographics" class="period-select {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onchange="onMonthSelect('demographics',this)"></select></div>
      <div class="period-select-wrap"><select id="year-select-demographics" class="period-select {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onchange="onYearSelect('demographics',this)"></select></div>
      @if($dateFrom)<button class="period-btn active-period" onclick="setPeriod('demographics','range',this)">{{ \Carbon\Carbon::parse($dateFrom.'-01')->format('M Y') }}{{ $dateTo && $dateTo!==$dateFrom ? ' – '.\Carbon\Carbon::parse($dateTo.'-01')->format('M Y') : '' }}</button>@endif
    </div>
  </div>
  <div class="g3">
    <div class="s-card"><h3>Gender</h3><div class="ch220"><canvas id="demGenderChart"></canvas></div></div>
    <div class="s-card"><h3>Age Groups</h3><div class="ch220"><canvas id="demAgeChart"></canvas></div></div>
    <div class="s-card"><h3>Marital Status</h3><div class="ch220"><canvas id="demMaritalChart"></canvas></div></div>
  </div>
  <div class="s-card">
    <h3>Key Population Groups</h3>
    <div id="dem-keypops"></div>
  </div>
  <div class="s-card" style="margin-top:14px">
    <h3>Call Group by Gender &amp; Age</h3>
    <div style="height:280px"><canvas id="demAgeGenderChart"></canvas></div>
  </div>
</div>

{{-- ══════════════════════════════════ SERVICES ══════════════════════════════════ --}}
<div id="sec-services" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Services &amp; Referrals</span>
    <div class="period-wrap">
      <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('services','day',this)">Today</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('services','week',this)">This Week</button>
      <div class="period-select-wrap"><select id="month-select-services" class="period-select {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onchange="onMonthSelect('services',this)"></select></div>
      <div class="period-select-wrap"><select id="year-select-services" class="period-select {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onchange="onYearSelect('services',this)"></select></div>
      @if($dateFrom)<button class="period-btn active-period" onclick="setPeriod('services','range',this)">{{ \Carbon\Carbon::parse($dateFrom.'-01')->format('M Y') }}{{ $dateTo && $dateTo!==$dateFrom ? ' – '.\Carbon\Carbon::parse($dateTo.'-01')->format('M Y') : '' }}</button>@endif
    </div>
  </div>
  <div class="s-card" style="margin-bottom:14px">
    <h3 style="text-align:center;font-size:15px;font-weight:700;margin-bottom:12px">Referral By Service</h3>
    <div style="height:280px"><canvas id="svcReferralByServiceChart"></canvas></div>
    <!-- Classification category breakdown table -->
    <div style="margin-top:16px;overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:11px">
        <thead>
          <tr>
            <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #f1f5f9;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px">Service</th>
            <th style="text-align:left;padding:6px 8px;border-bottom:2px solid #f1f5f9;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px">Classification Category</th>
            <th style="text-align:center;padding:6px 8px;border-bottom:2px solid #f1f5f9;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px">Cases</th>
            <th style="text-align:center;padding:6px 8px;border-bottom:2px solid #f1f5f9;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px">Uptake</th>
          </tr>
        </thead>
        <tbody id="svc-cat-tbody">
          <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:14px">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="svc-kpi-row">
    {{-- Services Requested card hidden --}}
    <div class="svc-kpi-card">
      <div class="svc-kpi-title">Referred Cases</div>
      <div class="svc-kpi-body">
        <div class="svc-kpi-icon">{!! $dashIcon('conference-call') !!}</div>
        <div class="svc-kpi-num" id="svc-kpi-referred">0</div>
      </div>
      <div class="svc-kpi-foot">
        <span class="svc-kpi-foot-lbl">Referral Completion</span>
        <span class="svc-kpi-rate" id="svc-kpi-ref-rate">0%</span>
      </div>
    </div>
    <div class="svc-kpi-card">
      <div class="svc-kpi-title">Confirmed Uptake</div>
      <div class="svc-kpi-body">
        <div class="svc-kpi-icon">{!! $dashIcon('checked') !!}</div>
        <div class="svc-kpi-num" id="svc-kpi-uptake">0</div>
      </div>
      <div class="svc-kpi-foot">
        <span class="svc-kpi-foot-lbl">Completion Rate</span>
        <span class="svc-kpi-rate" id="svc-kpi-uptake-rate">0%</span>
      </div>
    </div>
  </div>
  <div class="g2">
    {{-- <div class="s-card"><h3>Top Services Requested</h3><div class="ch220"><canvas id="svcServiceChart"></canvas></div></div> --}}
    <div class="s-card"><h3>Top Referral Destinations</h3><div class="ch220"><canvas id="svcReferralChart"></canvas></div></div>
    <div class="s-card"><h3>Call Group by Gender &amp; Age</h3><div style="height:280px"><canvas id="svcKeyPopsChart"></canvas></div></div>
  </div>
  <div class="s-card">
    <h3>Services Detail</h3>
    <div id="svc-bars"></div>
  </div>

  {{-- ── Psycho-social Support Breakdown ── --}}
  <div class="s-card" style="margin-top:14px" id="psychosocial-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <h3 style="margin:0">Psycho-social Support Breakdown</h3>
      <span style="font-size:11px;color:#94a3b8">Cases under "Psycho-social support" service</span>
    </div>
    <!-- KPI pills -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px" id="psychosocial-kpis">
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px;text-align:center">
        <div style="font-size:10px;font-weight:700;color:#3b82f6;text-transform:uppercase;margin-bottom:6px">Awareness Raising</div>
        <div style="font-size:28px;font-weight:900;color:#1d4ed8;line-height:1" id="psycho-awareness-total">—</div>
        <div style="font-size:10px;color:#64748b;margin-top:4px">Uptake: <span id="psycho-awareness-uptake" style="font-weight:700">—</span></div>
      </div>
      <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:12px;padding:14px;text-align:center">
        <div style="font-size:10px;font-weight:700;color:#d97706;text-transform:uppercase;margin-bottom:6px">Helpline Marketing</div>
        <div style="font-size:28px;font-weight:900;color:#b45309;line-height:1" id="psycho-marketing-total">—</div>
        <div style="font-size:10px;color:#64748b;margin-top:4px">Uptake: <span id="psycho-marketing-uptake" style="font-weight:700">—</span></div>
      </div>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px;text-align:center">
        <div style="font-size:10px;font-weight:700;color:#16a34a;text-transform:uppercase;margin-bottom:6px">Counselling</div>
        <div style="font-size:28px;font-weight:900;color:#15803d;line-height:1" id="psycho-counselling-total">—</div>
        <div style="font-size:10px;color:#64748b;margin-top:4px">Uptake: <span id="psycho-counselling-uptake" style="font-weight:700">—</span></div>
      </div>
    </div>
    <!-- Bar chart -->
    <div style="height:140px"><canvas id="psychosocialChart"></canvas></div>
    <div id="psychosocial-empty" style="display:none;text-align:center;padding:20px;font-size:12px;color:#94a3b8">
      No Psycho-social support data for this period
    </div>
  </div>
</div>

{{-- ══════════════════════════════════ CALL DETAILS ══════════════════════════════════ --}}
<div id="sec-calls" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Call Activity</span>
    <div class="period-wrap">
      <button class="period-btn {{ $callDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('calls','day',this)">Today</button>
      <button class="period-btn {{ $callDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('calls','week',this)">This Week</button>
      <div class="period-select-wrap"><select id="month-select-calls" class="period-select {{ $callDefaultPeriod==='month'?'active-period':'' }}" onchange="onMonthSelect('calls',this)"></select></div>
      <div class="period-select-wrap"><select id="year-select-calls" class="period-select {{ $callDefaultPeriod==='year'?'active-period':'' }}" onchange="onYearSelect('calls',this)"></select></div>
      @if($dateFrom)<button class="period-btn active-period" onclick="setPeriod('calls','range',this)">{{ \Carbon\Carbon::parse($dateFrom.'-01')->format('M Y') }}{{ $dateTo && $dateTo!==$dateFrom ? ' – '.\Carbon\Carbon::parse($dateTo.'-01')->format('M Y') : '' }}</button>@endif
    </div>
  </div>
  <div class="kpi-row">
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('phone') !!}</div><div class="kpi-val" id="c-total">0</div><div class="kpi-lbl">Total Calls</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('incoming-call') !!}</div><div class="kpi-val" id="c-inbound">0</div><div class="kpi-lbl">Inbound</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('outgoing-call') !!}</div><div class="kpi-val" id="c-outbound">0</div><div class="kpi-lbl">Outbound</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('high-priority', '#ef4444') !!}</div><div class="kpi-val" id="c-missed">{{ number_format($urgentOpen) }}</div><div class="kpi-lbl">Urgent Cases</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('ok', '#22c55e') !!}</div><div class="kpi-val" id="c-answered">0</div><div class="kpi-lbl">Answered</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('timer') !!}</div><div class="kpi-val" id="c-avgdur">0s</div><div class="kpi-lbl">Avg Duration</div></div>
  </div>
  <div class="s-card" style="margin-bottom:14px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <h3 style="margin:0" id="calls-trend-lbl">Calls by Hour — Today</h3>
    </div>
    <div class="ch180"><canvas id="callTrendChart"></canvas></div>
  </div>
  <div class="s-card">
    <h3>Purpose of Call (Top 10 — All Time)</h3>
    @php $maxPurpAll=$byPurpose->first()?->cnt??1; @endphp
    @foreach ($byPurpose as $purp)
      @php $pct=$total?round($purp->cnt/$total*100,1):0; $bar=$maxPurpAll?round($purp->cnt/$maxPurpAll*100):0; @endphp
      <div class="pb">
        <div class="pb-hdr"><span class="pb-lbl">{{ $purp->purpose_of_call }}</span><span class="pb-val">{{ number_format($purp->cnt) }} ({{ $pct }}%)</span></div>
        <div class="pb-track"><div class="pb-fill" style="width:{{ $bar }}%;background:#3b82f6"></div></div>
      </div>
    @endforeach
    @if($byPurpose->isEmpty())<p style="font-size:12px;color:#94a3b8;text-align:center;padding:14px 0">No purpose data yet</p>@endif
  </div>

  {{-- ── Tickets by Agent (period-filtered) ── --}}
  <div class="s-card" style="margin-top:14px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <h3 style="margin:0" id="agent-tickets-lbl">Tickets by Agent — Today</h3>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Agent / Extension</th>
            <th style="text-align:center">Total</th>
            <th style="text-align:center">Open</th>
            <th style="text-align:center">In Progress</th>
            <th style="text-align:center">Resolved</th>
            <th style="min-width:120px">Volume</th>
          </tr>
        </thead>
        <tbody id="agent-tickets-tbody">
          <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:16px">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════ TRENDS ══════════════════════════════════ --}}
<div id="sec-trends" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Trends</span>
    <div class="period-wrap">
      <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('trends','day',this)">Today</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('trends','week',this)">This Week</button>
      <div class="period-select-wrap"><select id="month-select-trends" class="period-select {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onchange="onMonthSelect('trends',this)"></select></div>
      <div class="period-select-wrap"><select id="year-select-trends" class="period-select {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onchange="onYearSelect('trends',this)"></select></div>
      @if($dateFrom)<button class="period-btn active-period" onclick="setPeriod('trends','range',this)">{{ \Carbon\Carbon::parse($dateFrom.'-01')->format('M Y') }}{{ $dateTo && $dateTo!==$dateFrom ? ' – '.\Carbon\Carbon::parse($dateTo.'-01')->format('M Y') : '' }}</button>@endif
    </div>
  </div>
  <div class="s-card" style="margin-bottom:14px">
    <h3 id="trend-chart-lbl">Interactions by Hour — Today</h3>
    <div class="ch180"><canvas id="trendMainChart"></canvas></div>
  </div>
  <!-- 12-month always visible below -->
  <div class="s-card" style="margin-bottom:14px">
    <h3>12-Month Overview</h3>
    <div class="ch120"><canvas id="trendAllChart"></canvas></div>
  </div>
  <div class="s-card">
    <h3>Monthly Breakdown</h3>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Month</th><th>Interactions</th><th>% of 12-month total</th><th>Volume</th></tr></thead>
      <tbody>
        @foreach ($monthArr as $ym => $cnt)
          @php $label=\Carbon\Carbon::createFromFormat('Y-m',$ym)->format('M Y');$pct=$monthSum?round($cnt/$monthSum*100,1):0;$bar=$maxMonth?round($cnt/$maxMonth*100):0; @endphp
          <tr>
            <td><strong style="color:#0f172a">{{ $label }}</strong></td>
            <td>{{ number_format($cnt) }}</td><td>{{ $pct }}%</td>
            <td style="min-width:100px"><div class="pb-track"><div class="pb-fill" style="width:{{ $bar }}%;background:#3b82f6"></div></div></td>
          </tr>
        @endforeach
      </tbody>
    </table></div>
  </div>
</div>

{{-- ══════════════════════════════ SOCIAL LISTENING MATRIX ══════════════════════════════ --}}
<div id="sec-social" class="section" style="display:none">

  <!-- Header -->
  <div class="sec-hdr">
    <div>
      <span class="sec-title">Social Listening Matrix</span>
      <span style="font-size:11px;color:#94a3b8;margin-left:8px">Listening · Understanding · Responding · Protecting</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <div style="background:#1e3a5f;color:#fff;border-radius:12px;padding:6px 14px;font-size:12px;font-weight:700">
        <span id="slm-total">0</span> Interactions
      </div>
      <div class="period-wrap">
        <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('social','day',this)">Today</button>
        <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('social','week',this)">This Week</button>
        <div class="period-select-wrap"><select id="month-select-social" class="period-select {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onchange="onMonthSelect('social',this)"></select></div>
        <div class="period-select-wrap"><select id="year-select-social" class="period-select {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onchange="onYearSelect('social',this)"></select></div>
        @if($dateFrom)<button class="period-btn active-period" onclick="setPeriod('social','range',this)">{{ \Carbon\Carbon::parse($dateFrom.'-01')->format('M Y') }}{{ $dateTo && $dateTo!==$dateFrom ? ' – '.\Carbon\Carbon::parse($dateTo.'-01')->format('M Y') : '' }}</button>@endif
      </div>
    </div>
  </div>

  <!-- 5-column main matrix -->
  <div class="glass slm-5col" style="margin-bottom:14px">

    <!-- Panel 1: Listening Sources -->
    <div class="slm-panel">
      <div class="slm-ptitle">1. Listening Sources</div>
      <div class="slm-src-sub">📱 Digital Channels</div>
      <div id="slm-digital-sources"><p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px">Loading…</p></div>
      <div class="slm-src-sub" style="background:#0d9488">👥 Community</div>
      <div id="slm-community-sources"><p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px">Loading…</p></div>
      <div class="slm-total-row" id="slm-src-total"><span>TOTAL</span><span>0</span></div>
    </div>

    <!-- Panel 2: Issues / Topics -->
    <div class="slm-panel">
      <div class="slm-ptitle">2. Issues / Topics</div>
      <div class="slm-cat-title" style="background:#e8f4ed;color:#15803d">🏥 Health &amp; Wellbeing</div>
      <div id="slm-issues-health"></div>
      <div class="slm-cat-title" style="background:#eef3fa;color:#1e40af">🛡️ Protection &amp; Safety</div>
      <div id="slm-issues-protection"></div>
      <div class="slm-cat-title" style="background:#fff7ed;color:#c2410c">🏘️ Social &amp; Community</div>
      <div id="slm-issues-social"></div>
      <div class="slm-total-row" id="slm-issues-total"><span>TOTAL ISSUES</span><span>0</span></div>
    </div>

    <!-- Panel 3: Risk Classification -->
    <div class="slm-panel">
      <div class="slm-ptitle">3. Risk Classification</div>
      <div id="slm-risk">
        <div class="slm-risk-row">
          <div class="slm-risk-hdr">
            <span style="width:10px;height:10px;border-radius:50%;background:#7f1d1d;display:inline-block;flex-shrink:0"></span>
            <strong style="color:#7f1d1d;font-size:11px">EMERGENCY</strong>
            <span style="margin-left:auto;font-size:18px;font-weight:800;color:#7f1d1d" id="slm-r-emerg">0</span>
          </div>
          <div style="font-size:9px;color:#94a3b8;margin-bottom:3px">Immediate danger · Life-threatening</div>
          <div style="background:#fee2e2;border-radius:2px;height:5px"><div id="slm-rb-emerg" style="height:5px;border-radius:2px;background:#7f1d1d;width:0%"></div></div>
          <div style="font-size:9px;color:#7f1d1d;font-weight:600;margin-top:2px" id="slm-rp-emerg">0%</div>
        </div>
        <div class="slm-risk-row">
          <div class="slm-risk-hdr">
            <span style="width:10px;height:10px;border-radius:50%;background:#dc2626;display:inline-block;flex-shrink:0"></span>
            <strong style="color:#dc2626;font-size:11px">HIGH</strong>
            <span style="margin-left:auto;font-size:18px;font-weight:800;color:#dc2626" id="slm-r-high">0</span>
          </div>
          <div style="font-size:9px;color:#94a3b8;margin-bottom:3px">Serious risk · Potential harm</div>
          <div style="background:#fee2e2;border-radius:2px;height:5px"><div id="slm-rb-high" style="height:5px;border-radius:2px;background:#dc2626;width:0%"></div></div>
          <div style="font-size:9px;color:#dc2626;font-weight:600;margin-top:2px" id="slm-rp-high">0%</div>
        </div>
        <div class="slm-risk-row">
          <div class="slm-risk-hdr">
            <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block;flex-shrink:0"></span>
            <strong style="color:#a16207;font-size:11px">MEDIUM</strong>
            <span style="margin-left:auto;font-size:18px;font-weight:800;color:#a16207" id="slm-r-med">0</span>
          </div>
          <div style="font-size:9px;color:#94a3b8;margin-bottom:3px">Some risk · Needs follow-up</div>
          <div style="background:#fef9c3;border-radius:2px;height:5px"><div id="slm-rb-med" style="height:5px;border-radius:2px;background:#f59e0b;width:0%"></div></div>
          <div style="font-size:9px;color:#a16207;font-weight:600;margin-top:2px" id="slm-rp-med">0%</div>
        </div>
        <div class="slm-risk-row">
          <div class="slm-risk-hdr">
            <span style="width:10px;height:10px;border-radius:50%;background:#22c55e;display:inline-block;flex-shrink:0"></span>
            <strong style="color:#15803d;font-size:11px">LOW</strong>
            <span style="margin-left:auto;font-size:18px;font-weight:800;color:#15803d" id="slm-r-low">0</span>
          </div>
          <div style="font-size:9px;color:#94a3b8;margin-bottom:3px">General concern · No immediate threat</div>
          <div style="background:#dcfce7;border-radius:2px;height:5px"><div id="slm-rb-low" style="height:5px;border-radius:2px;background:#22c55e;width:0%"></div></div>
          <div style="font-size:9px;color:#15803d;font-weight:600;margin-top:2px" id="slm-rp-low">0%</div>
        </div>
        <div class="slm-total-row" style="margin-top:8px" id="slm-risk-total"><span>TOTAL CLASSIFIED</span><span>0</span></div>
      </div>
    </div>

    <!-- Panel 4: Response Actions -->
    <div class="slm-panel">
      <div class="slm-ptitle">4. Response Actions</div>
      <div id="slm-actions"><p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px">Loading…</p></div>
      <div class="slm-total-row" id="slm-actions-total" style="margin-top:6px"><span>TOTAL ACTIONS</span><span>0</span></div>
    </div>

    <!-- Panel 5: Referral Pathways -->
    <div class="slm-panel" style="border-right:none">
      <div class="slm-ptitle">5. Referral Pathways</div>
      <div id="slm-referrals"><p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px">Loading…</p></div>
      <div class="slm-total-row" id="slm-ref-total" style="margin-top:6px"><span>TOTAL REFERRALS</span><span>0</span></div>
    </div>

  </div><!-- end 5col -->

  <!-- Section 6: Workflow -->
  <div style="background:#0f172a;border-radius:20px;padding:12px 14px;margin-bottom:14px">
    <div style="text-align:center;color:#fff;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">
      6. Social Listening Workflow
    </div>
    <div class="slm-wf-steps">
      <div class="slm-wf-step">
        <div class="slm-wf-icon">👂</div>
        <div class="slm-wf-name">Listen</div>
        <div class="slm-wf-desc">Collect across all channels</div>
        <div class="slm-wf-stat" id="slm-wf-total">0</div>
        <div class="slm-wf-slbl">Interactions</div>
      </div>
      <div class="slm-wf-step">
        <div class="slm-wf-icon">🔍</div>
        <div class="slm-wf-name">Analyze</div>
        <div class="slm-wf-desc">Identify issues &amp; risks</div>
        <div class="slm-wf-stat" id="slm-wf-issues">0</div>
        <div class="slm-wf-slbl">Issues Captured</div>
      </div>
      <div class="slm-wf-step">
        <div class="slm-wf-icon">🏷️</div>
        <div class="slm-wf-name">Classify</div>
        <div class="slm-wf-desc">Assign risk level</div>
        <div class="slm-wf-stat">4</div>
        <div class="slm-wf-slbl">Risk Levels</div>
      </div>
      <div class="slm-wf-step">
        <div class="slm-wf-icon">⚡</div>
        <div class="slm-wf-name">Respond</div>
        <div class="slm-wf-desc">Take appropriate actions</div>
        <div class="slm-wf-stat" id="slm-wf-actions">0</div>
        <div class="slm-wf-slbl">Actions Taken</div>
      </div>
      <div class="slm-wf-step">
        <div class="slm-wf-icon">🔄</div>
        <div class="slm-wf-name">Follow-Up</div>
        <div class="slm-wf-desc">Monitor &amp; continued support</div>
        <div class="slm-wf-stat" id="slm-wf-uptake">0</div>
        <div class="slm-wf-slbl">Uptake Confirmed</div>
      </div>
      <div class="slm-wf-step">
        <div class="slm-wf-icon">📊</div>
        <div class="slm-wf-name">Report</div>
        <div class="slm-wf-desc">Generate insights</div>
        <div class="slm-wf-stat" id="slm-wf-referrals">0</div>
        <div class="slm-wf-slbl">Referrals Made</div>
      </div>
      <div class="slm-wf-step">
        <div class="slm-wf-icon">📈</div>
        <div class="slm-wf-name">Improve</div>
        <div class="slm-wf-desc">Strengthen programs</div>
        <div class="slm-wf-stat">100%</div>
        <div class="slm-wf-slbl">Continuous</div>
      </div>
    </div>
    <div style="text-align:center;padding-bottom:4px;font-size:10px;color:#334155;letter-spacing:1px">↺ CONTINUOUS IMPROVEMENT LOOP ↺</div>
  </div>

  <!-- Sections 7 + 8: Trends & Geographic -->
  <div class="g2" style="margin-bottom:14px">
    <div class="glass">
      <div class="card-hdr">
        <span class="card-title">7. Community Trends</span>
        <span style="font-size:10px;color:#94a3b8">vs previous period</span>
      </div>
      <div class="tbl-wrap">
        <table style="font-size:11px">
          <thead>
            <tr><th>#</th><th>Issue / Topic</th><th>Cases</th><th>Trend</th></tr>
          </thead>
          <tbody id="slm-trends-tbody">
            <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:14px">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="glass">
      <div class="card-hdr">
        <span class="card-title">8. Geographic Intelligence</span>
        <span style="font-size:10px;color:#94a3b8">by province</span>
      </div>
      <div class="tbl-wrap">
        <table style="font-size:11px">
          <thead>
            <tr><th>#</th><th>Province</th><th>Cases</th><th>%</th><th>Volume</th></tr>
          </thead>
          <tbody id="slm-geo-tbody">
            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:14px">Loading…</td></tr>
          </tbody>
        </table>
      </div>
      <div class="slm-total-row" id="slm-geo-total" style="margin-top:8px"><span>TOTAL</span><span>0</span></div>
    </div>
  </div>

  <!-- Section 9: Case Recording -->
  <div class="glass" style="margin-bottom:14px">
    <div class="card-hdr">
      <span class="card-title">9. Case Recording Matrix</span>
      <span style="font-size:10px;color:#94a3b8">Last 8 interactions</span>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th><th>Case ID</th><th>Channel</th><th>Issue</th><th>Risk</th>
            <th>Referred To</th><th>Status</th><th>Province</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($recentTickets as $rt)
          @php
            $rPriority = strtolower($rt->priority ?? '');
            $rImm = (bool)$rt->immediate_action_required;
            $rPillClass = $rImm ? 'rpill-emerg' : ($rPriority === 'high' ? 'rpill-high' : ($rPriority === 'medium' ? 'rpill-med' : 'rpill-low'));
            $rPillLabel = $rImm ? 'EMERGENCY' : strtoupper($rPriority ?: 'LOW');
          @endphp
          <tr>
            <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($rt->created_at)->format('d/m/Y') }}</td>
            <td style="color:#94a3b8;font-size:10px">#{{ $rt->id }}</td>
            <td>{{ $rt->mode_of_communication ?? '—' }}</td>
            <td style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $rt->purpose_of_call ?? '—' }}</td>
            <td><span class="{{ $rPillClass }}">{{ $rPillLabel }}</span></td>
            <td style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $rt->referred_to ?? '—' }}</td>
            <td><span style="font-weight:600;color:{{ $rt->status==='closed'?'#16a34a':($rt->status==='open'?'#3b82f6':'#f59e0b') }}">{{ ucfirst($rt->status ?? '—') }}</span></td>
            <td>{{ $rt->province ?? '—' }}</td>
          </tr>
          @empty
          <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:16px">No recent interactions recorded</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Section 10: Urgent Escalation -->
  <div style="background:#fff5f5;border:2px solid #dc2626;border-radius:20px;padding:14px 16px;margin-bottom:14px">
    <div style="color:#dc2626;font-weight:800;font-size:14px;margin-bottom:10px;display:flex;align-items:center;gap:8px">
      ⚠️ 10. Urgent Escalation Alerts
      <span style="font-size:11px;font-weight:400;color:#6b7280">(Immediate Action Required)</span>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <div class="slm-urg-card">
        <div class="slm-urg-icon">⚠️</div>
        <div class="slm-urg-lbl">Immediate Action<br>Required</div>
        <div class="slm-urg-num" id="slm-urg-imm">0</div>
      </div>
      <div class="slm-urg-card">
        <div class="slm-urg-icon">📵</div>
        <div class="slm-urg-lbl">Missed Calls<br>(Unanswered)</div>
        <div class="slm-urg-num" id="slm-urg-missed">0</div>
      </div>
      <div class="slm-urg-card">
        <div class="slm-urg-icon">🔴</div>
        <div class="slm-urg-lbl">High Priority<br>Cases</div>
        <div class="slm-urg-num" id="slm-urg-high">0</div>
      </div>
      <div class="slm-urg-card">
        <div class="slm-urg-icon">🔄</div>
        <div class="slm-urg-lbl">Repeat<br>Callers</div>
        <div class="slm-urg-num" id="slm-urg-repeat">0</div>
      </div>
      <div class="slm-urg-card">
        <div class="slm-urg-icon">📋</div>
        <div class="slm-urg-lbl">Valid<br>Interactions</div>
        <div class="slm-urg-num" id="slm-urg-valid">0</div>
      </div>
      <div class="slm-urg-card">
        <div class="slm-urg-icon">✅</div>
        <div class="slm-urg-lbl">Uptake<br>Confirmed</div>
        <div class="slm-urg-num" id="slm-urg-uptake">0</div>
      </div>
    </div>
    <div style="text-align:center;margin-top:10px;font-weight:700;font-size:12px;color:#dc2626">
      TOTAL URGENT ALERTS: <span id="slm-urg-total">0</span>
    </div>
  </div>

  <!-- Section 11: Expected Outcomes -->
  <div class="glass" style="margin-bottom:14px">
    <div class="card-hdr">
      <span class="card-title">11. Expected Outcomes</span>
      <span style="font-size:10px;color:#94a3b8">What we aim to achieve</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
      @foreach([
        ['✅','Early detection of risks and needs'],
        ['✅','Timely referrals and emergency response'],
        ['✅','Improved service delivery and coordination'],
        ['✅','Stronger community trust and engagement'],
        ['✅','Data-driven decision making and reporting'],
        ['✅','Safer communities. Healthier youth. Brighter futures.'],
      ] as [$icon,$text])
      <div style="display:flex;align-items:flex-start;gap:7px;padding:8px 10px;background:#f8fafc;border-radius:10px">
        <span style="color:#16a34a;font-size:13px;flex-shrink:0">{{ $icon }}</span>
        <span style="font-size:11px;color:#374151">{{ $text }}</span>
      </div>
      @endforeach
    </div>
    <div style="margin-top:12px;padding-top:10px;border-top:2px solid #0f172a;text-align:center;font-weight:800;font-size:14px;color:#0f172a;letter-spacing:.5px">
      Every Voice Matters. Every Case Counts.
    </div>
  </div>

</div>{{-- end sec-social --}}

{{-- ══════════════════════════════════ CALL TARGETS ══════════════════════════════════ --}}
<div id="sec-targets" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Call Targets</span>
    <span style="font-size:11px;color:#94a3b8">Agent performance against daily targets</span>
  </div>

  <!-- KPI row -->
  <div class="kpi-row">
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('goal') !!}</div><div class="kpi-val" id="tgt-period-target">—</div><div class="kpi-lbl">Period Target</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('phone') !!}</div><div class="kpi-val" id="tgt-period-calls">—</div><div class="kpi-lbl">Calls Made</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('combo-chart') !!}</div><div class="kpi-val" id="tgt-coverage">—</div><div class="kpi-lbl">Coverage</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('team') !!}</div><div class="kpi-val" id="tgt-active-agents">—</div><div class="kpi-lbl">Active Agents</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('calendar') !!}</div><div class="kpi-val" id="tgt-today-required">—</div><div class="kpi-lbl">Today Required</div></div>
    <div class="kpi"><div class="kpi-icon">{!! $dashIcon('checked', '#22c55e') !!}</div><div class="kpi-val" id="tgt-today-calls">—</div><div class="kpi-lbl">Today's Calls</div></div>
  </div>

  <!-- Agent performance chart -->
  <div class="s-card" style="margin-bottom:14px">
    <h3>Target vs Calls Made — Per Agent</h3>
    <div style="height:260px"><canvas id="tgtAgentChart"></canvas></div>
  </div>

  <!-- Per-agent cards -->
  <div id="tgt-agent-cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
    <p style="color:#94a3b8;text-align:center;padding:24px;grid-column:1/-1">Loading…</p>
  </div>
</div>

{{-- ══════════════════════════════════ BOT ANALYTICS ══════════════════════════════ --}}
<div id="sec-bot" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Bot Analytics — uChat</span>
    <span style="font-size:11px;color:#94a3b8">Last 30 days &middot; <span id="bot-fetched">{{ $uchat['fetched_at'] ?? 'N/A' }}</span></span>
  </div>

  @if(!empty($uchat['error']))
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:10px 16px;font-size:12px;color:#b91c1c;margin-bottom:14px">
    ⚠ uChat API unavailable: {{ $uchat['error'] }}
  </div>
  @endif

  <!-- KPI cards -->
  <div class="kpi-row" style="margin-bottom:16px">
    <div class="kpi">
      <div class="kpi-icon">{!! $dashIcon('bot') !!}</div>
      <div class="kpi-val" id="bot-total">{{ number_format($uchat['total_bot_users'] ?? 0) }}</div>
      <div class="kpi-lbl">Total Bot Users</div>
    </div>
    <div class="kpi">
      <div class="kpi-icon">{!! $dashIcon('add-user-male') !!}</div>
      <div class="kpi-val" id="bot-new">{{ number_format($uchat['new_bot_users'] ?? 0) }}</div>
      <div class="kpi-lbl">New Bot Users (30d)</div>
    </div>
    <div class="kpi">
      <div class="kpi-icon">{!! $dashIcon('flash-on', '#f59e0b') !!}</div>
      <div class="kpi-val" id="bot-active">{{ number_format($uchat['active_today'] ?? 0) }}</div>
      <div class="kpi-lbl">Active (Last 24h)</div>
    </div>
    <div class="kpi">
      <div class="kpi-icon">{!! $dashIcon('bar-chart') !!}</div>
      <div class="kpi-val" id="bot-avg">{{ $uchat['new_bot_users'] > 0 ? round(($uchat['new_bot_users'] ?? 0) / 30, 1) : '—' }}</div>
      <div class="kpi-lbl">Avg New / Day (30d)</div>
    </div>
  </div>

  <!-- Channel breakdown -->
  <div class="s-card" style="margin-bottom:14px">
    <h3>New Bot Users By Channel (Last 30 Days)</h3>
    <div id="bot-channels" style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px">
      @php
        $channelIcons = [
          'whatsapp_cloud' => '💬',
          'whatsapp'       => '💬',
          'facebook'       => '📘',
          'instagram'      => '📷',
          'telegram'       => '✈️',
          'tiktok'         => '🎵',
          'web'            => '🌐',
          'slack'          => '💼',
          'wechat'         => '🟢',
          'unknown'        => '❓',
        ];
        $channelTotal = array_sum($uchat['channel_counts'] ?? []);
      @endphp
      @forelse($uchat['channel_counts'] ?? [] as $ch => $cnt)
        @php $pct = $channelTotal > 0 ? round($cnt / $channelTotal * 100) : 0; @endphp
        <div style="min-width:160px;flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:12px;font-weight:600;color:#374151;text-transform:capitalize">
              {{ $channelIcons[$ch] ?? '📡' }} {{ str_replace('_', ' ', $ch) }}
            </span>
            <span style="font-size:16px;font-weight:800;color:#0f172a">{{ number_format($cnt) }}</span>
          </div>
          <div style="height:4px;background:#e2e8f0;border-radius:4px;overflow:hidden">
            <div style="height:100%;width:{{ $pct }}%;background:#3b82f6;border-radius:4px"></div>
          </div>
          <div style="font-size:10px;color:#94a3b8;margin-top:3px">{{ $pct }}% of new users</div>
        </div>
      @empty
        <p style="color:#94a3b8;font-size:13px">No new subscribers in the last 30 days.</p>
      @endforelse
    </div>
  </div>

  <!-- Historical trend chart -->
  <div class="s-card" style="margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h3 style="margin:0">Growth Over Time</h3>
      <span style="font-size:11px;color:#94a3b8">Daily snapshots stored in database</span>
    </div>
    <div style="height:220px"><canvas id="botTrendChart"></canvas></div>
  </div>

  <!-- Total users bar -->
  <div class="s-card">
    <h3>Total Bot Users Overview</h3>
    <div style="display:flex;align-items:center;gap:20px;margin-top:8px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        @php $newPct = ($uchat['total_bot_users'] ?? 0) > 0 ? round(($uchat['new_bot_users'] ?? 0) / $uchat['total_bot_users'] * 100) : 0; @endphp
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;margin-bottom:4px">
          <span>New this period</span><span>{{ $newPct }}%</span>
        </div>
        <div style="height:8px;background:#e2e8f0;border-radius:8px;overflow:hidden">
          <div style="height:100%;width:{{ $newPct }}%;background:linear-gradient(90deg,#3b82f6,#6366f1);border-radius:8px"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#94a3b8;margin-top:4px">
          <span>{{ number_format($uchat['new_bot_users'] ?? 0) }} new</span>
          <span>{{ number_format($uchat['total_bot_users'] ?? 0) }} total</span>
        </div>
      </div>
      <div style="flex:1;min-width:200px">
        @php $activePct = ($uchat['total_bot_users'] ?? 0) > 0 ? round(($uchat['active_today'] ?? 0) / $uchat['total_bot_users'] * 100) : 0; @endphp
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;margin-bottom:4px">
          <span>Active last 24h</span><span>{{ $activePct }}%</span>
        </div>
        <div style="height:8px;background:#e2e8f0;border-radius:8px;overflow:hidden">
          <div style="height:100%;width:{{ $activePct }}%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:8px"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#94a3b8;margin-top:4px">
          <span>{{ number_format($uchat['active_today'] ?? 0) }} active</span>
          <span>{{ number_format($uchat['total_bot_users'] ?? 0) }} total</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="footer">Helpline Analytics &middot; Auto-refreshes every minute &middot; {{ now()->format('d M Y') }}</div>
</main>
</div>

<script>
// ── PHP data (let — reassigned on each background refresh) ────────────────────
let uchatData      = @json($uchat);
let urgentOpen     = {{ (int)$urgentOpen }};
let periodData     = @json($periodData);
let callStats      = @json($callStats);
let months12       = @json($months);
let prevPeriodData = @json($prevPeriodData);
let callTargetRows = @json($callTargetRows);
let activeService  = @json($serviceFilter);  // '' means all services
let allServices    = @json($allServices);
let activeProject       = @json($projectFilter);  // '' means all projects
const DISPLAY_OFFSET        = @json($displayTotalOffset);   // subtract from displayed total
const DISPLAY_UPTAKE_OFFSET = @json($displayUptakeOffset);  // subtract from displayed uptake
let allProjects    = @json($allProjects);

const CAT_LABELS = {
  household_social:     'Household & Social',
  hiv:                  'HIV Services',
  pep:                  'PEP',
  prep:                 'PrEP',
  srhr:                 'Sexual & Reproductive Health',
  general_health:       'General Health',
  mental_health:        'Mental Health & MHPSS',
  substance_use:        'Substance Use',
  family_relationships: 'Family & Relationships',
  gbv:                  'GBV',
  child_protection:     'Child Protection',
  legal:                'Legal & Protection',
  education:            'Education',
  livelihood:           'Livelihoods',
  youth_empowerment:    'Youth Empowerment',
  case_resolution:      'Case Resolution',
};

// Colour per category for badges
const CAT_COLORS = {
  household_social:'#0ea5e9', hiv:'#dc2626', pep:'#b91c1c', prep:'#f97316',
  srhr:'#db2777', general_health:'#0d9488', mental_health:'#7c3aed',
  substance_use:'#92400e', family_relationships:'#2563eb', gbv:'#dc2626',
  child_protection:'#16a34a', legal:'#374151', education:'#ca8a04',
  livelihood:'#059669', youth_empowerment:'#6366f1', case_resolution:'#64748b',
};

// ── Chart registry ─────────────────────────────────────────────────────────────
const CC = {};

// ── Global chart defaults ──────────────────────────────────────────────────────
Chart.defaults.color        = '#94a3b8';
Chart.defaults.borderColor  = '#f1f5f9';

// ── Colour palette ─────────────────────────────────────────────────────────────
const PAL = ['#3b82f6','#fbbf24','#4ade80','#f87171','#8b5cf6','#0d9488','#f59e0b','#06b6d4','#ec4899','#a3e635'];
const priorityColors = { low:'#4ade80', medium:'#3b82f6', high:'#f59e0b', urgent:'#f87171' };
const statusColors   = { open:'#60a5fa', in_progress:'#fbbf24', closed:'#4ade80', resolved:'#34d399' };
const genderMap      = { male:'Male', female:'Female', other:'Other', prefer_not_to_say:'Not say' };

// ── Core helpers ───────────────────────────────────────────────────────────────
function fmt(n){ return Number(n).toLocaleString(); }

function rc(id, type, labels, data, opts = {}) {
  if (CC[id]) { CC[id].destroy(); delete CC[id]; }
  const ctx = document.getElementById(id);
  if (!ctx) return;
  const isBar  = type === 'bar';
  const isLine = type === 'line';
  CC[id] = new Chart(ctx, {
    type,
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: opts.colors  ? opts.colors
          : (type === 'pie' || type === 'doughnut') ? PAL.slice(0, labels.length)
          : (opts.accent  ? labels.map((_, i) => i === labels.length - 1 ? opts.accent : opts.muted ?? '#dbeafe')
          : opts.single   ? Array(labels.length).fill(opts.single)
          : PAL.slice(0, labels.length)),
        borderColor  : isLine ? (opts.accent ?? '#3b82f6') : 'transparent',
        borderWidth  : isLine ? 2 : 0,
        borderRadius : isBar ? 6 : 0,
        fill         : opts.fill ?? false,
        tension      : 0.4,
        pointRadius  : isLine ? 3 : 0,
        hoverBackgroundColor: opts.single ? opts.single : undefined,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: type === 'doughnut' ? '72%' : undefined,
      plugins: {
        legend: { display: opts.legend ?? (type === 'pie' || type === 'doughnut') },
        tooltip: {
          backgroundColor:'rgba(255,255,255,.98)',borderColor:'#e2e8f0',
          titleColor:'#0f172a',bodyColor:'#475569',borderWidth:1,
          callbacks: { label: c => ` ${c.label ?? ''}: ${Number(c.raw).toLocaleString()}` },
        },
      },
      scales: (isBar || isLine) ? {
        y: { beginAtZero:true, ticks:{precision:0}, grid:{color:'#f1f5f9'} },
        x: { grid:{display:false}, ticks:{maxRotation:0, font:{size:10}} },
      } : {},
      ...(opts.indexAxis ? { indexAxis: opts.indexAxis } : {}),
    },
  });
}

function trendLabels(period, keys) {
  if (period === 'day') {
    return keys.map(h => {
      const hr = parseInt(h);
      return hr === 0 ? '12am' : hr < 12 ? hr+'am' : hr === 12 ? '12pm' : (hr-12)+'pm';
    });
  }
  if (period === 'year') {
    return keys.map(ym => {
      const [y, m] = ym.split('-');
      return new Date(+y, +m-1).toLocaleString('default', { month: 'short' });
    });
  }
  return keys.map(d => {
    const dt = new Date(d + 'T00:00:00');
    return period === 'week'
      ? dt.toLocaleDateString('default', { weekday:'short', day:'numeric' })
      : dt.getDate();
  });
}

function trendTitle(period, prefix) {
  return prefix + ' ' + ({
    day:   'by Hour — Today',
    week:  'by Day — This Week',
    month: 'by Day — This Month',
    year:  'by Month — This Year',
  }[period] ?? '');
}

function progressBars(data, total, fillColor, emptyMsg) {
  if (!data || !data.length) return `<p style="font-size:12px;color:#94a3b8;text-align:center;padding:12px 0">${emptyMsg}</p>`;
  const maxV = Math.max(...data.map(r => r[1]));
  return data.map(([label, val]) => {
    const pct = total ? ((val/total)*100).toFixed(1) : 0;
    const bar = maxV ? Math.round(val/maxV*100) : 0;
    return `<div class="pb">
      <div class="pb-hdr"><span class="pb-lbl">${label.replace(/_/g,' ')}</span><span class="pb-val">${fmt(val)} (${pct}%)</span></div>
      <div class="pb-track"><div class="pb-fill" style="width:${bar}%;background:${fillColor}"></div></div>
    </div>`;
  }).join('');
}

// ── OVERVIEW ───────────────────────────────────────────────────────────────────
const OV_CIRC = 2 * Math.PI * 26; // r=26 SVG circles

function updateOverview(p) {
  const d = periodData[p];
  const s = callStats[p];
  const t = d.total || 1;

  // ── Card 1: Total Calls ───────────────────────────────────────────────────
  document.getElementById('ov-total-new').textContent = fmt(d.total);

  // Gender percentages (used for SBC arcs below)
  const gArr  = d.by_gender ?? [];
  const gSum  = Math.max(1, gArr.reduce((a, r) => a + r[1], 0));
  const maleR = gArr.find(r => r[0] === 'male');
  const femR  = gArr.find(r => r[0] === 'female');
  const mPct  = maleR ? Math.round(maleR[1] / gSum * 100) : 0;
  const fPct  = femR  ? Math.round(femR[1]  / gSum * 100) : 0;

  // Overview: Gender x Age grouped bar chart
  const ovAgBands = ['10-14','15-19','20-25','25+'];
  const ovAgG     = d.by_age_gender ?? {};
  if (CC['ovAgeGenderChart']) { CC['ovAgeGenderChart'].destroy(); delete CC['ovAgeGenderChart']; }
  const ovAgCtx = document.getElementById('ovAgeGenderChart');
  if (ovAgCtx) {
    CC['ovAgeGenderChart'] = new Chart(ovAgCtx, {
      type: 'bar',
      data: {
        labels: ovAgBands,
        datasets: [
          { label:'Male',   backgroundColor:'#3b82f6', data: ovAgBands.map(b => ovAgG[b]?.male   ?? 0) },
          { label:'Female', backgroundColor:'#ec4899', data: ovAgBands.map(b => ovAgG[b]?.female ?? 0) },
          { label:'Other',  backgroundColor:'#a78bfa', data: ovAgBands.map(b => ovAgG[b]?.other  ?? 0) },
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position:'top', labels:{ boxWidth:10, font:{ size:10 } } }, tooltip:{ enabled:true } },
        scales: { x:{ ticks:{ font:{ size:9 } } }, y:{ ticks:{ font:{ size:9 } }, beginAtZero:true } }
      }
    });
  }

  // ── Card 2: Referred Cases = tickets with a referral destination ─────────
  const immCnt         = d.imm_act ?? 0;
  const uptakeCnt      = d.uptake ?? 0;
  const refCnt         = d.referral_count ?? 0;
  const refCompPct     = refCnt > 0 ? Math.min(100, +(uptakeCnt / refCnt * 100).toFixed(1)) : 0;
  const referredPct    = d.total > 0 ? +(refCnt / d.total * 100).toFixed(1) : 0;
  document.getElementById('ov-referred-new').textContent = fmt(refCnt);
  const refArc  = document.getElementById('ov-ref-comp-arc');
  const refTxt  = document.getElementById('ov-ref-comp-txt');
  const uptakeEl = document.getElementById('ov-uptake-cnt');
  const immEl    = document.getElementById('ov-imm-cnt');
  if (refArc)   refArc.setAttribute('stroke-dasharray', `${OV_CIRC * referredPct / 100} ${OV_CIRC}`);
  if (refTxt)   refTxt.textContent   = referredPct + '%';
  if (uptakeEl) uptakeEl.textContent = fmt(Math.max(0, uptakeCnt - DISPLAY_UPTAKE_OFFSET)) + ' uptakes confirmed';
  if (immEl)    immEl.textContent    = refCompPct + '%';

  // ── Card 3: High Risk Cases = immediate_action_required ──────────────────
  const highRiskPct = d.total > 0 ? +(immCnt / d.total * 100).toFixed(1) : 0;
  const pendingCnt  = ((() => { const sm = {}; (d.by_status ?? []).forEach(([k,v]) => sm[k]=v); return (sm['open']??0)+(sm['in_progress']??0); })());
  document.getElementById('ov-resolved-new').textContent = fmt(immCnt);
  const pendArc  = document.getElementById('ov-pending-arc');
  const pendTxt  = document.getElementById('ov-pending-txt');
  const pendOpen = document.getElementById('ov-pending-open');
  if (pendArc)  pendArc.setAttribute('stroke-dasharray', `${OV_CIRC * highRiskPct / 100} ${OV_CIRC}`);
  if (pendTxt)  pendTxt.textContent  = highRiskPct + '%';
  if (pendOpen) pendOpen.textContent = fmt(pendingCnt) + ' pending';

  // ── Card 4: Gender donuts ─────────────────────────────────────────────────
  const maleArc = document.getElementById('ov-male-arc');
  const maleTxt = document.getElementById('ov-male-txt');
  if (maleArc) maleArc.setAttribute('stroke-dasharray', `${OV_CIRC * Math.min(mPct, 100) / 100} ${OV_CIRC}`);
  if (maleTxt) maleTxt.textContent = mPct + '%';
  const femArc = document.getElementById('ov-female-arc');
  const femTxt = document.getElementById('ov-female-txt');
  if (femArc) femArc.setAttribute('stroke-dasharray', `${OV_CIRC * Math.min(fPct, 100) / 100} ${OV_CIRC}`);
  if (femTxt) femTxt.textContent = fPct + '%';

  // ── Call Activity strip (real call data, from the calls table) ───────────
  document.getElementById('ov-c-total').textContent    = fmt(s.total);
  document.getElementById('ov-c-inbound').textContent  = fmt(s.inbound);
  document.getElementById('ov-c-outbound').textContent = fmt(s.outbound);
  document.getElementById('ov-c-urgent').textContent   = fmt(urgentOpen);
  document.getElementById('ov-c-answered').textContent = fmt(s.answered);

  // ── Trend chart ───────────────────────────────────────────────────────────
  const ctkeys = Object.keys(s.trend);
  const ctvals = Object.values(s.trend);
  document.getElementById('ov-trend-label').textContent = trendTitle(p, 'Calls');
  rc('ovTrendChart', 'bar', trendLabels(p, ctkeys), ctvals, { single: '#f97316' });

  // ── Row 2 charts ──────────────────────────────────────────────────────────

  // Calls by Case Type donut
  const caseLabels = d.by_purpose.map(r => r[0]);
  const caseData   = d.by_purpose.map(r => r[1]);
  if (CC['ovCaseTypeDonut']) { CC['ovCaseTypeDonut'].destroy(); delete CC['ovCaseTypeDonut']; }
  const ctCtx = document.getElementById('ovCaseTypeDonut');
  if (ctCtx) {
    CC['ovCaseTypeDonut'] = new Chart(ctCtx, {
      type: 'doughnut',
      data: { labels: caseLabels, datasets: [{ data: caseData, backgroundColor: ['#f97316','#7c3aed','#2563eb','#16a34a','#0891b2','#1e293b','#e11d48','#ca8a04'], borderWidth: 0 }] },
      options: { cutout: '55%', plugins: { legend: { display: false } }, responsive: true, maintainAspectRatio: false },
    });
  }
  // Update legend labels
  const caseLegend = document.getElementById('ov-case-legend');
  if (caseLegend) {
    const spans = caseLegend.querySelectorAll('span[data-case-lbl]');
    caseLabels.slice(0, 6).forEach((lbl, i) => { if (spans[i]) spans[i].textContent = lbl.length > 14 ? lbl.slice(0,14)+'…' : lbl; });
  }

  // Calls Per Month — always use monthly buckets from the active period
  let mthKeys, mthVals;
  if (p === 'year' || p === 'range') {
    mthKeys = Object.keys(d.trend);
    mthVals = Object.values(d.trend);
  } else {
    mthKeys = Object.keys(months12);
    mthVals = Object.values(months12);
  }
  const mthLabels = mthKeys.map(k => { const [y,m] = k.split('-'); return new Date(+y,+m-1).toLocaleString('default',{month:'short',year:'2-digit'}); });
  if (CC['ovMonthLine']) { CC['ovMonthLine'].destroy(); delete CC['ovMonthLine']; }
  const mlCtx = document.getElementById('ovMonthLine');
  if (mlCtx) {
    CC['ovMonthLine'] = new Chart(mlCtx, {
      type: 'bar',
      data: { labels: mthLabels, datasets: [{ label:'Cases', data: mthVals, backgroundColor:'#f97316', borderRadius:4 }] },
      options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{ticks:{font:{size:9}}},y:{beginAtZero:true,ticks:{font:{size:9}}}} },
    });
  }

  // Referral By Service grouped bar
  const sbLabels   = d.by_service.map(r => r[0].length > 14 ? r[0].slice(0,14)+'…' : r[0]);
  const sbReferred = d.by_service.map(r => r[1]);
  const sbUptakeMap = d.by_service_uptake ?? {};
  const sbUptake   = d.by_service.map(r => sbUptakeMap[r[0]] ?? 0);
  if (CC['ovServiceBar']) { CC['ovServiceBar'].destroy(); delete CC['ovServiceBar']; }
  const sbCtx = document.getElementById('ovServiceBar');
  if (sbCtx) {
    CC['ovServiceBar'] = new Chart(sbCtx, {
      type: 'bar',
      data: { labels: sbLabels, datasets: [
        { label:'Referred Cases',           data: sbReferred, backgroundColor:'#7c3aed', borderRadius:3 },
        { label:'Confirmed Service Uptake', data: sbUptake,   backgroundColor:'#f97316', borderRadius:3 },
      ]},
      options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{ticks:{font:{size:8},maxRotation:45}},y:{beginAtZero:true,ticks:{font:{size:9}}}} },
    });
  }

  // Category badges below chart
  const ovCats = document.getElementById('ov-service-cats');
  if (ovCats) {
    ovCats.innerHTML = d.by_service.slice(0, 6).map(r => {
      const cats = r[2] ?? [];
      const badges = cats.map(k => `<span style="background:${CAT_COLORS[k]??'#6b7280'}22;color:${CAT_COLORS[k]??'#6b7280'};border-radius:4px;padding:1px 7px;font-size:9px;font-weight:700;border:1px solid ${CAT_COLORS[k]??'#6b7280'}44">${CAT_LABELS[k]??k}</span>`).join(' ');
      return cats.length ? `<div style="display:flex;align-items:center;gap:6px;font-size:10px">
        <span style="color:#374151;font-weight:600;min-width:80px;flex-shrink:0">${r[0].length>18?r[0].slice(0,18)+'…':r[0]}</span>
        <span style="display:flex;gap:3px;flex-wrap:wrap">${badges}</span>
      </div>` : '';
    }).filter(Boolean).join('');
  }
}

// ── GEOGRAPHIC ─────────────────────────────────────────────────────────────────
function updateGeographic(p) {
  const rows  = periodData[p].by_province;
  const total = periodData[p].total || 1;
  const labels = rows.map(r => r[0]);
  const data   = rows.map(r => r[1]);
  const maxV   = Math.max(...data, 1);

  rc('geoBarChart', 'bar', labels, data, { single: '#3b82f6', legend: false });
  rc('geoPieChart', 'pie',  labels, data);

  const tbody = document.getElementById('geo-table');
  tbody.innerHTML = rows.length
    ? rows.map(([prov, cnt], i) => {
        const pct = ((cnt/total)*100).toFixed(1);
        const bar = Math.round(cnt/maxV*100);
        return `<tr>
          <td style="color:#cbd5e1">${i+1}</td>
          <td><strong style="color:#0f172a">${prov}</strong></td>
          <td>${fmt(cnt)}</td><td>${pct}%</td>
          <td style="min-width:90px"><div class="pb-track"><div class="pb-fill" style="width:${bar}%;background:#3b82f6"></div></div></td>
        </tr>`;
      }).join('')
    : '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px">No data for this period</td></tr>';
}

// ── DEMOGRAPHICS ───────────────────────────────────────────────────────────────
function updateDemographics(p) {
  const d = periodData[p];

  rc('demGenderChart',  'doughnut', d.by_gender.map(r  => genderMap[r[0]] ?? r[0]), d.by_gender.map(r  => r[1]));
  rc('demAgeChart',     'bar',      d.age_groups.map(r => r[0]),                     d.age_groups.map(r => r[1]), { single:'#8b5cf6', legend:false });
  rc('demMaritalChart', 'doughnut', d.by_marital.map(r => r[0].replace(/_/g,' ')),   d.by_marital.map(r => r[1]));

  document.getElementById('dem-keypops').innerHTML =
    progressBars(d.by_key_pops, d.total, '#8b5cf6', 'No key pops data for this period');

  // Key Population Groups by Gender & Age grouped bar chart
  const ageBands = ['10-14','15-19','20-25','25+'];
  const agGender = d.by_age_gender ?? {};
  rcGrouped('demAgeGenderChart', ageBands, [
    { label:'Male',   backgroundColor:'#3b82f6', data: ageBands.map(b => agGender[b]?.male   ?? 0) },
    { label:'Female', backgroundColor:'#ec4899', data: ageBands.map(b => agGender[b]?.female ?? 0) },
    { label:'Other',  backgroundColor:'#a78bfa', data: ageBands.map(b => agGender[b]?.other  ?? 0) },
  ]);
}

// ── Grouped bar chart (2 series) ───────────────────────────────────────────────
function rcGrouped(id, labels, datasets) {
  if (CC[id]) { CC[id].destroy(); delete CC[id]; }
  const ctx = document.getElementById(id);
  if (!ctx) return;
  CC[id] = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { boxWidth: 12, padding: 16, font: { size: 12 } } },
        tooltip: { mode: 'index', intersect: false },
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 11 } } },
        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
      },
    },
  });
}

// ── SERVICES ───────────────────────────────────────────────────────────────────
function updateServices(p) {
  const d = periodData[p];

  // KPI cards
  const refCount    = d.referral_count  ?? 0;
  const refUptake   = d.referral_uptake ?? 0;
  const svcCount    = d.service_count   ?? 0;
  const uptake      = d.uptake          ?? 0;
  const total       = d.total           ?? 0;
  document.getElementById('svc-kpi-referred').textContent    = fmt(refCount);
  document.getElementById('svc-kpi-ref-rate').textContent    = (refCount ? Math.round(refUptake / refCount * 100) : 0) + '%';
  const svcServicesEl = document.getElementById('svc-kpi-services'); if (svcServicesEl) svcServicesEl.textContent = fmt(svcCount);
  const svcSvcRateEl  = document.getElementById('svc-kpi-svc-rate');  if (svcSvcRateEl)  svcSvcRateEl.textContent  = (svcCount ? Math.round(uptake / svcCount * 100) : 0) + '%';
  document.getElementById('svc-kpi-uptake').textContent      = fmt(Math.max(0, uptake - DISPLAY_UPTAKE_OFFSET));
  document.getElementById('svc-kpi-uptake-rate').textContent = (total  ? Math.round(uptake / total  * 100) : 0) + '%';

  // Referral By Service — grouped bar (referred vs confirmed uptake)
  const svcLabels  = d.by_service.map(r => r[0]);
  const svcReferred = d.by_service.map(r => r[1]);
  const uptakeMap  = d.by_service_uptake ?? {};
  const svcUptake  = svcLabels.map(lbl => uptakeMap[lbl] ?? 0);

  rcGrouped('svcReferralByServiceChart', svcLabels, [
    { label: 'Referred Cases',           data: svcReferred, backgroundColor: '#6366f1', borderRadius: 4 },
    { label: 'Confirmed Service Uptake', data: svcUptake,   backgroundColor: '#f97316', borderRadius: 4 },
  ]);

  // Services × Classification Category table
  const catTbody = document.getElementById('svc-cat-tbody');
  if (catTbody) {
    if (!d.by_service.length) {
      catTbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:14px">No data for this period</td></tr>';
    } else {
      catTbody.innerHTML = d.by_service.map(r => {
        const cats = r[2] ?? [];
        const catHtml = cats.length
          ? cats.map(k => `<span style="display:inline-block;margin:1px 2px;background:${CAT_COLORS[k]??'#6b7280'}22;color:${CAT_COLORS[k]??'#6b7280'};border-radius:4px;padding:1px 7px;font-size:9px;font-weight:700;border:1px solid ${CAT_COLORS[k]??'#6b7280'}44">${CAT_LABELS[k]??k}</span>`).join('')
          : '<span style="color:#cbd5e1;font-size:10px;font-style:italic">Not assigned</span>';
        const uptake = uptakeMap[r[0]] ?? 0;
        const uptakePct = r[1] > 0 ? Math.round(uptake/r[1]*100) : 0;
        return `<tr style="border-bottom:1px solid #f8fafc">
          <td style="padding:7px 8px;font-weight:600;color:#0f172a;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${r[0]}</td>
          <td style="padding:7px 8px">${catHtml}</td>
          <td style="padding:7px 8px;text-align:center;font-weight:700;color:#0f172a">${fmt(r[1])}</td>
          <td style="padding:7px 8px;text-align:center">
            <span style="background:#f0fdf4;color:#16a34a;border-radius:4px;padding:1px 8px;font-size:11px;font-weight:700">${fmt(uptake)} (${uptakePct}%)</span>
          </td>
        </tr>`;
      }).join('');
    }
  }

  if (document.getElementById('svcServiceChart')) rc('svcServiceChart', 'bar', d.by_service.map(r => r[0]), d.by_service.map(r => r[1]), { single:'#0d9488', legend:false, indexAxis:'y' });
  rc('svcReferralChart', 'bar', d.by_referral.map(r => r[0]), d.by_referral.map(r => r[1]), { single:'#fbbf24', legend:false, indexAxis:'y' });
  const ageBands2 = ['10-14','15-19','20-25','25+'];
  const agGender2 = d.by_age_gender ?? {};
  rcGrouped('svcKeyPopsChart', ageBands2, [
    { label:'Male',   backgroundColor:'#3b82f6', data: ageBands2.map(b => agGender2[b]?.male   ?? 0) },
    { label:'Female', backgroundColor:'#ec4899', data: ageBands2.map(b => agGender2[b]?.female ?? 0) },
    { label:'Other',  backgroundColor:'#a78bfa', data: ageBands2.map(b => agGender2[b]?.other  ?? 0) },
  ]);

  document.getElementById('svc-bars').innerHTML =
    progressBars(d.by_service, d.total, '#0d9488', 'No services data for this period');

  // ── Psycho-social Support Breakdown ──────────────────────────────────────
  const psData = d.psychosocial_breakdown ?? [];
  const emptyEl = document.getElementById('psychosocial-empty');
  const chartEl = document.getElementById('psychosocialChart');

  const psMap = {};
  psData.forEach(r => { psMap[r.type] = r; });

  const types = ['Awareness Raising', 'Helpline Marketing', 'Counselling'];
  const ids   = { 'Awareness Raising': 'awareness', 'Helpline Marketing': 'marketing', 'Counselling': 'counselling' };

  types.forEach(t => {
    const row   = psMap[t] ?? { total: 0, uptake: 0 };
    const id    = ids[t];
    const elT   = document.getElementById(`psycho-${id}-total`);
    const elU   = document.getElementById(`psycho-${id}-uptake`);
    if (elT) elT.textContent = fmt(row.total);
    if (elU) elU.textContent = row.total > 0 ? fmt(row.uptake) + ' (' + Math.round(row.uptake/row.total*100) + '%)' : '0';
  });

  if (psData.length === 0) {
    if (emptyEl)  emptyEl.style.display  = 'block';
    if (chartEl)  chartEl.style.display  = 'none';
  } else {
    if (emptyEl)  emptyEl.style.display  = 'none';
    if (chartEl)  chartEl.style.display  = 'block';

    const psColors = { 'Awareness Raising':'#3b82f6', 'Helpline Marketing':'#f59e0b', 'Counselling':'#22c55e' };
    const psLabels = types;
    const psTotals = types.map(t => psMap[t]?.total ?? 0);
    const psUptake = types.map(t => psMap[t]?.uptake ?? 0);

    if (CC['psychosocialChart']) { CC['psychosocialChart'].destroy(); delete CC['psychosocialChart']; }
    const ctx = document.getElementById('psychosocialChart');
    if (ctx) {
      CC['psychosocialChart'] = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: psLabels,
          datasets: [
            { label: 'Cases', data: psTotals, backgroundColor: types.map(t => psColors[t]), borderRadius: 6 },
            { label: 'Uptake', data: psUptake, backgroundColor: types.map(t => psColors[t] + '55'), borderRadius: 6 },
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } },
            tooltip: { mode: 'index', intersect: false },
          },
          scales: {
            x: { grid: { display: false }, ticks: { font: { size: 12 }, color: '#374151' } },
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
          },
        },
      });
    }
  }
}

// ── CALLS ──────────────────────────────────────────────────────────────────────
function updateCalls(p) {
  const s = callStats[p];
  const d = periodData[p];

  document.getElementById('c-total').textContent    = fmt(s.total);
  document.getElementById('c-inbound').textContent  = fmt(s.inbound);
  document.getElementById('c-outbound').textContent = fmt(s.outbound);
  document.getElementById('c-missed').textContent   = fmt(urgentOpen);
  document.getElementById('c-answered').textContent = fmt(s.answered);
  document.getElementById('c-avgdur').textContent   = s.avg_dur + 's';

  const tkeys = Object.keys(s.trend);
  const tvals = Object.values(s.trend);
  document.getElementById('calls-trend-lbl').textContent = trendTitle(p, 'Calls');
  rc('callTrendChart', 'bar', trendLabels(p, tkeys), tvals, { accent:'#3b82f6', muted:'#dbeafe' });

  // ── Agent tickets table ────────────────────────────────────────────────────
  const lbl = document.getElementById('agent-tickets-lbl');
  if (lbl) lbl.textContent = 'Tickets by Agent — ' + ({
    day: 'Today', week: 'This Week', month: 'This Month', year: 'This Year',
  }[p] ?? p);

  const agents = d.by_agent ?? [];
  const maxTotal = Math.max(...agents.map(a => a.total), 1);
  const tbody = document.getElementById('agent-tickets-tbody');
  if (!tbody) return;

  if (!agents.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:16px">No ticket data for this period</td></tr>';
    return;
  }

  tbody.innerHTML = agents.map(a => {
    const bar = maxTotal > 0 ? Math.round(a.total / maxTotal * 100) : 0;
    const extBadge = a.extension
      ? `<span style="margin-left:5px;background:#f1f5f9;color:#64748b;border-radius:4px;padding:1px 6px;font-size:10px;font-weight:600">Ext ${a.extension}</span>`
      : '';
    return `<tr>
      <td>
        <strong style="color:#0f172a">${a.agent}</strong>${extBadge}
      </td>
      <td style="text-align:center;font-weight:700;color:#0f172a">${fmt(a.total)}</td>
      <td style="text-align:center">
        <span style="display:inline-block;background:#fef9c3;color:#a16207;border-radius:4px;padding:1px 8px;font-size:11px;font-weight:700">${fmt(a.open)}</span>
      </td>
      <td style="text-align:center">
        <span style="display:inline-block;background:#eff6ff;color:#3b82f6;border-radius:4px;padding:1px 8px;font-size:11px;font-weight:700">${fmt(a.in_progress)}</span>
      </td>
      <td style="text-align:center">
        <span style="display:inline-block;background:#f0fdf4;color:#16a34a;border-radius:4px;padding:1px 8px;font-size:11px;font-weight:700">${fmt(a.resolved)}</span>
      </td>
      <td style="min-width:120px">
        <div class="pb-track"><div class="pb-fill" style="width:${bar}%;background:#6366f1"></div></div>
      </td>
    </tr>`;
  }).join('');
}

// ── TRENDS ─────────────────────────────────────────────────────────────────────
function updateTrends(p) {
  const d      = periodData[p];
  const tkeys  = Object.keys(d.trend);
  const tvals  = Object.values(d.trend);

  document.getElementById('trend-chart-lbl').textContent = trendTitle(p, 'Interactions');
  rc('trendMainChart', 'line', trendLabels(p, tkeys), tvals, { accent:'#3b82f6', fill:true, legend:false });
}

// ── SOCIAL LISTENING MATRIX ────────────────────────────────────────────────────
const DIGITAL_KEYWORDS  = ['whatsapp','facebook','tiktok','twitter','instagram','chatbot','sms','email','web','online','hotline','helpline','app','platform','chat'];
const COMMUNITY_KEYWORDS = ['ambassador','school','teacher','clinic','health','youth','church','mosque','community','leader','village','ward','organization'];
const HEALTH_KEYWORDS    = ['hiv','sti','mental','health','srhr','substance','drug','alcohol','pregnancy','medical','disease','outbreak','nutrition','tb'];
const PROTECT_KEYWORDS   = ['gbv','violence','abuse','assault','rape','suicide','self-harm','bullying','harassment','child','safety','protection','neglect'];

function slmKeywords(name, list) {
  const n = (name ?? '').toLowerCase();
  return list.some(k => n.includes(k));
}

function slmPillHtml(level, imm) {
  if (imm) return '<span class="rpill-emerg">EMERGENCY</span>';
  if (!level) return '<span class="rpill-low">LOW</span>';
  const l = level.toLowerCase();
  if (l === 'high') return '<span class="rpill-high">HIGH</span>';
  if (l === 'medium') return '<span class="rpill-med">MEDIUM</span>';
  return '<span class="rpill-low">LOW</span>';
}

function updateSocial(p) {
  const d    = periodData[p];
  const s    = callStats[p];
  const prev = prevPeriodData[p] ?? {};
  const total = d.total;

  // ── Grand total pill ──
  el('slm-total').textContent = fmt(total);

  // ── Workflow stats ──
  el('slm-wf-total').textContent    = fmt(total);
  el('slm-wf-issues').textContent   = fmt(d.by_purpose.length ? d.by_purpose.reduce((a, r) => a + r[1], 0) : 0);
  el('slm-wf-actions').textContent  = fmt(total);
  el('slm-wf-uptake').textContent   = fmt(d.uptake ?? 0);
  el('slm-wf-referrals').textContent = fmt(d.by_referral.reduce((a, r) => a + r[1], 0));

  // ── Sources (split digital vs community) ──
  const modes    = d.by_mode;
  const digital  = modes.filter(r => slmKeywords(r[0], DIGITAL_KEYWORDS));
  const community = modes.filter(r => !slmKeywords(r[0], DIGITAL_KEYWORDS));
  const maxSrc   = Math.max(...modes.map(r => r[1]), 1);
  const digTotal = digital.reduce((a, r) => a + r[1], 0);
  const comTotal = community.reduce((a, r) => a + r[1], 0);

  function srcRows(arr) {
    if (!arr.length) return '<p style="font-size:10px;color:#94a3b8;padding:4px 0">—</p>';
    return arr.map(([name, cnt]) => {
      const bar = Math.round(cnt / maxSrc * 100);
      return `<div class="slm-src-row">
        <span class="slm-src-name">${name}</span>
        <div style="flex:1;margin:0 5px;background:#f1f5f9;border-radius:2px;height:4px"><div style="width:${bar}%;background:#3b82f6;height:4px;border-radius:2px"></div></div>
        <span class="slm-src-cnt">${fmt(cnt)}</span>
      </div>`;
    }).join('');
  }
  el('slm-digital-sources').innerHTML = srcRows(digital) + (digTotal ? `<div class="slm-total-row" style="margin-top:4px"><span>SUB-TOTAL</span><span>${fmt(digTotal)}</span></div>` : '');
  el('slm-community-sources').innerHTML = srcRows(community) + (comTotal ? `<div class="slm-total-row" style="margin-top:4px;background:#0d9488"><span>SUB-TOTAL</span><span>${fmt(comTotal)}</span></div>` : '');
  el('slm-src-total').innerHTML = `<span>GRAND TOTAL</span><span>${fmt(digTotal + comTotal)}</span>`;

  // ── Issues (grouped by category) ──
  const purposes = d.by_purpose;
  const healthIss    = purposes.filter(r => slmKeywords(r[0], HEALTH_KEYWORDS));
  const protectIss   = purposes.filter(r => !slmKeywords(r[0], HEALTH_KEYWORDS) && slmKeywords(r[0], PROTECT_KEYWORDS));
  const socialIss    = purposes.filter(r => !slmKeywords(r[0], HEALTH_KEYWORDS) && !slmKeywords(r[0], PROTECT_KEYWORDS));
  const allIssTotal  = purposes.reduce((a, r) => a + r[1], 0);

  function issueRows(arr, startIdx) {
    if (!arr.length) return '<p style="font-size:10px;color:#94a3b8;padding:2px 4px">—</p>';
    return arr.slice(0, 6).map(([name, cnt], i) => `
      <div class="slm-issue-row">
        <span class="slm-issue-num">${startIdx + i + 1}</span>
        <span class="slm-issue-name" title="${name}">${name}</span>
        <span class="slm-issue-cnt">${fmt(cnt)}</span>
      </div>`).join('');
  }
  el('slm-issues-health').innerHTML     = issueRows(healthIss, 0);
  el('slm-issues-protection').innerHTML = issueRows(protectIss, healthIss.length);
  el('slm-issues-social').innerHTML     = issueRows(socialIss, healthIss.length + protectIss.length);
  el('slm-issues-total').innerHTML      = `<span>TOTAL ISSUES</span><span>${fmt(allIssTotal)}</span>`;

  // ── Risk Classification ──
  const priMap = {};
  d.by_priority.forEach(([k, v]) => { priMap[(k ?? '').toLowerCase()] = v; });
  const rEmerg = d.imm_act ?? 0;
  const rHigh  = priMap.high   ?? 0;
  const rMed   = priMap.medium ?? 0;
  const rLow   = priMap.low    ?? 0;
  const rTot   = total || 1;

  function setRisk(suffix, count, total) {
    const pct = ((count / total) * 100).toFixed(1);
    el('slm-r-' + suffix).textContent  = fmt(count);
    el('slm-rp-' + suffix).textContent = pct + '%';
    el('slm-rb-' + suffix).style.width = Math.min(100, parseFloat(pct)) + '%';
  }
  setRisk('emerg', rEmerg, rTot);
  setRisk('high',  rHigh,  rTot);
  setRisk('med',   rMed,   rTot);
  setRisk('low',   rLow,   rTot);
  el('slm-risk-total').innerHTML = `<span>TOTAL CLASSIFIED</span><span>${fmt(total)}</span>`;

  // ── Response Actions (from by_status) ──
  const actMap = {
    open        : ['🫂 Counselling / Support',   '#dcfce7', '#16a34a'],
    in_progress : ['⚡ Active Intervention',      '#fef9c3', '#a16207'],
    pending     : ['📋 Pending Follow-Up',        '#eff6ff', '#3b82f6'],
    referred    : ['🏥 Referral Made',            '#f0fdf4', '#15803d'],
    closed      : ['✅ Case Closed / Resolved',   '#f0fdf4', '#0d9488'],
    resolved    : ['✅ Case Resolved',             '#f0fdf4', '#0d9488'],
  };
  const actTotal = d.by_status.reduce((a, r) => a + r[1], 0);
  const actMax   = Math.max(...d.by_status.map(r => r[1]), 1);
  el('slm-actions').innerHTML = d.by_status.length ? d.by_status.map(([status, cnt]) => {
    const [label, bg, col] = actMap[status] ?? [`📌 ${status.replace(/_/g,' ')}`, '#f8fafc', '#374151'];
    const pct = actTotal ? ((cnt / actTotal) * 100).toFixed(1) : 0;
    const bar = Math.round(cnt / actMax * 100);
    return `<div style="padding:4px 0;border-bottom:1px dotted #f1f5f9">
      <div style="display:flex;justify-content:space-between;font-size:10.5px;margin-bottom:2px">
        <span style="font-weight:600;color:#374151">${label}</span>
        <span style="font-weight:700;color:#0f172a">${fmt(cnt)}</span>
      </div>
      <div style="display:flex;align-items:center;gap:5px">
        <div style="flex:1;background:#f1f5f9;border-radius:2px;height:5px">
          <div style="width:${bar}%;background:${col};height:5px;border-radius:2px"></div>
        </div>
        <span style="font-size:9px;color:#94a3b8">${pct}%</span>
      </div>
    </div>`;
  }).join('') : '<p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px">No action data</p>';
  el('slm-actions-total').innerHTML = `<span>TOTAL ACTIONS</span><span>${fmt(actTotal)}</span>`;

  // ── Referrals ──
  const refs    = d.by_referral;
  const refTotal = refs.reduce((a, r) => a + r[1], 0);
  el('slm-referrals').innerHTML = refs.length ? refs.map(([name, cnt]) => `
    <div class="slm-ref-row">
      <span class="slm-ref-name">${name}</span>
      <span class="slm-ref-cnt">${fmt(cnt)}</span>
    </div>`).join('') : '<p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px">No referral data</p>';
  el('slm-ref-total').innerHTML = `<span>TOTAL REFERRALS</span><span>${fmt(refTotal)}</span>`;

  // ── Community Trends ──
  const prevPurp = prev.by_purpose ?? {};
  const tbody7 = document.getElementById('slm-trends-tbody');
  if (tbody7) {
    tbody7.innerHTML = purposes.length ? purposes.slice(0, 9).map(([name, cnt], i) => {
      const prevCnt = prevPurp[name] ?? 0;
      const chg     = prevCnt > 0 ? Math.round(((cnt - prevCnt) / prevCnt) * 100) : null;
      const trend   = chg === null ? '<span style="color:#94a3b8">–</span>'
        : chg > 0  ? `<span style="color:#dc2626;font-weight:700">↑ +${chg}%</span>`
        : chg < 0  ? `<span style="color:#16a34a;font-weight:700">↓ ${chg}%</span>`
        : '<span style="color:#94a3b8">→ 0%</span>';
      return `<tr>
        <td style="color:#94a3b8">${i + 1}</td>
        <td style="color:#374151">${name}</td>
        <td style="font-weight:700;color:#0f172a">${fmt(cnt)}</td>
        <td>${trend}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:14px">No purpose data for this period</td></tr>';
  }

  // ── Geographic (provinces) ──
  const provs  = d.by_province;
  const geoMax = Math.max(...provs.map(r => r[1]), 1);
  const tbody8 = document.getElementById('slm-geo-tbody');
  if (tbody8) {
    tbody8.innerHTML = provs.length ? provs.map(([name, cnt], i) => {
      const pct = total ? ((cnt / total) * 100).toFixed(1) : 0;
      const bar = Math.round(cnt / geoMax * 100);
      return `<tr>
        <td style="color:#94a3b8">${i + 1}</td>
        <td style="font-weight:600;color:#0f172a">${name}</td>
        <td>${fmt(cnt)}</td>
        <td>${pct}%</td>
        <td style="min-width:70px"><div style="background:#e2e8f0;border-radius:2px;height:5px">
          <div style="width:${bar}%;height:5px;border-radius:2px;background:#3b82f6"></div>
        </div></td>
      </tr>`;
    }).join('') : '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:14px">No geographic data</td></tr>';
  }
  el('slm-geo-total').innerHTML = `<span>TOTAL</span><span>${fmt(total)}</span>`;

  // ── Urgent Escalation ──
  el('slm-urg-imm').textContent    = fmt(d.imm_act ?? 0);
  el('slm-urg-missed').textContent = fmt(s.missed ?? 0);
  el('slm-urg-high').textContent   = fmt(rHigh);
  el('slm-urg-repeat').textContent = fmt(d.repeat ?? 0);
  el('slm-urg-valid').textContent  = fmt(d.valid ?? 0);
  el('slm-urg-uptake').textContent = fmt(d.uptake ?? 0);
  el('slm-urg-total').textContent  = fmt((d.imm_act ?? 0) + rHigh);
}

// ── CALL TARGETS ───────────────────────────────────────────────────────────────
function renderTargets() {
  const active = callTargetRows.filter(r => r.daily_target && !r.expired);

  const totalPeriodTarget = active.reduce((s, r) => s + (r.period_target ?? 0), 0);
  const totalPeriodCalls  = active.reduce((s, r) => s + (r.period_calls  ?? 0), 0);
  const totalTodayReq     = active.reduce((s, r) => s + (r.today_required ?? 0), 0);
  const totalTodayCalls   = active.reduce((s, r) => s + (r.today_calls   ?? 0), 0);
  const coverage          = totalPeriodTarget ? Math.min(100, Math.round(totalPeriodCalls / totalPeriodTarget * 100)) : 0;

  el('tgt-period-target').textContent  = fmt(totalPeriodTarget);
  el('tgt-period-calls').textContent   = fmt(totalPeriodCalls);
  el('tgt-coverage').textContent       = coverage + '%';
  el('tgt-active-agents').textContent  = active.length;
  el('tgt-today-required').textContent = fmt(totalTodayReq);
  el('tgt-today-calls').textContent    = fmt(totalTodayCalls);

  // Grouped bar chart: Period Target vs Calls Made per agent
  rcGrouped('tgtAgentChart', active.map(r => r.name), [
    { label: 'Period Target', data: active.map(r => r.period_target ?? 0), backgroundColor: '#6366f1', borderRadius: 4 },
    { label: 'Calls Made',    data: active.map(r => r.period_calls  ?? 0), backgroundColor: '#22c55e', borderRadius: 4 },
  ]);

  // ── Agent cards ────────────────────────────────────────────────────────────
  function tgtColor(pct) {
    if (pct >= 100) return '#22c55e';
    if (pct >= 60)  return '#3b82f6';
    if (pct >= 30)  return '#fbbf24';
    return '#f87171';
  }
  function fmtDate(d) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-ZA', { day: '2-digit', month: 'short' });
  }

  const container = el('tgt-agent-cards');
  if (!callTargetRows.length) {
    container.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:24px;grid-column:1/-1">No agents found</p>';
    return;
  }

  // Build card HTML first, then draw charts
  container.innerHTML = callTargetRows.map((row, i) => {
    const pct     = row.period_target ? Math.min(100, Math.round((row.period_calls ?? 0) / row.period_target * 100)) : 0;
    const col     = tgtColor(pct);
    const todayOk = row.today_required != null && row.today_calls >= row.today_required;
    const badge   = row.expired     ? ['EXPIRED',  '#fee2e2','#dc2626']
                  : !row.daily_target ? ['NO TARGET','#f1f5f9','#94a3b8']
                  : pct >= 100      ? ['MET ✓',    '#dcfce7','#16a34a']
                  : pct >= 60       ? ['ON TRACK',  '#eff6ff','#3b82f6']
                  : pct >= 30       ? ['BEHIND',    '#fef9c3','#a16207']
                  :                   ['AT RISK',   '#fee2e2','#dc2626'];
    const targetDay = row.target_day ? fmtDate(row.target_day) : null;
    const periodStr = row.start_date
      ? fmtDate(row.start_date) + ' → ' + (targetDay ?? (row.end_date ? fmtDate(row.end_date) : 'ongoing'))
      : 'No period set';

    // Today's progress bar values
    const todayPct = row.today_required ? Math.min(100, Math.round(row.today_calls / row.today_required * 100)) : 0;
    const todayCol = tgtColor(todayPct);

    return `<div class="glass" style="padding:18px;${row.expired ? 'opacity:0.6' : ''}">
      <!-- Header -->
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px">
        <div>
          <div style="font-size:13px;font-weight:700;color:#0f172a">${row.name}</div>
          <div style="font-size:10px;color:#94a3b8;margin-top:2px">${periodStr}</div>
          ${targetDay ? `<div style="font-size:10px;color:#6366f1;font-weight:600;margin-top:1px">Day: ${targetDay}</div>` : ''}
        </div>
        <span style="font-size:9px;font-weight:700;padding:3px 8px;border-radius:20px;background:${badge[1]};color:${badge[2]};white-space:nowrap">${badge[0]}</span>
      </div>

      <!-- Donut chart -->
      <div style="position:relative;width:110px;height:110px;margin:0 auto 14px">
        <canvas id="tgt-donut-${i}"></canvas>
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center">
          <div style="font-size:20px;font-weight:900;color:${col};line-height:1">${pct}%</div>
          <div style="font-size:9px;color:#94a3b8;margin-top:2px">coverage</div>
        </div>
      </div>

      <!-- Period stats -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
        <div style="background:#f8fafc;border-radius:10px;padding:8px 10px;text-align:center">
          <div style="font-size:16px;font-weight:800;color:#6366f1">${row.period_target != null ? fmt(row.period_target) : '—'}</div>
          <div style="font-size:9px;color:#94a3b8;margin-top:2px">${row.period_days ?? '?'} days × ${row.daily_target ?? '—'}/day</div>
        </div>
        <div style="background:#f8fafc;border-radius:10px;padding:8px 10px;text-align:center">
          <div style="font-size:16px;font-weight:800;color:${(row.period_calls ?? 0) >= (row.period_target ?? Infinity) ? '#16a34a' : '#0f172a'}">${row.period_calls != null ? fmt(row.period_calls) : '—'}</div>
          <div style="font-size:9px;color:#94a3b8;margin-top:2px">calls made</div>
        </div>
      </div>

      <!-- Today bar chart -->
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:10px;margin-bottom:4px">
          <span style="color:#64748b;font-weight:600">Today</span>
          <span style="color:#94a3b8">${fmt(row.today_calls)} / ${row.today_required != null ? fmt(row.today_required) : '—'} req.</span>
        </div>
        <div style="height:80px"><canvas id="tgt-today-${i}"></canvas></div>
      </div>

      <!-- Carry-forward -->
      ${row.carry_forward > 0 ? `
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:6px 10px;display:flex;justify-content:space-between;font-size:11px">
        <span style="color:#92400e;font-weight:600">Carry-forward</span>
        <span style="color:#f97316;font-weight:800">+${fmt(row.carry_forward)}</span>
      </div>` : ''}
    </div>`;
  }).join('');

  // Draw charts for each agent card
  callTargetRows.forEach((row, i) => {
    const pct   = row.period_target ? Math.min(100, Math.round((row.period_calls ?? 0) / row.period_target * 100)) : 0;
    const col   = tgtColor(pct);
    const rem   = Math.max(0, 100 - pct);

    // Donut: coverage
    rc(`tgt-donut-${i}`, 'doughnut',
      ['Calls Made', 'Remaining'],
      [pct, rem],
      { colors: [col, '#f1f5f9'], legend: false }
    );

    // Mini bar: today's calls vs today required
    const todayReq   = row.today_required ?? 0;
    const todayCalls = row.today_calls    ?? 0;
    const todayCol   = tgtColor(todayReq ? Math.min(100, Math.round(todayCalls / todayReq * 100)) : 0);
    rc(`tgt-today-${i}`, 'bar',
      ['Required', 'Calls Made'],
      [todayReq, todayCalls],
      { colors: ['#e2e8f0', todayCol], legend: false }
    );
  });
}

function el(id) { return document.getElementById(id); }

// ── 12-month all-time trend (static, always shown) ────────────────────────────
(function () {
  const mLabels = Object.keys(months12).map(ym => {
    const [y, m] = ym.split('-');
    return new Date(+y, +m-1).toLocaleString('default', { month:'short', year:'2-digit' });
  });
  rc('trendAllChart', 'line', mLabels, Object.values(months12), { accent:'#3b82f6', fill:true, legend:false });
})();

// ── Period button dispatcher ───────────────────────────────────────────────────
function setPeriod(section, period, btn) {
  document.querySelectorAll(`#sec-${section} .period-btn`).forEach(b => b.classList.remove('active-period'));
  document.querySelectorAll(`#sec-${section} .period-select`).forEach(s => s.classList.remove('active-period'));
  if (btn) btn.classList.add('active-period');

  const fn = { overview:updateOverview, geographic:updateGeographic, demographics:updateDemographics, services:updateServices, calls:updateCalls, trends:updateTrends, social:updateSocial };
  if (fn[section]) fn[section](period);
  if (section === 'targets') renderTargets();
}

// ── Section switcher ──────────────────────────────────────────────────────────
function showSection(name, btn) {
  document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
  document.querySelectorAll('.sb-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sec-' + name).style.display = 'block';
  btn.classList.add('active');
  if (name === 'targets') { renderTargets(); return; }
  if (name === 'bot')     { updateBotStats(); return; }
  // Re-render the active period for the newly visible section
  const activeBtn = document.querySelector(`#sec-${name} .period-btn.active-period`);
  if (activeBtn) {
    const match = activeBtn.getAttribute('onclick').match(/'(\w+)'/g);
    if (match && match[1]) setPeriod(name, match[1].replace(/'/g,''), activeBtn);
  } else {
    // Check if a select has active-period (year or month selected)
    const activeSel = document.querySelector(`#sec-${name} .period-select.active-period`);
    if (activeSel) activeSel.dispatchEvent(new Event('change'));
  }
}

// ── Download / print the currently active tab ────────────────────────────────
function printCurrentTab() {
  const visibleSec = [...document.querySelectorAll('.section')].find(s => s.style.display !== 'none');
  const label = visibleSec?.querySelector('.sec-title')?.textContent?.trim() || 'Dashboard';
  const originalTitle = document.title;
  const stamp = new Date().toISOString().slice(0, 10);
  document.title = `Helpline Analytics — ${label} — ${stamp}`;
  window.print();
  setTimeout(() => { document.title = originalTitle; }, 500);
}

// ── PHP-determined defaults (server guarantees these periods have data) ───
const TICKET_DEFAULT = @json($ticketDefaultPeriod);
const CALL_DEFAULT   = @json($callDefaultPeriod);

// ── Year / Month picker state ────────────────────────────────────────────────
let activeYear  = @json($yearVal);
let activeMonth = @json($selectedMonth ?? $monthStart->format('Y-m'));
let availableYears = @json($availableYears);
let activeDateFrom = @json($dateFrom ?? '');
let activeDateTo   = @json($dateTo ?? '');

const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function buildMonthOptions(selectEl, currentMonth) {
  const [selY, selM] = currentMonth.split('-').map(Number);
  const now = new Date();
  selectEl.innerHTML = '';
  // Show all months for the selected year (or up to current month if current year)
  for (let m = 1; m <= 12; m++) {
    if (selY === now.getFullYear() && m > now.getMonth() + 1) break;
    const val = `${selY}-${String(m).padStart(2,'0')}`;
    const opt = document.createElement('option');
    opt.value = val;
    opt.textContent = `${MONTH_NAMES[m-1]} ${selY}`;
    if (m === selM) opt.selected = true;
    selectEl.appendChild(opt);
  }
}

function buildYearOptions(selectEl, years, currentYear) {
  selectEl.innerHTML = '';
  years.forEach(yr => {
    const opt = document.createElement('option');
    opt.value = yr;
    opt.textContent = yr;
    if (yr === currentYear) opt.selected = true;
    selectEl.appendChild(opt);
  });
}

function initPeriodSelects() {
  const TICKET_SECTIONS = ['overview','geographic','demographics','services','trends','social'];
  TICKET_SECTIONS.forEach(sec => {
    const ySel = document.getElementById(`year-select-${sec}`);
    const mSel = document.getElementById(`month-select-${sec}`);
    if (ySel) buildYearOptions(ySel, availableYears, activeYear);
    if (mSel) buildMonthOptions(mSel, activeMonth);
  });
  // Calls section
  const cySel = document.getElementById('year-select-calls');
  const cmSel = document.getElementById('month-select-calls');
  if (cySel) buildYearOptions(cySel, availableYears, activeYear);
  if (cmSel) buildMonthOptions(cmSel, activeMonth);
}

function onYearSelect(section, sel) {
  const yr = parseInt(sel.value);
  activeYear = yr;
  // Reset month to Jan of the selected year (or current month if same year)
  const now = new Date();
  const defaultM = yr === now.getFullYear()
    ? `${yr}-${String(now.getMonth()+1).padStart(2,'0')}`
    : `${yr}-01`;
  activeMonth = defaultM;

  // Rebuild month dropdowns with new year
  document.querySelectorAll('[id^="month-select-"]').forEach(mSel => {
    buildMonthOptions(mSel, activeMonth);
  });

  // Mark this year select active, remove from buttons and month selects
  document.querySelectorAll(`#sec-${section} .period-btn, #sec-${section} .period-select`).forEach(el => el.classList.remove('active-period'));
  sel.classList.add('active-period');

  // Fetch new data and re-render
  fetchWithPeriod('year', section);
}

function onMonthSelect(section, sel) {
  activeMonth = sel.value;
  const [yr] = sel.value.split('-').map(Number);
  activeYear = yr;

  // Sync year dropdowns
  document.querySelectorAll('[id^="year-select-"]').forEach(ySel => {
    [...ySel.options].forEach(o => { o.selected = parseInt(o.value) === yr; });
  });

  // Mark this month select active
  document.querySelectorAll(`#sec-${section} .period-btn, #sec-${section} .period-select`).forEach(el => el.classList.remove('active-period'));
  sel.classList.add('active-period');

  fetchWithPeriod('month', section);
}

function fetchWithPeriod(period, section) {
  const params = new URLSearchParams();
  if (activeService)  params.set('service',    activeService);
  if (activeProject)  params.set('project',    activeProject);
  if (activeDateFrom) params.set('date_from',  activeDateFrom);
  if (activeDateTo)   params.set('date_to',    activeDateTo);
  if (period === 'year')  params.set('year',  activeYear);
  if (period === 'month') params.set('month', activeMonth);

  fetch('/screen/data?' + params.toString(), { cache: 'no-store' })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(d => {
      periodData     = d.periodData;
      callStats      = d.callStats;
      if (d.availableYears) availableYears = d.availableYears;

      const fn = { overview:updateOverview, geographic:updateGeographic, demographics:updateDemographics, services:updateServices, calls:updateCalls, trends:updateTrends, social:updateSocial };
      if (fn[section]) fn[section](period);
      labelPeriodBtns();
    })
    .catch(console.error);
}

function labelPeriodBtns() {
  ['overview','geographic','demographics','services','trends','social'].forEach(sec => {
    document.querySelectorAll(`#sec-${sec} .period-btn`).forEach(btn => {
      const m = btn.getAttribute('onclick').match(/'(day|week)'/);
      if (!m) return;
      const p = m[1], count = periodData[p]?.total ?? 0;
      const labels = { day:'Today', week:'This Week' };
      btn.innerHTML = labels[p] + (count > 0 ? ` <span style="font-size:10px;font-weight:400;opacity:.6">(${fmt(count)})</span>` : '');
    });
    // Label month select
    const mSel = document.getElementById(`month-select-${sec}`);
    if (mSel) {
      const count = periodData['month']?.total ?? 0;
      const [y, m] = activeMonth.split('-').map(Number);
      const label = MONTH_NAMES[m-1] + ' ' + y;
      [...mSel.options].forEach(o => {
        o.text = o.value === activeMonth
          ? label + (count > 0 ? ` (${fmt(count)})` : '')
          : MONTH_NAMES[parseInt(o.value.split('-')[1])-1] + ' ' + o.value.split('-')[0];
      });
    }
    // Label year select
    const ySel = document.getElementById(`year-select-${sec}`);
    if (ySel) {
      const count = periodData['year']?.total ?? 0;
      [...ySel.options].forEach(o => {
        o.text = parseInt(o.value) === activeYear
          ? o.value + (count > 0 ? ` (${fmt(count)})` : '')
          : o.value;
      });
    }
  });
  // Calls section
  document.querySelectorAll('#sec-calls .period-btn').forEach(btn => {
    const m = btn.getAttribute('onclick').match(/'(day|week)'/);
    if (!m) return;
    const p = m[1], count = callStats[p]?.total ?? 0;
    const labels = { day:'Today', week:'This Week' };
    btn.innerHTML = labels[p] + (count > 0 ? ` <span style="font-size:10px;font-weight:400;opacity:.6">(${fmt(count)})</span>` : '');
  });
}

// ── Bot Analytics ─────────────────────────────────────────────────────────────
let botTrendChartInst = null;

function updateBotStats() {
  const d = uchatData;
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  set('bot-total',   (d.total_bot_users ?? 0).toLocaleString());
  set('bot-new',     (d.new_bot_users   ?? 0).toLocaleString());
  set('bot-active',  (d.active_today    ?? 0).toLocaleString());
  const avgNew = d.new_bot_users > 0 ? (d.new_bot_users / 30).toFixed(1) : '—';
  set('bot-avg', avgNew);
  if (d.fetched_at) set('bot-fetched', d.fetched_at);
  loadBotTrendChart();
}

function loadBotTrendChart() {
  fetch('/screen/uchat-history', { cache: 'no-store' })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(rows => {
      if (!rows.length) return;
      const labels = rows.map(r => {
        const d = new Date(r.date);
        return d.toLocaleDateString('default', { day:'2-digit', month:'short' });
      });
      const total  = rows.map(r => r.total_bot_users);
      const active = rows.map(r => r.active_today);
      const newU   = rows.map(r => r.new_bot_users);

      const ctx = document.getElementById('botTrendChart');
      if (!ctx) return;
      if (botTrendChartInst) botTrendChartInst.destroy();
      botTrendChartInst = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Total Users',
              data: total,
              borderColor: '#3b82f6',
              backgroundColor: 'rgba(59,130,246,.08)',
              fill: true,
              tension: 0.3,
              pointRadius: rows.length > 20 ? 2 : 4,
              borderWidth: 2,
            },
            {
              label: 'Active (24h)',
              data: active,
              borderColor: '#22c55e',
              backgroundColor: 'transparent',
              fill: false,
              tension: 0.3,
              pointRadius: rows.length > 20 ? 2 : 4,
              borderWidth: 2,
            },
            {
              label: 'New Users (30d)',
              data: newU,
              borderColor: '#f59e0b',
              backgroundColor: 'transparent',
              fill: false,
              tension: 0.3,
              pointRadius: rows.length > 20 ? 2 : 4,
              borderWidth: 2,
              borderDash: [4, 3],
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } },
            tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()}` } },
          },
          scales: {
            x: { ticks: { font: { size: 10 }, maxRotation: 45 } },
            y: { beginAtZero: false, ticks: { font: { size: 10 } } },
          },
        },
      });
    })
    .catch(() => {});
}

// ── Service filter ────────────────────────────────────────────────────────────
function applyServiceFilter(value) {
  const url = new URL(window.location.href);
  if (value) url.searchParams.set('service', value);
  else url.searchParams.delete('service');
  window.location.href = url.toString();
}

// ── Date range filter ─────────────────────────────────────────────────────────
function applyDateRange() {
  const from = document.getElementById('range-from').value;
  const to   = document.getElementById('range-to').value;
  if (!from && !to) return;
  const url = new URL(window.location.href);
  if (from) url.searchParams.set('date_from', from);
  else url.searchParams.delete('date_from');
  if (to) url.searchParams.set('date_to', to || from);
  else url.searchParams.delete('date_to');
  // Keep other active filters
  if (activeService) url.searchParams.set('service', activeService);
  else url.searchParams.delete('service');
  if (activeProject) url.searchParams.set('project', activeProject);
  else url.searchParams.delete('project');
  window.location.href = url.toString();
}
function clearDateRange() {
  const url = new URL(window.location.href);
  url.searchParams.delete('date_from');
  url.searchParams.delete('date_to');
  window.location.href = url.toString();
}

// ── Project filter ────────────────────────────────────────────────────────────
function applyProjectFilter(value) {
  const url = new URL(window.location.href);
  if (value) url.searchParams.set('project', value);
  else url.searchParams.delete('project');
  window.location.href = url.toString();
}

// ── Gender filter ─────────────────────────────────────────────────────────────
function applyGenderFilter(value) {
  const url = new URL(window.location.href);
  if (value) url.searchParams.set('gender', value);
  else url.searchParams.delete('gender');
  window.location.href = url.toString();
}

// ── Age filter ────────────────────────────────────────────────────────────────
function applyAgeFilter(value) {
  const url = new URL(window.location.href);
  if (value) url.searchParams.set('age', value);
  else url.searchParams.delete('age');
  window.location.href = url.toString();
}

// ── Background data refresh (no page reload) ──────────────────────────────────
function refreshData() {
  const p = new URLSearchParams();
  if (activeService)  p.set('service',   activeService);
  if (activeProject)  p.set('project',   activeProject);
  if (activeDateFrom) p.set('date_from', activeDateFrom);
  if (activeDateTo)   p.set('date_to',   activeDateTo);
  if (activeYear)    p.set('year', activeYear);
  if (activeMonth)   p.set('month', activeMonth);
  const params = p.toString() ? '?' + p.toString() : '';
  fetch('/screen/data' + params, { cache: 'no-store' })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(d => {
      // Reassign module-level data
      periodData     = d.periodData;
      callStats      = d.callStats;
      months12       = d.months;
      prevPeriodData = d.prevPeriodData;
      callTargetRows = d.callTargetRows;
      if (d.availableYears) availableYears = d.availableYears;
      if (d.uchat) uchatData = d.uchat;
      if (d.urgentOpen !== undefined) urgentOpen = d.urgentOpen;
      if (d.total !== undefined) {
        const hdrTotal = document.getElementById('hdr-total');
        if (hdrTotal) hdrTotal.textContent = Math.max(0, d.total - DISPLAY_OFFSET).toLocaleString();
      }

      // Update live indicator
      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit' });
      const upd = document.getElementById('live-updated');
      if (upd) upd.textContent = timeStr;
      const dot = document.getElementById('live-dot');
      if (dot) { dot.style.background = '#22c55e'; setTimeout(() => { dot.style.background = ''; }, 800); }

      // Re-render whichever section is currently visible
      const visibleSec = [...document.querySelectorAll('.section')].find(s => s.style.display !== 'none');
      if (!visibleSec) return;
      const name = visibleSec.id.replace('sec-', '');
      if (name === 'targets') { renderTargets(); return; }
      const activeBtn = document.querySelector(`#sec-${name} .period-btn.active-period`);
      if (activeBtn) {
        const m = activeBtn.getAttribute('onclick').match(/'(day|week|month|year)'/);
        if (m) setPeriod(name, m[1], activeBtn);
      }
      // Always refresh the static 12-month trend chart
      const mLabels = Object.keys(months12).map(ym => {
        const [y, mo] = ym.split('-');
        return new Date(+y, +mo-1).toLocaleString('default', { month:'short', year:'2-digit' });
      });
      rc('trendAllChart', 'line', mLabels, Object.values(months12), { accent:'#3b82f6', fill:true, legend:false });

      console.log('[screen] data refreshed at ' + timeStr);
    })
    .catch(err => console.warn('[screen] refresh failed:', err));
}

function initOverviewCharts() {
  // Overview Gender x Age grouped bar — init with server-rendered data
  const ovAgBands0 = ['10-14','15-19','20-25','25+'];
  const ovAgG0     = @json($ageGenderData ?? []);
  const ovAgCtx0   = document.getElementById('ovAgeGenderChart');
  if (ovAgCtx0) {
    CC['ovAgeGenderChart'] = new Chart(ovAgCtx0, {
      type: 'bar',
      data: {
        labels: ovAgBands0,
        datasets: [
          { label:'Male',   backgroundColor:'#3b82f6', data: ovAgBands0.map(b => ovAgG0[b]?.male   ?? 0) },
          { label:'Female', backgroundColor:'#ec4899', data: ovAgBands0.map(b => ovAgG0[b]?.female ?? 0) },
          { label:'Other',  backgroundColor:'#a78bfa', data: ovAgBands0.map(b => ovAgG0[b]?.other  ?? 0) },
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position:'top', labels:{ boxWidth:10, font:{ size:10 } } }, tooltip:{ enabled:true } },
        scales: { x:{ ticks:{ font:{ size:9 } } }, y:{ ticks:{ font:{ size:9 } }, beginAtZero:true } }
      }
    });
  }
  // ovCaseTypeDonut, ovMonthLine, ovServiceBar are owned by updateOverview — no init needed here
}
// ── Bootstrap ─────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  if (typeof lucide !== 'undefined') lucide.createIcons();
  try { initOverviewCharts(); } catch(e) { console.error('[initOverviewCharts]', e); }
  initPeriodSelects();
  labelPeriodBtns();
  updateOverview(TICKET_DEFAULT);
  updateGeographic(TICKET_DEFAULT);
  updateDemographics(TICKET_DEFAULT);
  updateServices(TICKET_DEFAULT);
  updateCalls(CALL_DEFAULT);
  updateTrends(TICKET_DEFAULT);
  updateSocial(TICKET_DEFAULT);
  renderTargets();

  // Refresh data every 60 seconds without reloading the page
  setInterval(refreshData, 60_000);
});
</script>
</body>
</html>
