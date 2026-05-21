<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="120">
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

@media(max-width:900px){
  .sidebar{display:none}
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

{{-- ══════════════════════════════════ OVERVIEW ══════════════════════════════════ --}}
<div id="sec-overview" class="section">

  <!-- Period selector -->
  <div class="sec-hdr">
    <span class="sec-title">Overview</span>
    <div class="period-wrap">
      <button class="period-btn active-period" onclick="setPeriod('overview','day',this)">Today</button>
      <button class="period-btn" onclick="setPeriod('overview','week',this)">This Week</button>
      <button class="period-btn" onclick="setPeriod('overview','month',this)">This Month</button>
      <button class="period-btn" onclick="setPeriod('overview','year',this)">This Year</button>
    </div>
  </div>

  <!-- Top grid -->
  <div class="top-grid">

    <!-- Activity bar chart -->
    <div class="glass">
      <div class="card-hdr">
        <span class="card-title">Activity</span>
        <span class="card-tag" id="ov-trend-label">Today by hour</span>
      </div>
      <div class="ch140"><canvas id="ovTrendChart"></canvas></div>
    </div>

    <!-- Mini stats -->
    <div class="stat-stack">
      <div class="stat-mini">
        <div class="sm-icon" style="background:#eff6ff">📞</div>
        <div><div class="sm-val" id="ov-total">0</div><div class="sm-lbl">Total</div></div>
      </div>
      <div class="stat-mini">
        <div class="sm-icon" style="background:#f0fdf4">✅</div>
        <div><div class="sm-val" id="ov-valid">0</div><div class="sm-lbl">Valid</div></div>
      </div>
      <div class="stat-mini">
        <div class="sm-icon" style="background:#fefce8">🔄</div>
        <div><div class="sm-val" id="ov-repeat">0</div><div class="sm-lbl">Repeat</div></div>
      </div>
    </div>

    <!-- Donut overview -->
    <div class="glass">
      <div class="card-hdr"><span class="card-title">Overview</span><span class="card-tag">Period share</span></div>
      <div class="donut-wrap">
        <canvas id="ovDonut"></canvas>
        <div class="donut-center">
          <div class="donut-pct" id="ov-pct">0%</div>
          <div class="donut-sub">Valid</div>
        </div>
      </div>
      <div class="ov-rows">
        <div class="ov-row"><span class="ov-dot" style="background:#3b82f6"></span><span class="ov-lbl">Valid</span><span class="ov-val" id="ov-ov-valid">0</span><span class="ov-chg up" id="ov-ov-vpct"></span></div>
        <div class="ov-row"><span class="ov-dot" style="background:#fbbf24"></span><span class="ov-lbl">Repeat</span><span class="ov-val" id="ov-ov-repeat">0</span><span class="ov-chg muted" id="ov-ov-rpct"></span></div>
        <div class="ov-row"><span class="ov-dot" style="background:#f87171"></span><span class="ov-lbl">Immediate Action</span><span class="ov-val" id="ov-ov-imm">0</span><span class="ov-chg muted" id="ov-ov-ipct"></span></div>
      </div>
    </div>
  </div><!-- /top-grid -->

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
        <div style="font-size:20px;font-weight:800;color:#0f172a" id="ov-uptake">0</div>
        <div style="font-size:10px;color:#94a3b8;margin-top:2px" id="ov-uptake-sub"></div>
      </div>
    </div>

    <!-- Output -->
    <div class="glass" style="position:relative;overflow:hidden">
      <div class="card-title" style="margin-bottom:10px">Immediate Actions</div>
      <div class="out-val" id="ov-imm">0</div>
      <div class="out-lbl">Requiring immediate action</div>
      <span class="out-badge" id="ov-imm-badge">All Clear</span>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9">
        <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Valid Rate</div>
        <div style="font-size:20px;font-weight:800;color:#16a34a" id="ov-vrate">0%</div>
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
      <button class="period-btn active-period" onclick="setPeriod('geographic','day',this)">Today</button>
      <button class="period-btn" onclick="setPeriod('geographic','week',this)">This Week</button>
      <button class="period-btn" onclick="setPeriod('geographic','month',this)">This Month</button>
      <button class="period-btn" onclick="setPeriod('geographic','year',this)">This Year</button>
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
      <button class="period-btn active-period" onclick="setPeriod('demographics','day',this)">Today</button>
      <button class="period-btn" onclick="setPeriod('demographics','week',this)">This Week</button>
      <button class="period-btn" onclick="setPeriod('demographics','month',this)">This Month</button>
      <button class="period-btn" onclick="setPeriod('demographics','year',this)">This Year</button>
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
      <button class="period-btn active-period" onclick="setPeriod('services','day',this)">Today</button>
      <button class="period-btn" onclick="setPeriod('services','week',this)">This Week</button>
      <button class="period-btn" onclick="setPeriod('services','month',this)">This Month</button>
      <button class="period-btn" onclick="setPeriod('services','year',this)">This Year</button>
    </div>
  </div>
  <div class="g2">
    <div class="s-card"><h3>Top Services Requested</h3><div class="ch220"><canvas id="svcServiceChart"></canvas></div></div>
    <div class="s-card"><h3>Top Referral Destinations</h3><div class="ch220"><canvas id="svcReferralChart"></canvas></div></div>
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
      <button class="period-btn active-period" onclick="setPeriod('calls','day',this)">Today</button>
      <button class="period-btn" onclick="setPeriod('calls','week',this)">This Week</button>
      <button class="period-btn" onclick="setPeriod('calls','month',this)">This Month</button>
      <button class="period-btn" onclick="setPeriod('calls','year',this)">This Year</button>
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
      <button class="period-btn active-period" onclick="setPeriod('trends','day',this)">Today</button>
      <button class="period-btn" onclick="setPeriod('trends','week',this)">This Week</button>
      <button class="period-btn" onclick="setPeriod('trends','month',this)">This Month</button>
      <button class="period-btn" onclick="setPeriod('trends','year',this)">This Year</button>
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

<div class="footer">Helpline Analytics &middot; Auto-refreshes every 2 minutes &middot; {{ now()->format('d M Y') }}</div>
</main>
</div>

<script>
// ── PHP data ──────────────────────────────────────────────────────────────────
const periodData = @json($periodData);
const callStats  = @json($callStats);
const months12   = @json($months);
const last7      = @json($last7);

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

  // KPIs
  document.getElementById('ov-total').textContent  = fmt(d.total);
  document.getElementById('ov-valid').textContent  = fmt(d.valid);
  document.getElementById('ov-repeat').textContent = fmt(d.repeat);

  // Donut
  const vp = t ? Math.round(d.valid/t*100) : 0;
  const rp = t ? Math.round(d.repeat/t*100) : 0;
  const ip = t ? Math.round(d.imm_act/t*100) : 0;
  document.getElementById('ov-pct').textContent         = vp + '%';
  document.getElementById('ov-ov-valid').textContent    = fmt(d.valid);
  document.getElementById('ov-ov-vpct').textContent     = '+' + vp + '%';
  document.getElementById('ov-ov-repeat').textContent   = fmt(d.repeat);
  document.getElementById('ov-ov-rpct').textContent     = rp + '%';
  document.getElementById('ov-ov-imm').textContent      = fmt(d.imm_act);
  document.getElementById('ov-ov-ipct').textContent     = ip + '%';
  document.getElementById('ov-uptake').textContent      = fmt(d.uptake);
  document.getElementById('ov-uptake-sub').textContent  = (d.valid ? ((d.uptake/d.valid)*100).toFixed(1) : 0) + '% of valid';
  document.getElementById('ov-imm').textContent         = fmt(d.imm_act);
  document.getElementById('ov-vrate').textContent       = vp + '%';
  document.getElementById('ov-imm-badge').textContent   = d.imm_act > 0 ? 'Needs Attention' : 'All Clear';

  // Donut chart
  rc('ovDonut', 'doughnut',
    ['Valid','Repeat','Immediate','Other'],
    [vp, rp, ip, Math.max(0, 100 - vp - rp - ip)],
    { colors: ['#3b82f6','#fbbf24','#f87171','#e2e8f0'], legend: false }
  );

  // Trend chart
  const tkeys  = Object.keys(d.trend);
  const tvals  = Object.values(d.trend);
  const tlbls  = trendLabels(p, tkeys);
  document.getElementById('ov-trend-label').textContent = trendTitle(p, 'Interactions');
  rc('ovTrendChart', 'bar', tlbls, tvals, { accent: '#3b82f6', muted: '#dbeafe' });

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

// ── SERVICES ───────────────────────────────────────────────────────────────────
function updateServices(p) {
  const d = periodData[p];

  rc('svcServiceChart',  'bar', d.by_service.map(r  => r[0]), d.by_service.map(r  => r[1]), { single:'#0d9488', legend:false, indexAxis:'y' });
  rc('svcReferralChart', 'bar', d.by_referral.map(r => r[0]), d.by_referral.map(r => r[1]), { single:'#fbbf24', legend:false, indexAxis:'y' });

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

  const fn = { overview:updateOverview, geographic:updateGeographic, demographics:updateDemographics, services:updateServices, calls:updateCalls, trends:updateTrends };
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

// ── Bootstrap: initialise all sections with 'day' ────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  lucide.createIcons();
  updateOverview('day');
  updateGeographic('day');
  updateDemographics('day');
  updateServices('day');
  updateCalls('day');
  updateTrends('day');
});
</script>
</body>
</html>
