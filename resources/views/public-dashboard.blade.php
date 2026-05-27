<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="60">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Helpline Analytics</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1e293b;min-height:100vh;overflow-x:hidden}
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
.sb-logo svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
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
.period-wrap{display:flex;gap:4px;background:#f1f5f9;border-radius:12px;padding:4px}
.period-btn{padding:6px 13px;border:none;background:transparent;border-radius:8px;cursor:pointer;
  font-family:'Inter',sans-serif;font-size:12px;font-weight:500;color:#64748b;transition:all .15s;white-space:nowrap}
.period-btn:hover{color:#1e293b}
.period-btn.active-period{background:#fff;color:#1e293b;box-shadow:0 1px 4px rgba(0,0,0,.1);font-weight:600}

/* KPI grid */
.kpi-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.kpi{flex:1;min-width:110px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;box-shadow:0 1px 6px rgba(0,0,0,.04)}
.kpi-icon{font-size:16px;margin-bottom:6px}
.kpi-val{font-size:22px;font-weight:800;color:#0f172a;line-height:1}
.kpi-lbl{font-size:10px;color:#94a3b8;margin-top:3px;font-weight:500}
.svc-kpi-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px}
.svc-kpi-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.svc-kpi-title{font-weight:700;font-size:14px;color:#1e293b;margin-bottom:14px}
.svc-kpi-body{display:flex;align-items:center;gap:14px}
.svc-kpi-icon{width:48px;height:48px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
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
  <div class="sb-logo">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
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
</aside>

<!-- ── Main ── -->
<main class="main">

<!-- Header -->
<div class="page-hdr">
  <div>
    <div class="page-title">Helpline Analytics</div>
    <div class="page-sub">National Youth Helpline &middot; {{ $lastUpdated ? \Carbon\Carbon::parse($lastUpdated)->format('d M Y, H:i') : 'N/A' }}</div>
  </div>
  <div class="hdr-right">
    <div class="live-pill"><span class="live-dot"></span> Live</div>
    <div class="total-pill"><div class="tv">{{ number_format($total) }}</div><div class="tl">All-time Interactions</div></div>
  </div>
</div>

{{-- ── Debug banner (remove once confirmed working) ──────────────────────── --}}
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:8px 14px;font-size:11px;color:#92400e;display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px">
  <span>📊 Server default: <strong>{{ $ticketDefaultPeriod }}</strong></span>
  <span>📅 Month tickets: <strong>{{ number_format($periodData['month']['total']) }}</strong></span>
  <span>📅 Year tickets: <strong>{{ number_format($periodData['year']['total']) }}</strong></span>
  <span>📞 Month calls: <strong>{{ number_format($callStats['month']['total']) }}</strong></span>
  <span>🕐 Today calls: <strong>{{ number_format($callStats['day']['total']) }}</strong></span>
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

  <!-- No-tickets notice (hidden by JS when period has data) -->
  <div id="ov-no-data-notice" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:10px 16px;font-size:12px;color:#1d4ed8;margin-bottom:12px">
    No ticket interactions for this period &mdash; <span id="ov-notice-calls"></span> calls recorded.
    <span style="color:#64748b;margin-left:6px">Switch to <strong>This Month</strong> or <strong>This Year</strong> for ticket data.</span>
  </div>

  <!-- Period selector -->
  <div class="sec-hdr">
    <span class="sec-title">Overview</span>
    <div class="period-wrap">
      <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('overview','day',this)">Today</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('overview','week',this)">This Week</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onclick="setPeriod('overview','month',this)">This Month</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onclick="setPeriod('overview','year',this)">This Year</button>
    </div>
  </div>

  <!-- Top grid -->
  <div class="top-grid">

    <!-- Activity bar chart (call volume from PBX) -->
    <div class="glass">
      <div class="card-hdr">
        <span class="card-title">Call Activity</span>
        <span class="card-tag" id="ov-trend-label">Calls by Hour — Today</span>
      </div>
      <div class="ch140"><canvas id="ovTrendChart"></canvas></div>
    </div>

    <!-- Mini stats — always use CALL data so Today is never empty -->
    <div class="stat-stack">
      <div class="stat-mini">
        <div class="sm-icon" style="background:#eff6ff">📞</div>
        <div><div class="sm-val" id="ov-total">{{ number_format($sc['total']) }}</div><div class="sm-lbl">Total Calls</div></div>
      </div>
      <div class="stat-mini">
        <div class="sm-icon" style="background:#f0fdf4">✅</div>
        <div><div class="sm-val" id="ov-valid">{{ number_format($sc['answered']) }}</div><div class="sm-lbl">Answered</div></div>
      </div>
      <div class="stat-mini">
        <div class="sm-icon" style="background:#fef2f2">📵</div>
        <div><div class="sm-val" id="ov-repeat">{{ number_format($sc['missed']) }}</div><div class="sm-lbl">Missed</div></div>
      </div>
    </div>

    <!-- Donut overview -->
@php
  $scT   = $sc['total'] ?: 1;
  $ansP  = round($sc['answered'] / $scT * 100);
  $misP  = round($sc['missed']   / $scT * 100);
  $inP   = round($sc['inbound']  / $scT * 100);
@endphp
    <div class="glass">
      <div class="card-hdr"><span class="card-title">Calls Overview</span><span class="card-tag" id="ov-donut-label">Call share</span></div>
      <div class="donut-wrap">
        <canvas id="ovDonut"></canvas>
        <div class="donut-center">
          <div class="donut-pct" id="ov-pct">{{ $ansP }}%</div>
          <div class="donut-sub" id="ov-donut-sub">Answered</div>
        </div>
      </div>
      <div class="ov-rows">
        <div class="ov-row"><span class="ov-dot" style="background:#3b82f6"></span><span class="ov-lbl">Answered</span><span class="ov-val" id="ov-ov-valid">{{ number_format($sc['answered']) }}</span><span class="ov-chg up" id="ov-ov-vpct">{{ $ansP }}%</span></div>
        <div class="ov-row"><span class="ov-dot" style="background:#f87171"></span><span class="ov-lbl">Missed</span><span class="ov-val" id="ov-ov-repeat">{{ number_format($sc['missed']) }}</span><span class="ov-chg muted" id="ov-ov-rpct">{{ $misP }}%</span></div>
        <div class="ov-row"><span class="ov-dot" style="background:#4ade80"></span><span class="ov-lbl">Inbound</span><span class="ov-val" id="ov-ov-imm">{{ number_format($sc['inbound']) }}</span><span class="ov-chg muted" id="ov-ov-ipct">{{ $inP }}%</span></div>
      </div>
    </div>
  </div><!-- /top-grid -->

  <!-- Call KPIs row (from Yeastar / calls table) -->
  <div class="kpi-row">
    <div class="kpi"><div class="kpi-icon">📞</div><div class="kpi-val" id="ov-c-total">{{ number_format($sc['total']) }}</div><div class="kpi-lbl">Total Calls</div></div>
    <div class="kpi"><div class="kpi-icon">📥</div><div class="kpi-val" id="ov-c-inbound">{{ number_format($sc['inbound']) }}</div><div class="kpi-lbl">Inbound</div></div>
    <div class="kpi"><div class="kpi-icon">📤</div><div class="kpi-val" id="ov-c-outbound">{{ number_format($sc['outbound']) }}</div><div class="kpi-lbl">Outbound</div></div>
    <div class="kpi"><div class="kpi-icon">📵</div><div class="kpi-val" id="ov-c-missed">{{ number_format($sc['missed']) }}</div><div class="kpi-lbl">Missed</div></div>
    <div class="kpi"><div class="kpi-icon">✅</div><div class="kpi-val" id="ov-c-answered">{{ number_format($sc['answered']) }}</div><div class="kpi-lbl">Answered</div></div>
    <div class="kpi"><div class="kpi-icon">⏱️</div><div class="kpi-val" id="ov-c-avgdur">{{ $sc['avg_dur'] }}s</div><div class="kpi-lbl">Avg Duration</div></div>
  </div>

  <!-- Bottom grid -->
  <div class="bot-grid">

    <!-- Top purposes -->
    <div class="glass">
      <div class="card-title" style="margin-bottom:10px">Top Purposes of Call</div>
      <div id="ov-purposes"></div>

      <!-- Status breakdown -->
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9">
        <div class="card-title" style="margin-bottom:8px;font-size:11px">Status Breakdown</div>
        <div id="ov-status-bars"></div>
      </div>
    </div>

    <!-- Calendar -->
    <div class="glass">
      <div class="cal-nav"><span>{{ $calMonthName }}</span></div>
      <div class="cal-grid">
        @foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $dn)<div class="cal-dn">{{ $dn }}</div>@endforeach
        @for($e=0;$e<$calFirstDay;$e++)<div class="cal-d empty">0</div>@endfor
        @for($d=1;$d<=$calDays;$d++)<div class="cal-d {{ $d===$today?'today':'' }}">{{ $d }}</div>@endfor
      </div>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9">
        <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Uptake Confirmed</div>
        <div style="font-size:20px;font-weight:800;color:#0f172a" id="ov-uptake">{{ number_format($dd['uptake']) }}</div>
        <div style="font-size:10px;color:#94a3b8;margin-top:2px" id="ov-uptake-sub">{{ $dd['valid'] ? round($dd['uptake']/$dd['valid']*100,1) : 0 }}% of valid</div>
      </div>
    </div>

    <!-- Output -->
    <div class="glass" style="position:relative;overflow:hidden">
      <div class="card-title" style="margin-bottom:10px">Immediate Actions</div>
      <div class="out-val" id="ov-imm">{{ number_format($dd['imm_act']) }}</div>
      <div class="out-lbl">Requiring immediate action</div>
      <span class="out-badge" id="ov-imm-badge">{{ $dd['imm_act'] > 0 ? 'Needs Attention' : 'All Clear' }}</span>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9">
        <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Valid Rate</div>
        <div style="font-size:20px;font-weight:800;color:#16a34a" id="ov-vrate">{{ $ddVp }}%</div>
        <div style="font-size:10px;color:#94a3b8;margin-top:2px">of period interactions</div>
      </div>
    </div>

  </div><!-- /bot-grid -->
</div>

{{-- ══════════════════════════════════ GEOGRAPHIC ══════════════════════════════════ --}}
<div id="sec-geographic" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Geographic Distribution</span>
    <div class="period-wrap">
      <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('geographic','day',this)">Today</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('geographic','week',this)">This Week</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onclick="setPeriod('geographic','month',this)">This Month</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onclick="setPeriod('geographic','year',this)">This Year</button>
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
      <button class="period-btn {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onclick="setPeriod('demographics','month',this)">This Month</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onclick="setPeriod('demographics','year',this)">This Year</button>
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
</div>

{{-- ══════════════════════════════════ SERVICES ══════════════════════════════════ --}}
<div id="sec-services" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Services &amp; Referrals</span>
    <div class="period-wrap">
      <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('services','day',this)">Today</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('services','week',this)">This Week</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onclick="setPeriod('services','month',this)">This Month</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onclick="setPeriod('services','year',this)">This Year</button>
    </div>
  </div>
  <div class="s-card" style="margin-bottom:14px">
    <h3 style="text-align:center;font-size:15px;font-weight:700;margin-bottom:12px">Referral By Service</h3>
    <div style="height:280px"><canvas id="svcReferralByServiceChart"></canvas></div>
  </div>
  <div class="svc-kpi-row">
    <div class="svc-kpi-card">
      <div class="svc-kpi-title">Referred Cases</div>
      <div class="svc-kpi-body">
        <div class="svc-kpi-icon">👥</div>
        <div class="svc-kpi-num" id="svc-kpi-referred">0</div>
      </div>
      <div class="svc-kpi-foot">
        <span class="svc-kpi-foot-lbl">Referral Completion</span>
        <span class="svc-kpi-rate" id="svc-kpi-ref-rate">0%</span>
      </div>
    </div>
    <div class="svc-kpi-card">
      <div class="svc-kpi-title">Services Requested</div>
      <div class="svc-kpi-body">
        <div class="svc-kpi-icon">🏥</div>
        <div class="svc-kpi-num" id="svc-kpi-services">0</div>
      </div>
      <div class="svc-kpi-foot">
        <span class="svc-kpi-foot-lbl">Uptake Rate</span>
        <span class="svc-kpi-rate" id="svc-kpi-svc-rate">0%</span>
      </div>
    </div>
    <div class="svc-kpi-card">
      <div class="svc-kpi-title">Confirmed Uptake</div>
      <div class="svc-kpi-body">
        <div class="svc-kpi-icon">✅</div>
        <div class="svc-kpi-num" id="svc-kpi-uptake">0</div>
      </div>
      <div class="svc-kpi-foot">
        <span class="svc-kpi-foot-lbl">Completion Rate</span>
        <span class="svc-kpi-rate" id="svc-kpi-uptake-rate">0%</span>
      </div>
    </div>
  </div>
  <div class="g3">
    <div class="s-card"><h3>Top Services Requested</h3><div class="ch220"><canvas id="svcServiceChart"></canvas></div></div>
    <div class="s-card"><h3>Top Referral Destinations</h3><div class="ch220"><canvas id="svcReferralChart"></canvas></div></div>
    <div class="s-card"><h3>Key Population Groups</h3><div class="ch220"><canvas id="svcKeyPopsChart"></canvas></div></div>
  </div>
  <div class="s-card">
    <h3>Services Detail</h3>
    <div id="svc-bars"></div>
  </div>
</div>

{{-- ══════════════════════════════════ CALL DETAILS ══════════════════════════════════ --}}
<div id="sec-calls" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Call Activity</span>
    <div class="period-wrap">
      <button class="period-btn {{ $callDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('calls','day',this)">Today</button>
      <button class="period-btn {{ $callDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('calls','week',this)">This Week</button>
      <button class="period-btn {{ $callDefaultPeriod==='month'?'active-period':'' }}" onclick="setPeriod('calls','month',this)">This Month</button>
      <button class="period-btn {{ $callDefaultPeriod==='year'?'active-period':'' }}" onclick="setPeriod('calls','year',this)">This Year</button>
    </div>
  </div>
  <div class="kpi-row">
    <div class="kpi"><div class="kpi-icon">📞</div><div class="kpi-val" id="c-total">0</div><div class="kpi-lbl">Total Calls</div></div>
    <div class="kpi"><div class="kpi-icon">📥</div><div class="kpi-val" id="c-inbound">0</div><div class="kpi-lbl">Inbound</div></div>
    <div class="kpi"><div class="kpi-icon">📤</div><div class="kpi-val" id="c-outbound">0</div><div class="kpi-lbl">Outbound</div></div>
    <div class="kpi"><div class="kpi-icon">📵</div><div class="kpi-val" id="c-missed">0</div><div class="kpi-lbl">Missed</div></div>
    <div class="kpi"><div class="kpi-icon">✅</div><div class="kpi-val" id="c-answered">0</div><div class="kpi-lbl">Answered</div></div>
    <div class="kpi"><div class="kpi-icon">⏱️</div><div class="kpi-val" id="c-avgdur">0s</div><div class="kpi-lbl">Avg Duration</div></div>
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
</div>

{{-- ══════════════════════════════════ TRENDS ══════════════════════════════════ --}}
<div id="sec-trends" class="section" style="display:none">
  <div class="sec-hdr">
    <span class="sec-title">Trends</span>
    <div class="period-wrap">
      <button class="period-btn {{ $ticketDefaultPeriod==='day'?'active-period':'' }}" onclick="setPeriod('trends','day',this)">Today</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='week'?'active-period':'' }}" onclick="setPeriod('trends','week',this)">This Week</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onclick="setPeriod('trends','month',this)">This Month</button>
      <button class="period-btn {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onclick="setPeriod('trends','year',this)">This Year</button>
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
        <button class="period-btn {{ $ticketDefaultPeriod==='month'?'active-period':'' }}" onclick="setPeriod('social','month',this)">This Month</button>
        <button class="period-btn {{ $ticketDefaultPeriod==='year'?'active-period':'' }}" onclick="setPeriod('social','year',this)">This Year</button>
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

<div class="footer">Helpline Analytics &middot; Auto-refreshes every minute &middot; {{ now()->format('d M Y') }}</div>
</main>
</div>

<script>
// ── PHP data ──────────────────────────────────────────────────────────────────
const periodData     = @json($periodData);
const callStats      = @json($callStats);
const months12       = @json($months);
const last7          = @json($last7);
const prevPeriodData = @json($prevPeriodData);

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
function updateOverview(p) {
  const d = periodData[p];
  const t = d.total || 1;
  const s = callStats[p]; // call stats from Yeastar/calls table

  // No-data notice
  const notice = document.getElementById('ov-no-data-notice');
  if (notice) {
    if (d.total === 0 && s.total > 0) {
      notice.style.display = 'block';
      const nc = document.getElementById('ov-notice-calls');
      if (nc) nc.textContent = fmt(s.total);
    } else {
      notice.style.display = 'none';
    }
  }

  // Mini stats — CALL data (always populated, even when no tickets)
  document.getElementById('ov-total').textContent  = fmt(s.total);
  document.getElementById('ov-valid').textContent  = fmt(s.answered);
  document.getElementById('ov-repeat').textContent = fmt(s.missed);

  // Call KPI detail row
  document.getElementById('ov-c-total').textContent    = fmt(s.total);
  document.getElementById('ov-c-inbound').textContent  = fmt(s.inbound);
  document.getElementById('ov-c-outbound').textContent = fmt(s.outbound);
  document.getElementById('ov-c-missed').textContent   = fmt(s.missed);
  document.getElementById('ov-c-answered').textContent = fmt(s.answered);
  document.getElementById('ov-c-avgdur').textContent   = s.avg_dur + 's';

  // Donut — call breakdown (Answered / Missed / Inbound)
  const st = s.total || 1;
  const ansP = Math.round(s.answered / st * 100);
  const misP = Math.round(s.missed   / st * 100);
  const inP  = Math.round(s.inbound  / st * 100);
  document.getElementById('ov-pct').textContent      = ansP + '%';
  document.getElementById('ov-ov-valid').textContent  = fmt(s.answered);
  document.getElementById('ov-ov-vpct').textContent   = ansP + '%';
  document.getElementById('ov-ov-repeat').textContent = fmt(s.missed);
  document.getElementById('ov-ov-rpct').textContent   = misP + '%';
  document.getElementById('ov-ov-imm').textContent    = fmt(s.inbound);
  document.getElementById('ov-ov-ipct').textContent   = inP + '%';

  // Ticket stats (uptake / immediate action / valid rate)
  const vp = t ? Math.round(d.valid/t*100) : 0;
  document.getElementById('ov-uptake').textContent     = fmt(d.uptake);
  document.getElementById('ov-uptake-sub').textContent = (d.valid ? ((d.uptake/d.valid)*100).toFixed(1) : 0) + '% of valid';
  document.getElementById('ov-imm').textContent        = fmt(d.imm_act);
  document.getElementById('ov-vrate').textContent      = vp + '%';
  document.getElementById('ov-imm-badge').textContent  = d.imm_act > 0 ? 'Needs Attention' : 'All Clear';

  // Donut chart
  rc('ovDonut', 'doughnut',
    ['Answered','Missed','Inbound'],
    [ansP, misP, inP],
    { colors: ['#3b82f6','#f87171','#4ade80'], legend: false }
  );

  // Activity chart: use call trend (Yeastar data — has data even when no tickets)
  const ctkeys = Object.keys(s.trend);
  const ctvals = Object.values(s.trend);
  document.getElementById('ov-trend-label').textContent = trendTitle(p, 'Calls');
  rc('ovTrendChart', 'bar', trendLabels(p, ctkeys), ctvals, { accent: '#3b82f6', muted: '#dbeafe' });

  // Top purposes
  document.getElementById('ov-purposes').innerHTML = (d.by_purpose.length
    ? d.by_purpose.slice(0, 3).map(([name, val], i) => {
        const badges = ['badge-go','badge-done','badge-alert'];
        const labs   = ['Active','Common','Flagged'];
        const ring   = i === 1 ? 'done' : '';
        return `<div class="ch-item">
          <div class="ch-ring ${ring}">${i===1?'✓':'○'}</div>
          <div class="ch-body">
            <div class="ch-name">${name}</div>
            <div class="ch-prog">${fmt(val)} / ${fmt(d.total)} interactions</div>
          </div>
          <span class="ch-badge ${badges[i]}">${labs[i]}</span>
        </div>`;
      }).join('')
    : '<p style="font-size:12px;color:#94a3b8;text-align:center;padding:12px 0">No data for this period</p>'
  );

  // Status bars
  const statusColors2 = { open:'#60a5fa', in_progress:'#fbbf24', closed:'#4ade80', resolved:'#34d399' };
  document.getElementById('ov-status-bars').innerHTML = d.by_status.length
    ? d.by_status.map(([k, v]) => {
        const pct = d.total ? ((v/d.total)*100).toFixed(1) : 0;
        const bar = d.total ? Math.round(v/d.total*100) : 0;
        const col = statusColors2[k] ?? '#94a3b8';
        return `<div class="pb">
          <div class="pb-hdr"><span class="pb-lbl">${k.replace('_',' ')}</span><span class="pb-val">${fmt(v)} (${pct}%)</span></div>
          <div class="pb-track"><div class="pb-fill" style="width:${bar}%;background:${col}"></div></div>
        </div>`;
      }).join('')
    : '<p style="font-size:11px;color:#94a3b8;text-align:center;padding:8px 0">No data</p>';
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
  const refCount = d.referral_count ?? 0;
  const svcCount = d.service_count  ?? 0;
  const uptake   = d.uptake         ?? 0;
  const total    = d.total          ?? 0;
  document.getElementById('svc-kpi-referred').textContent    = fmt(refCount);
  document.getElementById('svc-kpi-ref-rate').textContent    = (refCount ? Math.round(uptake / refCount * 100) : 0) + '%';
  document.getElementById('svc-kpi-services').textContent    = fmt(svcCount);
  document.getElementById('svc-kpi-svc-rate').textContent    = (svcCount ? Math.round(uptake / svcCount * 100) : 0) + '%';
  document.getElementById('svc-kpi-uptake').textContent      = fmt(uptake);
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

  rc('svcServiceChart',  'bar', d.by_service.map(r  => r[0]), d.by_service.map(r  => r[1]), { single:'#0d9488', legend:false, indexAxis:'y' });
  rc('svcReferralChart', 'bar', d.by_referral.map(r => r[0]), d.by_referral.map(r => r[1]), { single:'#fbbf24', legend:false, indexAxis:'y' });
  rc('svcKeyPopsChart',  'doughnut', d.by_key_pops.map(r => r[0]), d.by_key_pops.map(r => r[1]), {});

  document.getElementById('svc-bars').innerHTML =
    progressBars(d.by_service, d.total, '#0d9488', 'No services data for this period');
}

// ── CALLS ──────────────────────────────────────────────────────────────────────
function updateCalls(p) {
  const s = callStats[p];

  document.getElementById('c-total').textContent    = fmt(s.total);
  document.getElementById('c-inbound').textContent  = fmt(s.inbound);
  document.getElementById('c-outbound').textContent = fmt(s.outbound);
  document.getElementById('c-missed').textContent   = fmt(s.missed);
  document.getElementById('c-answered').textContent = fmt(s.answered);
  document.getElementById('c-avgdur').textContent   = s.avg_dur + 's';

  const tkeys = Object.keys(s.trend);
  const tvals = Object.values(s.trend);
  document.getElementById('calls-trend-lbl').textContent = trendTitle(p, 'Calls');
  rc('callTrendChart', 'bar', trendLabels(p, tkeys), tvals, { accent:'#3b82f6', muted:'#dbeafe' });
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
  btn.classList.add('active-period');

  const fn = { overview:updateOverview, geographic:updateGeographic, demographics:updateDemographics, services:updateServices, calls:updateCalls, trends:updateTrends, social:updateSocial };
  if (fn[section]) fn[section](period);
}

// ── Section switcher ──────────────────────────────────────────────────────────
function showSection(name, btn) {
  document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
  document.querySelectorAll('.sb-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sec-' + name).style.display = 'block';
  btn.classList.add('active');
  // Re-render the active period for the newly visible section
  const activeBtn = document.querySelector(`#sec-${name} .period-btn.active-period`);
  if (activeBtn) {
    const match = activeBtn.getAttribute('onclick').match(/'(\w+)'/g);
    if (match && match[1]) setPeriod(name, match[1].replace(/'/g,''), activeBtn);
  }
}

// ── PHP-determined defaults (server guarantees these periods have data) ───
const TICKET_DEFAULT = @json($ticketDefaultPeriod);
const CALL_DEFAULT   = @json($callDefaultPeriod);

function labelPeriodBtns() {
  const LABELS = { day:'Today', week:'This Week', month:'This Month', year:'This Year' };
  ['overview','geographic','demographics','services','trends','social'].forEach(sec => {
    document.querySelectorAll(`#sec-${sec} .period-btn`).forEach(btn => {
      const m = btn.getAttribute('onclick').match(/'(day|week|month|year)'/);
      if (!m) return;
      const p = m[1], count = periodData[p]?.total ?? 0;
      btn.innerHTML = LABELS[p] + (count > 0 ? ` <span style="font-size:10px;font-weight:400;opacity:.6">(${fmt(count)})</span>` : '');
    });
  });
  document.querySelectorAll('#sec-calls .period-btn').forEach(btn => {
    const m = btn.getAttribute('onclick').match(/'(day|week|month|year)'/);
    if (!m) return;
    const p = m[1], count = callStats[p]?.total ?? 0;
    btn.innerHTML = LABELS[p] + (count > 0 ? ` <span style="font-size:10px;font-weight:400;opacity:.6">(${fmt(count)})</span>` : '');
  });
}

// ── Bootstrap ─────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  if (typeof lucide !== 'undefined') lucide.createIcons();
  labelPeriodBtns();
  console.log('[screen] TICKET_DEFAULT='+TICKET_DEFAULT+' month_total='+periodData['month']?.total+' day_calls='+callStats['day']?.total);
  updateOverview(TICKET_DEFAULT);
  updateGeographic(TICKET_DEFAULT);
  updateDemographics(TICKET_DEFAULT);
  updateServices(TICKET_DEFAULT);
  updateCalls(CALL_DEFAULT);
  updateTrends(TICKET_DEFAULT);
  updateSocial(TICKET_DEFAULT);
});
</script>
</body>
</html>
