<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="120">
<title>Helpline Analytics Dashboard</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --primary:   #1a3a5c;
    --secondary: #2a6db5;
    --accent:    #f4a261;
    --danger:    #e63946;
    --success:   #2d9b4e;
    --purple:    #7b2d8b;
    --teal:      #0d7377;
    --bg:        #f0f4f8;
    --card:      #ffffff;
    --text:      #1a2332;
    --muted:     #6b7a8d;
    --border:    #e2e8f0;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

  .header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white; padding: 28px 40px;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
  }
  .header h1 { font-family: 'DM Serif Display', serif; font-size: 26px; line-height: 1.2; }
  .header .org { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; opacity: 0.75; margin-bottom: 4px; }
  .header .sub { font-size: 13px; opacity: 0.8; margin-top: 4px; }
  .header-badge { background: rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 20px; text-align: right; }
  .header-badge .val { font-size: 28px; font-weight: 700; font-family: 'DM Serif Display', serif; }
  .header-badge .lbl { font-size: 11px; opacity: 0.75; }
  .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #4ade80; margin-right: 5px; animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

  .nav { background: white; border-bottom: 2px solid var(--border); padding: 0 32px; display: flex; gap: 0; overflow-x: auto; position: sticky; top: 0; z-index: 10; }
  .nav button {
    padding: 14px 20px; border: none; background: none; cursor: pointer;
    font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 13px;
    color: var(--muted); border-bottom: 3px solid transparent; white-space: nowrap;
    transition: all .2s; margin-bottom: -2px;
  }
  .nav button.active { color: var(--secondary); border-bottom-color: var(--secondary); }
  .nav button:hover:not(.active) { color: var(--primary); }

  .content { padding: 28px 40px; max-width: 1400px; margin: 0 auto; }
  .tab { display: none; }
  .tab.active { display: block; }

  .section-hdr { margin-bottom: 20px; }
  .section-hdr h2 { font-family: 'DM Serif Display', serif; font-size: 20px; color: var(--primary); border-bottom: 3px solid var(--secondary); padding-bottom: 8px; display: inline-block; }
  .section-hdr p { font-size: 13px; color: var(--muted); margin-top: 6px; }

  .kpi-row { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px; }
  .kpi {
    background: white; border-radius: 12px; padding: 20px 22px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07); flex: 1; min-width: 150px;
    border-left: 5px solid var(--secondary); transition: transform .2s;
  }
  .kpi:hover { transform: translateY(-2px); }
  .kpi .icon { font-size: 22px; margin-bottom: 6px; }
  .kpi .value { font-family: 'DM Serif Display', serif; font-size: 32px; line-height: 1; }
  .kpi .label { font-size: 12px; font-weight: 600; color: #555; margin-top: 4px; }
  .kpi .sub { font-size: 11px; color: var(--muted); margin-top: 2px; }

  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
  .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px; }

  .card { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 2px 12px rgba(0,0,0,.07); margin-bottom: 0; }
  .card h3 { font-size: 15px; font-weight: 700; color: var(--primary); margin-bottom: 16px; }

  .prog-bar { margin-bottom: 10px; }
  .prog-hdr { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 3px; }
  .prog-lbl { font-weight: 600; color: #444; }
  .prog-val { font-weight: 700; }
  .prog-track { background: #eee; border-radius: 4px; height: 8px; }
  .prog-fill { height: 100%; border-radius: 4px; transition: width .6s ease; }

  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  thead tr { background: var(--primary); color: white; }
  th { padding: 10px 12px; text-align: left; font-weight: 700; font-size: 12px; white-space: nowrap; }
  td { padding: 9px 12px; border-bottom: 1px solid #eee; }
  tbody tr:nth-child(even) { background: #f9f9f9; }
  .tbl-wrap { overflow-x: auto; }

  .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }

  footer { background: var(--primary); color: rgba(255,255,255,.6); text-align: center; padding: 14px; font-size: 12px; margin-top: 32px; }

  @media (max-width: 768px) {
    .content { padding: 16px; }
    .header { padding: 20px 16px; }
    .nav { padding: 0 12px; }
    .grid-2, .grid-3 { grid-template-columns: 1fr; }
    .kpi-row { flex-direction: column; }
  }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="org">National Youth Helpline</div>
    <h1>Helpline Analytics Dashboard</h1>
    <p class="sub">
      <span class="live-dot"></span>Live data &middot;
      Last updated: <?php echo e($lastUpdated ? \Carbon\Carbon::parse($lastUpdated)->format('d M Y H:i') : 'N/A'); ?>

    </p>
  </div>
  <div class="header-badge">
    <div class="val"><?php echo e(number_format($total)); ?></div>
    <div class="lbl">Total Interactions Logged</div>
  </div>
</div>

<nav class="nav">
  <button class="active" onclick="showTab('overview',this)">📊 Overview</button>
  <button onclick="showTab('geographic',this)">🗺️ Geographic</button>
  <button onclick="showTab('demographics',this)">👥 Demographics</button>
  <button onclick="showTab('services',this)">🔗 Services</button>
  <button onclick="showTab('calls',this)">📞 Call Details</button>
  <button onclick="showTab('trends',this)">📈 Trends</button>
</nav>

<div class="content">


<div id="tab-overview" class="tab active">
  <div class="section-hdr">
    <h2>Overview</h2>
    <p>Summary of all helpline interactions across all channels</p>
  </div>

  <div class="kpi-row">
    <div class="kpi" style="border-left-color:var(--secondary)">
      <div class="icon">📞</div>
      <div class="value" style="color:var(--secondary)"><?php echo e(number_format($total)); ?></div>
      <div class="label">Total Interactions</div>
      <div class="sub">All channels</div>
    </div>
    <div class="kpi" style="border-left-color:var(--success)">
      <div class="icon">✅</div>
      <div class="value" style="color:var(--success)"><?php echo e(number_format($validTotal)); ?></div>
      <div class="label">Valid Interactions</div>
      <div class="sub"><?php echo e($total ? round($validTotal/$total*100,1) : 0); ?>% of total</div>
    </div>
    <div class="kpi" style="border-left-color:var(--accent)">
      <div class="icon">🔄</div>
      <div class="value" style="color:var(--accent)"><?php echo e(number_format($repeatTotal)); ?></div>
      <div class="label">Repeat Callers</div>
      <div class="sub"><?php echo e($total ? round($repeatTotal/$total*100,1) : 0); ?>% repeat rate</div>
    </div>
    <div class="kpi" style="border-left-color:var(--teal)">
      <div class="icon">🤝</div>
      <div class="value" style="color:var(--teal)"><?php echo e(number_format($uptakeTotal)); ?></div>
      <div class="label">Uptake Confirmed</div>
      <div class="sub"><?php echo e($validTotal ? round($uptakeTotal/$validTotal*100,1) : 0); ?>% of valid</div>
    </div>
    <div class="kpi" style="border-left-color:var(--danger)">
      <div class="icon">🚨</div>
      <div class="value" style="color:var(--danger)"><?php echo e(number_format($immediateAct)); ?></div>
      <div class="label">Immediate Action</div>
      <div class="sub"><?php echo e($total ? round($immediateAct/$total*100,1) : 0); ?>% of total</div>
    </div>
  </div>

  <div class="grid-2">
    <div class="card">
      <h3>Ticket Status Breakdown</h3>
      <canvas id="statusChart" height="220"></canvas>
    </div>
    <div class="card">
      <h3>Priority Distribution</h3>
      <canvas id="priorityChart" height="220"></canvas>
    </div>
  </div>

  <div class="card">
    <h3>Communication Channel Breakdown</h3>
    <canvas id="modeChart" height="100"></canvas>
  </div>
</div>


<div id="tab-geographic" class="tab">
  <div class="section-hdr">
    <h2>Geographic Distribution</h2>
    <p>Interactions broken down by province across Zimbabwe</p>
  </div>

  <div class="grid-2">
    <div class="card">
      <h3>Calls by Province</h3>
      <canvas id="provinceBarChart" height="280"></canvas>
    </div>
    <div class="card">
      <h3>Province Share</h3>
      <canvas id="provincePieChart" height="280"></canvas>
    </div>
  </div>

  <div class="card">
    <h3>Province Details</h3>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Province</th>
            <th>Interactions</th>
            <th>% of Total</th>
            <th>Coverage</th>
          </tr>
        </thead>
        <tbody>
          <?php $rank = 0; $maxProv = $byProvince->first()?->cnt ?? 1; ?>
          <?php $__currentLoopData = $byProvince; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $rank++; $pct = $total ? round($row->cnt/$total*100,1) : 0; $bar = $maxProv ? round($row->cnt/$maxProv*100) : 0; ?>
          <tr>
            <td><?php echo e($rank); ?></td>
            <td><strong><?php echo e($row->province); ?></strong></td>
            <td><?php echo e(number_format($row->cnt)); ?></td>
            <td><?php echo e($pct); ?>%</td>
            <td style="min-width:120px">
              <div class="prog-track"><div class="prog-fill" style="width:<?php echo e($bar); ?>%;background:var(--secondary)"></div></div>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php if($byProvince->isEmpty()): ?>
          <tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px">No province data yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<div id="tab-demographics" class="tab">
  <div class="section-hdr">
    <h2>Demographics</h2>
    <p>Caller gender, age, marital status, and key population groups</p>
  </div>

  <div class="grid-3">
    <div class="card">
      <h3>Gender</h3>
      <canvas id="genderChart" height="220"></canvas>
    </div>
    <div class="card">
      <h3>Age Groups</h3>
      <canvas id="ageChart" height="220"></canvas>
    </div>
    <div class="card">
      <h3>Marital Status</h3>
      <canvas id="maritalChart" height="220"></canvas>
    </div>
  </div>

  <div class="card">
    <h3>Key Population Groups (Key Pops)</h3>
    <?php $maxKp = $byKeyPops->first()?->cnt ?? 1; ?>
    <?php $__currentLoopData = $byKeyPops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php $pct = $total ? round($kp->cnt/$total*100,1) : 0; $bar = $maxKp ? round($kp->cnt/$maxKp*100) : 0; ?>
    <div class="prog-bar">
      <div class="prog-hdr">
        <span class="prog-lbl"><?php echo e($kp->key_pops); ?></span>
        <span class="prog-val"><?php echo e(number_format($kp->cnt)); ?> <span style="color:var(--muted);font-weight:400">(<?php echo e($pct); ?>%)</span></span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:<?php echo e($bar); ?>%;background:var(--purple)"></div></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if($byKeyPops->isEmpty()): ?>
    <p style="color:#aaa;font-size:13px;text-align:center;padding:16px 0">No key pops data yet</p>
    <?php endif; ?>
  </div>
</div>


<div id="tab-services" class="tab">
  <div class="section-hdr">
    <h2>Services &amp; Referrals</h2>
    <p>Services requested and referral destinations</p>
  </div>

  <div class="grid-2">
    <div class="card">
      <h3>Top Services Requested</h3>
      <canvas id="serviceChart" height="280"></canvas>
    </div>
    <div class="card">
      <h3>Top Referral Destinations</h3>
      <canvas id="referralChart" height="280"></canvas>
    </div>
  </div>

  <div class="card">
    <h3>Services Detail</h3>
    <?php $maxSvc = $byService->first()?->cnt ?? 1; ?>
    <?php $__currentLoopData = $byService; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $svc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php $pct = $total ? round($svc->cnt/$total*100,1) : 0; $bar = $maxSvc ? round($svc->cnt/$maxSvc*100) : 0; ?>
    <div class="prog-bar">
      <div class="prog-hdr">
        <span class="prog-lbl"><?php echo e($svc->services_requested); ?></span>
        <span class="prog-val"><?php echo e(number_format($svc->cnt)); ?> <span style="color:var(--muted);font-weight:400">(<?php echo e($pct); ?>%)</span></span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:<?php echo e($bar); ?>%;background:var(--teal)"></div></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if($byService->isEmpty()): ?>
    <p style="color:#aaa;font-size:13px;text-align:center;padding:16px 0">No services data yet</p>
    <?php endif; ?>
  </div>
</div>


<div id="tab-calls" class="tab">
  <div class="section-hdr">
    <h2>Call Details</h2>
    <p>Validity, purpose, and action flags</p>
  </div>

  <div class="kpi-row">
    <div class="kpi" style="border-left-color:var(--success)">
      <div class="icon">✅</div>
      <div class="value" style="color:var(--success)"><?php echo e(number_format($byValidity->get('valid', 0))); ?></div>
      <div class="label">Valid Calls</div>
    </div>
    <div class="kpi" style="border-left-color:var(--danger)">
      <div class="icon">❌</div>
      <div class="value" style="color:var(--danger)"><?php echo e(number_format($byValidity->get('invalid', 0))); ?></div>
      <div class="label">Invalid Calls</div>
    </div>
    <div class="kpi" style="border-left-color:var(--accent)">
      <div class="icon">🔄</div>
      <div class="value" style="color:var(--accent)"><?php echo e(number_format($repeatTotal)); ?></div>
      <div class="label">Repeat Callers</div>
    </div>
    <div class="kpi" style="border-left-color:var(--danger)">
      <div class="icon">🚨</div>
      <div class="value" style="color:var(--danger)"><?php echo e(number_format($immediateAct)); ?></div>
      <div class="label">Immediate Action Required</div>
    </div>
  </div>

  <div class="card">
    <h3>Purpose of Call (Top 10)</h3>
    <?php $maxPurp = $byPurpose->first()?->cnt ?? 1; ?>
    <?php $__currentLoopData = $byPurpose; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $purp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php $pct = $total ? round($purp->cnt/$total*100,1) : 0; $bar = $maxPurp ? round($purp->cnt/$maxPurp*100) : 0; ?>
    <div class="prog-bar">
      <div class="prog-hdr">
        <span class="prog-lbl"><?php echo e($purp->purpose_of_call); ?></span>
        <span class="prog-val"><?php echo e(number_format($purp->cnt)); ?> <span style="color:var(--muted);font-weight:400">(<?php echo e($pct); ?>%)</span></span>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:<?php echo e($bar); ?>%;background:var(--secondary)"></div></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if($byPurpose->isEmpty()): ?>
    <p style="color:#aaa;font-size:13px;text-align:center;padding:16px 0">No purpose data yet</p>
    <?php endif; ?>
  </div>
</div>


<div id="tab-trends" class="tab">
  <div class="section-hdr">
    <h2>Monthly Trends</h2>
    <p>Interaction volume over the last 12 months</p>
  </div>

  <div class="card" style="margin-bottom:20px">
    <h3>Interactions per Month (Last 12 Months)</h3>
    <canvas id="trendChart" height="120"></canvas>
  </div>

  <div class="card">
    <h3>Monthly Breakdown Table</h3>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>Month</th><th>Interactions</th><th>% of 12-month total</th><th>Volume</th></tr>
        </thead>
        <tbody>
          <?php
            $monthArr = $months->toArray();
            $monthSum = array_sum($monthArr);
          ?>
          <?php $__currentLoopData = $monthArr; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ym => $cnt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $label = \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('M Y');
            $pct = $monthSum ? round($cnt/$monthSum*100,1) : 0;
            $bar = $monthSum ? round($cnt/max(array_values($monthArr))*100) : 0;
          ?>
          <tr>
            <td><strong><?php echo e($label); ?></strong></td>
            <td><?php echo e(number_format($cnt)); ?></td>
            <td><?php echo e($pct); ?>%</td>
            <td style="min-width:120px"><div class="prog-track"><div class="prog-fill" style="width:<?php echo e($bar); ?>%;background:var(--secondary)"></div></div></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div>

<footer>
  Helpline Analytics Dashboard &middot; Auto-refreshes every 2 minutes &middot;
  <?php echo e(now()->format('d M Y')); ?>

</footer>

<script>
// ── Data from PHP ─────────────────────────────────────────────────
const byStatus   = <?php echo json_encode($byStatus, 15, 512) ?>;
const byPriority = <?php echo json_encode($byPriority, 15, 512) ?>;
const byMode     = <?php echo json_encode($byMode, 15, 512) ?>;
const byProvince = <?php echo json_encode($byProvince, 15, 512) ?>;
const byGender   = <?php echo json_encode($byGender, 15, 512) ?>;
const ageGroups  = <?php echo json_encode($ageGroups, 15, 512) ?>;
const byMarital  = <?php echo json_encode($byMarital, 15, 512) ?>;
const byService  = <?php echo json_encode($byService, 15, 512) ?>;
const byReferral = <?php echo json_encode($byReferral, 15, 512) ?>;
const months     = <?php echo json_encode($months, 15, 512) ?>;

// ── Color palettes ────────────────────────────────────────────────
const PAL = ['#2a6db5','#f4a261','#2d9b4e','#e63946','#7b2d8b','#0d7377','#f59e0b','#06b6d4','#8b5cf6','#ec4899'];

function makeChart(id, type, labels, data, opts = {}) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  return new Chart(ctx, {
    type,
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: opts.singleColor
          ? Array(labels.length).fill(opts.singleColor).map((c, i) => i === 0 ? c : c + 'bb')
          : PAL.slice(0, labels.length),
        borderColor: opts.line ? '#2a6db5' : 'transparent',
        borderWidth: opts.line ? 2 : 0,
        fill: opts.fill ?? false,
        tension: 0.4,
        pointBackgroundColor: '#2a6db5',
      }],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: opts.legend ?? (type === 'pie' || type === 'doughnut') },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed.toLocaleString()}` } },
      },
      scales: (type === 'bar' || type === 'line') ? {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { ticks: { maxRotation: 35 } },
      } : {},
      ...opts.extra,
    },
  });
}

// ── Status chart ──────────────────────────────────────────────────
const statusLabels = Object.keys(byStatus).map(s => s.replace('_',' '));
makeChart('statusChart', 'doughnut', statusLabels, Object.values(byStatus));

// ── Priority chart ────────────────────────────────────────────────
const priorityOrder = ['low','medium','high','urgent'];
const priorityColors = { low:'#4caf50', medium:'#2a6db5', high:'#f59e0b', urgent:'#e63946' };
makeChart('priorityChart', 'doughnut',
  priorityOrder.filter(p => byPriority[p]),
  priorityOrder.filter(p => byPriority[p]).map(p => byPriority[p]),
  { singleColor: null }
);
// Re-do with custom colors
(function(){
  const ctx = document.getElementById('priorityChart');
  if (!ctx) return;
  Chart.getChart(ctx)?.destroy();
  const labels = priorityOrder.filter(p => byPriority[p]);
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data: labels.map(p => byPriority[p]), backgroundColor: labels.map(p => priorityColors[p]) }],
    },
    options: { responsive: true, plugins: { legend: { display: true } } },
  });
})();

// ── Mode of communication ─────────────────────────────────────────
const modeLabels = byMode.map(r => r.mode_of_communication.replace('_',' '));
makeChart('modeChart', 'bar', modeLabels, byMode.map(r => r.cnt),
  { singleColor: '#2a6db5', legend: false, extra: { indexAxis: 'x' } });

// ── Province bar + pie ────────────────────────────────────────────
const provLabels = byProvince.map(r => r.province);
const provData   = byProvince.map(r => r.cnt);
makeChart('provinceBarChart', 'bar', provLabels, provData, { singleColor: '#2a6db5', legend: false });
makeChart('provincePieChart', 'pie',  provLabels, provData);

// ── Gender ────────────────────────────────────────────────────────
const genderMap = { male:'Male', female:'Female', other:'Other', prefer_not_to_say:'Prefer not to say' };
const genderLabels = byGender.map(r => genderMap[r.caller_gender] ?? r.caller_gender);
makeChart('genderChart', 'doughnut', genderLabels, byGender.map(r => r.cnt));

// ── Age groups ────────────────────────────────────────────────────
makeChart('ageChart', 'bar', Object.keys(ageGroups), Object.values(ageGroups),
  { singleColor: '#7b2d8b', legend: false });

// ── Marital status ────────────────────────────────────────────────
const maritalLabels = byMarital.map(r => r.caller_marital_status.replace('_',' '));
makeChart('maritalChart', 'doughnut', maritalLabels, byMarital.map(r => r.cnt));

// ── Services ──────────────────────────────────────────────────────
makeChart('serviceChart', 'bar',
  byService.map(r => r.services_requested), byService.map(r => r.cnt),
  { singleColor: '#0d7377', legend: false, extra: { indexAxis: 'y' } });

// ── Referrals ─────────────────────────────────────────────────────
makeChart('referralChart', 'bar',
  byReferral.map(r => r.referred_to), byReferral.map(r => r.cnt),
  { singleColor: '#f4a261', legend: false, extra: { indexAxis: 'y' } });

// ── Monthly trend ─────────────────────────────────────────────────
const monthLabels = Object.keys(months).map(ym => {
  const [y, m] = ym.split('-');
  return new Date(+y, +m-1).toLocaleString('en-ZW', { month: 'short', year: '2-digit' });
});
makeChart('trendChart', 'line', monthLabels, Object.values(months),
  { line: true, fill: true, legend: false });

// ── Tab switching ─────────────────────────────────────────────────
function showTab(name, btn) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.nav button').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}
</script>
</body>
</html>
<?php /**PATH C:\Users\Mazarura\crm-pbx\backend\resources\views/public-dashboard.blade.php ENDPATH**/ ?>