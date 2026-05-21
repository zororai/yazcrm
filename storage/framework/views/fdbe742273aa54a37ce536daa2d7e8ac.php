<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="120">
<title>Helpline Analytics</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}

body{
  font-family:'Inter',sans-serif;
  background:#f0f4f8;
  color:#1e293b;
  min-height:100vh;
  overflow-x:hidden;
}

body::before{
  content:'';
  position:fixed;top:0;left:0;right:0;bottom:0;
  background:
    radial-gradient(ellipse 60% 50% at 10% 10%, rgba(219,234,254,0.8) 0%, transparent 60%),
    radial-gradient(ellipse 50% 40% at 90% 90%, rgba(209,250,229,0.5) 0%, transparent 60%);
  pointer-events:none;z-index:0;
}

.app{display:flex;min-height:100vh;position:relative;z-index:1}

/* ── Sidebar ──────────────────────────────────────────────── */
.sidebar{
  width:72px;flex-shrink:0;
  background:#fff;
  border-right:1px solid #e2e8f0;
  display:flex;flex-direction:column;align-items:center;
  padding:20px 0;gap:6px;
  position:sticky;top:0;height:100vh;
  box-shadow:2px 0 12px rgba(0,0,0,0.04);
}
.sb-logo{
  width:40px;height:40px;
  background:linear-gradient(135deg,#3b82f6,#1e40af);
  border-radius:12px;display:flex;align-items:center;
  justify-content:center;font-size:18px;margin-bottom:20px;
}
.sb-btn{
  width:44px;height:44px;border:none;
  background:transparent;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:17px;
  color:#94a3b8;transition:all .2s;
}
.sb-btn:hover{background:#f1f5f9;color:#475569}
.sb-btn.active{background:#eff6ff;color:#3b82f6}
.sb-divider{width:30px;height:1px;background:#e2e8f0;margin:6px 0}

/* ── Main ────────────────────────────────────────────────── */
.main{flex:1;padding:28px 28px 20px;display:flex;flex-direction:column;gap:18px;min-width:0;overflow-y:auto}

/* ── Glass card ──────────────────────────────────────────── */
.glass{
  background:#fff;
  border:1px solid #e2e8f0;
  border-radius:20px;padding:22px;
  box-shadow:0 2px 12px rgba(0,0,0,0.05);
}

/* ── Header ──────────────────────────────────────────────── */
.page-hdr{display:flex;align-items:flex-start;justify-content:space-between}
.page-title{font-size:24px;font-weight:800;color:#0f172a;letter-spacing:-.5px}
.page-sub{font-size:12px;color:#94a3b8;margin-top:3px}
.hdr-right{display:flex;align-items:center;gap:10px}
.live-pill{
  display:flex;align-items:center;gap:6px;
  background:#f0fdf4;
  border:1px solid #bbf7d0;
  border-radius:20px;padding:5px 13px;
  font-size:11px;color:#16a34a;font-weight:600;
}
.live-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.total-pill{
  background:#fff;
  border:1px solid #e2e8f0;
  border-radius:16px;padding:8px 16px;text-align:right;
  box-shadow:0 1px 6px rgba(0,0,0,0.06);
}
.total-pill .tv{font-size:22px;font-weight:800;color:#0f172a}
.total-pill .tl{font-size:10px;color:#94a3b8;margin-top:1px}

/* ── Top grid ────────────────────────────────────────────── */
.top-grid{display:grid;grid-template-columns:1fr 175px 270px;gap:16px}

.card-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.card-title{font-size:13px;font-weight:600;color:#374151}
.card-tag{
  font-size:11px;color:#94a3b8;
  background:#f8fafc;
  border:1px solid #e2e8f0;
  border-radius:8px;padding:3px 10px;
  display:flex;align-items:center;gap:5px;cursor:pointer;
  font-family:'Inter',sans-serif;
}

/* bar chart area */
.chart-wrap{height:140px;position:relative}

/* ── Stat mini stack ─────────────────────────────────────── */
.stat-stack{display:flex;flex-direction:column;gap:10px}
.stat-mini{
  background:#fff;
  border:1px solid #e2e8f0;
  border-radius:16px;padding:13px 15px;
  display:flex;align-items:center;gap:11px;
  box-shadow:0 1px 6px rgba(0,0,0,0.04);
}
.sm-icon{
  width:34px;height:34px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:15px;flex-shrink:0;
}
.sm-val{font-size:15px;font-weight:700;color:#0f172a;line-height:1.1}
.sm-lbl{font-size:10px;color:#94a3b8;margin-top:2px}

/* ── Overview card ───────────────────────────────────────── */
.donut-wrap{position:relative;width:120px;height:120px;margin:0 auto 14px}
.donut-center{
  position:absolute;top:50%;left:50%;
  transform:translate(-50%,-50%);text-align:center;
}
.donut-pct{font-size:20px;font-weight:800;color:#0f172a;line-height:1}
.donut-sub{font-size:10px;color:#94a3b8;margin-top:2px}
.ov-rows{display:flex;flex-direction:column;gap:9px}
.ov-row{display:flex;align-items:center;gap:8px}
.ov-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.ov-lbl{flex:1;font-size:11px;color:#64748b}
.ov-val{font-size:12px;font-weight:700;color:#0f172a}
.ov-chg{font-size:10px;margin-left:4px;font-weight:500}
.ov-chg.up{color:#16a34a}
.ov-chg.neutral{color:#94a3b8}

/* ── Bottom grid ─────────────────────────────────────────── */
.bottom-grid{display:grid;grid-template-columns:1fr 230px 170px;gap:16px}

/* challenges */
.ch-item{
  display:flex;align-items:center;gap:12px;
  padding:11px 0;border-bottom:1px solid #f1f5f9;
}
.ch-item:last-child{border-bottom:none;padding-bottom:0}
.ch-ring{
  width:24px;height:24px;border-radius:50%;
  border:2px solid #e2e8f0;
  display:flex;align-items:center;justify-content:center;
  font-size:10px;flex-shrink:0;color:#cbd5e1;
}
.ch-ring.done{background:#22c55e;border-color:#22c55e;color:#fff;font-size:12px}
.ch-body{flex:1;min-width:0}
.ch-name{font-size:12px;font-weight:500;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ch-prog{font-size:10px;color:#94a3b8;margin-top:2px}
.ch-badge{
  font-size:10px;font-weight:600;padding:3px 10px;
  border-radius:20px;white-space:nowrap;flex-shrink:0;
}
.badge-go{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.badge-done{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe}
.badge-alert{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}

/* calendar */
.cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.cal-nav span{font-size:13px;font-weight:700;color:#0f172a}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;text-align:center}
.cal-dn{font-size:9px;color:#94a3b8;font-weight:600;padding:0 0 6px;text-transform:uppercase}
.cal-d{
  font-size:11px;color:#64748b;
  padding:5px 2px;border-radius:8px;font-weight:400;
}
.cal-d.today{background:#3b82f6;color:#fff;font-weight:700}
.cal-d.empty{color:transparent;pointer-events:none}

/* output */
.out-val{font-size:42px;font-weight:900;color:#0f172a;line-height:1;margin-bottom:4px}
.out-lbl{font-size:11px;color:#94a3b8;margin-bottom:14px}
.out-badge{
  display:inline-block;
  background:#f0fdf4;border:1px solid #bbf7d0;
  color:#16a34a;border-radius:20px;
  padding:4px 14px;font-size:11px;font-weight:600;
}
.out-img{font-size:48px;opacity:.08;position:absolute;bottom:14px;right:16px}

/* ── Section cards ────────────────────────────────────────── */
.section-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.section-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px}
.s-card{
  background:#fff;border:1px solid #e2e8f0;
  border-radius:16px;padding:20px;
  box-shadow:0 1px 6px rgba(0,0,0,0.04);
}
.s-card h3{font-size:13px;font-weight:600;color:#374151;margin-bottom:14px}
.chart-h{height:220px;position:relative}
.chart-h2{height:120px;position:relative}

/* progress bars */
.pb{margin-bottom:11px}
.pb-hdr{display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px}
.pb-lbl{color:#475569;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:72%}
.pb-val{color:#94a3b8;font-size:10px}
.pb-track{background:#f1f5f9;border-radius:3px;height:5px}
.pb-fill{height:100%;border-radius:3px;transition:width .6s}

/* table */
table{width:100%;border-collapse:collapse;font-size:12px}
th{padding:9px 12px;text-align:left;font-weight:600;font-size:10px;
   color:#94a3b8;border-bottom:1px solid #f1f5f9;
   text-transform:uppercase;letter-spacing:.5px}
td{padding:8px 12px;border-bottom:1px solid #f8fafc;color:#475569}
tr:hover td{background:#f8fafc}
.tbl-wrap{overflow-x:auto}

/* kpi row */
.kpi-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.kpi{
  flex:1;min-width:130px;
  background:#fff;border:1px solid #e2e8f0;
  border-radius:16px;padding:18px;
  box-shadow:0 1px 6px rgba(0,0,0,0.04);
}
.kpi-icon{font-size:18px;margin-bottom:8px}
.kpi-val{font-size:26px;font-weight:800;color:#0f172a;line-height:1}
.kpi-lbl{font-size:11px;color:#94a3b8;margin-top:4px;font-weight:500}
.kpi-sub{font-size:10px;color:#cbd5e1;margin-top:2px}

/* period buttons */
.period-btn{
  padding:6px 14px;border:none;background:transparent;
  border-radius:8px;cursor:pointer;
  font-family:'Inter',sans-serif;font-size:12px;font-weight:500;
  color:#64748b;transition:all .15s;white-space:nowrap;
}
.period-btn:hover{color:#1e293b}
.period-btn.active-period{background:#fff;color:#1e293b;box-shadow:0 1px 4px rgba(0,0,0,0.1);font-weight:600}

/* footer */
.footer{text-align:center;padding:12px;font-size:10px;color:#94a3b8;border-top:1px solid #e2e8f0}

@media(max-width:900px){
  .sidebar{display:none}
  .top-grid,.bottom-grid{grid-template-columns:1fr}
  .section-grid-2,.section-grid-3{grid-template-columns:1fr}
  .main{padding:16px}
}
</style>
</head>
<body>

<?php
  $validPct  = $total  ? round($validTotal / $total * 100)  : 0;
  $repeatPct = $total  ? round($repeatTotal / $total * 100) : 0;
  $immPct    = $total  ? round($immediateAct / $total * 100): 0;
  $monthArr  = $months->toArray();
  $last7     = array_slice($monthArr, -7, 7, true);
  $monthSum  = array_sum($monthArr);
  $maxMonth  = max(array_values($monthArr) ?: [1]);
  $topPurpose3 = $byPurpose->take(3);
  $maxPurp   = $byPurpose->first()?->cnt ?? 1;
  $now       = \Carbon\Carbon::now('Africa/Johannesburg');
  $today     = $now->day;
  $calYear   = $now->year;
  $calMonth  = $now->month;
  $calMonthName = $now->format('F Y');
  $calFirstDay  = $now->copy()->startOfMonth()->dayOfWeek; // 0=Sun
  $calDays      = $now->daysInMonth;
?>

<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sb-logo">📊</div>
    <button class="sb-btn active" onclick="showSection('overview',this)" title="Overview">🏠</button>
    <button class="sb-btn" onclick="showSection('geographic',this)" title="Geographic">🗺️</button>
    <button class="sb-btn" onclick="showSection('demographics',this)" title="Demographics">👥</button>
    <button class="sb-btn" onclick="showSection('services',this)" title="Services">🔗</button>
    <button class="sb-btn" onclick="showSection('calls',this)" title="Call Details">📞</button>
    <button class="sb-btn" onclick="showSection('trends',this)" title="Trends">📈</button>
    <div class="sb-divider" style="margin-top:auto"></div>
    <div style="font-size:18px;opacity:.2">🌿</div>
  </aside>

  <!-- Main -->
  <main class="main">

    <!-- ── Header ── -->
    <div class="page-hdr">
      <div>
        <div class="page-title">Helpline Analytics</div>
        <div class="page-sub">
          National Youth Helpline &middot;
          <?php echo e($lastUpdated ? \Carbon\Carbon::parse($lastUpdated)->format('d M Y, H:i') : 'N/A'); ?>

        </div>
      </div>
      <div class="hdr-right">
        <div class="live-pill"><span class="live-dot"></span> Live</div>
        <div class="total-pill">
          <div class="tv"><?php echo e(number_format($total)); ?></div>
          <div class="tl">Total Interactions</div>
        </div>
      </div>
    </div>

    <!-- ═══════════════ OVERVIEW SECTION ═══════════════ -->
    <div id="sec-overview" class="section">

      <!-- Top grid: Activity | Mini-stats | Overview donut -->
      <div class="top-grid">

        <!-- Activity bar chart -->
        <div class="glass">
          <div class="card-hdr">
            <span class="card-title">Activity</span>
            <button class="card-tag">Monthly ∨</button>
          </div>
          <div class="chart-wrap">
            <canvas id="activityChart"></canvas>
          </div>
        </div>

        <!-- 3 mini stat cards -->
        <div class="stat-stack">
          <div class="stat-mini">
            <div class="sm-icon" style="background:rgba(59,130,246,0.15)">📞</div>
            <div>
              <div class="sm-val"><?php echo e(number_format($total)); ?></div>
              <div class="sm-lbl">Total Interactions</div>
            </div>
          </div>
          <div class="stat-mini">
            <div class="sm-icon" style="background:rgba(74,222,128,0.15)">✅</div>
            <div>
              <div class="sm-val"><?php echo e(number_format($validTotal)); ?></div>
              <div class="sm-lbl">Valid Calls</div>
            </div>
          </div>
          <div class="stat-mini">
            <div class="sm-icon" style="background:rgba(251,191,36,0.15)">🔄</div>
            <div>
              <div class="sm-val"><?php echo e(number_format($repeatTotal)); ?></div>
              <div class="sm-lbl">Repeat Callers</div>
            </div>
          </div>
        </div>

        <!-- Overview donut -->
        <div class="glass">
          <div class="card-hdr">
            <span class="card-title">Overview</span>
            <button class="card-tag">All Time ∨</button>
          </div>
          <div class="donut-wrap">
            <canvas id="overviewDonut"></canvas>
            <div class="donut-center">
              <div class="donut-pct"><?php echo e($validPct); ?>%</div>
              <div class="donut-sub">Valid</div>
            </div>
          </div>
          <div class="ov-rows">
            <div class="ov-row">
              <span class="ov-dot" style="background:#3b82f6"></span>
              <span class="ov-lbl">Valid Calls</span>
              <span class="ov-val"><?php echo e(number_format($validTotal)); ?></span>
              <span class="ov-chg up">+<?php echo e($validPct); ?>%</span>
            </div>
            <div class="ov-row">
              <span class="ov-dot" style="background:#fbbf24"></span>
              <span class="ov-lbl">Repeat Callers</span>
              <span class="ov-val"><?php echo e(number_format($repeatTotal)); ?></span>
              <span class="ov-chg neutral"><?php echo e($repeatPct); ?>%</span>
            </div>
            <div class="ov-row">
              <span class="ov-dot" style="background:#f87171"></span>
              <span class="ov-lbl">Immediate Action</span>
              <span class="ov-val"><?php echo e(number_format($immediateAct)); ?></span>
              <span class="ov-chg neutral"><?php echo e($immPct); ?>%</span>
            </div>
          </div>
        </div>

      </div><!-- /top-grid -->

      <!-- Bottom grid: Challenges | Calendar | Output -->
      <div class="bottom-grid">

        <!-- Top purposes (Challenges style) -->
        <div class="glass">
          <div class="card-hdr">
            <span class="card-title">Top Purposes of Call</span>
          </div>
          <?php $__empty_1 = true; $__currentLoopData = $topPurpose3; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $purp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $pct3 = $maxPurp ? round($purp->cnt / $maxPurp * 100) : 0;
              $badges = ['badge-go','badge-done','badge-alert'];
              $labels = ['Active','Common','Flagged'];
              $checks = ['○','✓','○'];
              $rings  = ['','done',''];
            ?>
            <div class="ch-item">
              <div class="ch-ring <?php echo e($rings[$i]); ?>"><?php echo e($i === 1 ? '✓' : '○'); ?></div>
              <div class="ch-body">
                <div class="ch-name"><?php echo e($purp->purpose_of_call); ?></div>
                <div class="ch-prog"><?php echo e(number_format($purp->cnt)); ?> / <?php echo e(number_format($total)); ?> interactions</div>
              </div>
              <span class="ch-badge <?php echo e($badges[$i]); ?>"><?php echo e($labels[$i]); ?></span>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p style="font-size:12px;color:#cbd5e1;text-align:center;padding:20px 0">No purpose data yet</p>
          <?php endif; ?>

          <?php if($byStatus->isNotEmpty()): ?>
            <div style="margin-top:16px">
              <div class="card-title" style="margin-bottom:10px">Status Breakdown</div>
              <?php $__currentLoopData = $byStatus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $cnt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $sPct = $total ? round($cnt / $total * 100) : 0;
                  $colors = ['open'=>'#60a5fa','in_progress'=>'#fbbf24','closed'=>'#4ade80','resolved'=>'#34d399'];
                  $sc = $colors[$status] ?? '#94a3b8';
                ?>
                <div class="pb">
                  <div class="pb-hdr">
                    <span class="pb-lbl"><?php echo e(ucfirst(str_replace('_',' ',$status))); ?></span>
                    <span class="pb-val"><?php echo e(number_format($cnt)); ?> (<?php echo e($sPct); ?>%)</span>
                  </div>
                  <div class="pb-track">
                    <div class="pb-fill" style="width:<?php echo e($sPct); ?>%;background:<?php echo e($sc); ?>"></div>
                  </div>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Calendar -->
        <div class="glass">
          <div class="cal-nav">
            <span><?php echo e($calMonthName); ?></span>
          </div>
          <div class="cal-grid">
            <?php $__currentLoopData = ['Su','Mo','Tu','We','Th','Fr','Sa']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div class="cal-dn"><?php echo e($dn); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php for($e = 0; $e < $calFirstDay; $e++): ?>
              <div class="cal-d empty">0</div>
            <?php endfor; ?>
            <?php for($d = 1; $d <= $calDays; $d++): ?>
              <div class="cal-d <?php echo e($d === $today ? 'today' : ''); ?>"><?php echo e($d); ?></div>
            <?php endfor; ?>
          </div>

          <!-- Uptake stat below calendar -->
          <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.06)">
            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Uptake Confirmed</div>
            <div style="font-size:22px;font-weight:800;color:#0f172a"><?php echo e(number_format($uptakeTotal)); ?></div>
            <div style="font-size:10px;color:#94a3b8;margin-top:2px">
              <?php echo e($validTotal ? round($uptakeTotal/$validTotal*100,1) : 0); ?>% of valid calls
            </div>
          </div>
        </div>

        <!-- Output -->
        <div class="glass" style="position:relative;overflow:hidden">
          <div class="card-title" style="margin-bottom:12px">Immediate Actions</div>
          <div class="out-val"><?php echo e(number_format($immediateAct)); ?></div>
          <div class="out-lbl">Cases requiring immediate action</div>
          <span class="out-badge"><?php echo e($immediateAct > 0 ? 'Needs Attention' : 'All Clear'); ?></span>

          <div style="margin-top:18px;padding-top:14px;border-top:1px solid rgba(255,255,255,.06)">
            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Valid Rate</div>
            <div style="font-size:22px;font-weight:800;color:#16a34a"><?php echo e($validPct); ?>%</div>
            <div style="font-size:10px;color:#94a3b8;margin-top:2px">of all interactions</div>
          </div>

          <div class="out-img">🚨</div>
        </div>

      </div><!-- /bottom-grid -->

    </div><!-- /sec-overview -->

    <!-- ═══════════════ GEOGRAPHIC ═══════════════ -->
    <div id="sec-geographic" class="section" style="display:none">
      <div class="section-grid-2">
        <div class="s-card">
          <h3>Calls by Province</h3>
          <div class="chart-h"><canvas id="provinceBarChart"></canvas></div>
        </div>
        <div class="s-card">
          <h3>Province Share</h3>
          <div class="chart-h"><canvas id="provincePieChart"></canvas></div>
        </div>
      </div>
      <div class="s-card">
        <h3>Province Details</h3>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>#</th><th>Province</th><th>Interactions</th><th>% Share</th><th>Volume</th></tr></thead>
            <tbody>
              <?php $rank=0; $maxProv=$byProvince->first()?->cnt??1; ?>
              <?php $__currentLoopData = $byProvince; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $rank++; $pct=round($row->cnt/$total*100,1); $bar=$maxProv?round($row->cnt/$maxProv*100):0; ?>
                <tr>
                  <td style="color:#cbd5e1"><?php echo e($rank); ?></td>
                  <td><strong style="color:#0f172a"><?php echo e($row->province); ?></strong></td>
                  <td><?php echo e(number_format($row->cnt)); ?></td>
                  <td><?php echo e($pct); ?>%</td>
                  <td style="min-width:100px"><div class="pb-track"><div class="pb-fill" style="width:<?php echo e($bar); ?>%;background:#3b82f6"></div></div></td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              <?php if($byProvince->isEmpty()): ?>
                <tr><td colspan="5" style="text-align:center;color:#cbd5e1;padding:20px">No province data yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══════════════ DEMOGRAPHICS ═══════════════ -->
    <div id="sec-demographics" class="section" style="display:none">
      <div class="section-grid-3">
        <div class="s-card"><h3>Gender</h3><div class="chart-h"><canvas id="genderChart"></canvas></div></div>
        <div class="s-card"><h3>Age Groups</h3><div class="chart-h"><canvas id="ageChart"></canvas></div></div>
        <div class="s-card"><h3>Marital Status</h3><div class="chart-h"><canvas id="maritalChart"></canvas></div></div>
      </div>
      <div class="s-card">
        <h3>Key Population Groups</h3>
        <?php $maxKp=$byKeyPops->first()?->cnt??1; ?>
        <?php $__currentLoopData = $byKeyPops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $pct=$total?round($kp->cnt/$total*100,1):0; $bar=$maxKp?round($kp->cnt/$maxKp*100):0; ?>
          <div class="pb">
            <div class="pb-hdr"><span class="pb-lbl"><?php echo e($kp->key_pops); ?></span><span class="pb-val"><?php echo e(number_format($kp->cnt)); ?> (<?php echo e($pct); ?>%)</span></div>
            <div class="pb-track"><div class="pb-fill" style="width:<?php echo e($bar); ?>%;background:#8b5cf6"></div></div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($byKeyPops->isEmpty()): ?><p style="font-size:12px;color:#94a3b8;text-align:center;padding:16px 0">No key pops data yet</p><?php endif; ?>
      </div>
    </div>

    <!-- ═══════════════ SERVICES ═══════════════ -->
    <div id="sec-services" class="section" style="display:none">
      <div class="section-grid-2">
        <div class="s-card"><h3>Top Services Requested</h3><div class="chart-h"><canvas id="serviceChart"></canvas></div></div>
        <div class="s-card"><h3>Top Referral Destinations</h3><div class="chart-h"><canvas id="referralChart"></canvas></div></div>
      </div>
      <div class="s-card">
        <h3>Services Detail</h3>
        <?php $maxSvc=$byService->first()?->cnt??1; ?>
        <?php $__currentLoopData = $byService; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $svc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $pct=$total?round($svc->cnt/$total*100,1):0; $bar=$maxSvc?round($svc->cnt/$maxSvc*100):0; ?>
          <div class="pb">
            <div class="pb-hdr"><span class="pb-lbl"><?php echo e($svc->services_requested); ?></span><span class="pb-val"><?php echo e(number_format($svc->cnt)); ?> (<?php echo e($pct); ?>%)</span></div>
            <div class="pb-track"><div class="pb-fill" style="width:<?php echo e($bar); ?>%;background:#0d9488"></div></div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($byService->isEmpty()): ?><p style="font-size:12px;color:#94a3b8;text-align:center;padding:16px 0">No services data yet</p><?php endif; ?>
      </div>
    </div>

    <!-- ═══════════════ CALL DETAILS ═══════════════ -->
    <div id="sec-calls" class="section" style="display:none">

      <!-- Period selector -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <span style="font-size:15px;font-weight:700;color:#0f172a">Call Activity</span>
        <div style="display:flex;gap:4px;background:#f1f5f9;border-radius:12px;padding:4px">
          <button onclick="setCallPeriod('day',this)"   class="period-btn active-period">Today</button>
          <button onclick="setCallPeriod('week',this)"  class="period-btn">This Week</button>
          <button onclick="setCallPeriod('month',this)" class="period-btn">This Month</button>
        </div>
      </div>

      <!-- KPI cards — updated by JS -->
      <div class="kpi-row" style="margin-bottom:16px">
        <div class="kpi">
          <div class="kpi-icon">📞</div>
          <div class="kpi-val" id="c-total">0</div>
          <div class="kpi-lbl">Total Calls</div>
        </div>
        <div class="kpi">
          <div class="kpi-icon">📥</div>
          <div class="kpi-val" id="c-inbound">0</div>
          <div class="kpi-lbl">Inbound</div>
        </div>
        <div class="kpi">
          <div class="kpi-icon">📤</div>
          <div class="kpi-val" id="c-outbound">0</div>
          <div class="kpi-lbl">Outbound</div>
        </div>
        <div class="kpi">
          <div class="kpi-icon">📵</div>
          <div class="kpi-val" id="c-missed">0</div>
          <div class="kpi-lbl">Missed</div>
        </div>
        <div class="kpi">
          <div class="kpi-icon">✅</div>
          <div class="kpi-val" id="c-answered">0</div>
          <div class="kpi-lbl">Answered</div>
        </div>
        <div class="kpi">
          <div class="kpi-icon">⏱️</div>
          <div class="kpi-val" id="c-avgdur">0s</div>
          <div class="kpi-lbl">Avg Duration</div>
        </div>
      </div>

      <!-- Trend chart -->
      <div class="s-card" style="margin-bottom:16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h3 id="trend-label" style="margin:0">Calls by Hour — Today</h3>
        </div>
        <div style="height:180px;position:relative"><canvas id="callTrendChart"></canvas></div>
      </div>

      <!-- Purpose of call (all-time, below the period stats) -->
      <div class="s-card">
        <h3>Purpose of Call (Top 10 — All Time)</h3>
        <?php $maxPurpAll=$byPurpose->first()?->cnt??1; ?>
        <?php $__currentLoopData = $byPurpose; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $purp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $pct=$total?round($purp->cnt/$total*100,1):0; $bar=$maxPurpAll?round($purp->cnt/$maxPurpAll*100):0; ?>
          <div class="pb">
            <div class="pb-hdr"><span class="pb-lbl"><?php echo e($purp->purpose_of_call); ?></span><span class="pb-val"><?php echo e(number_format($purp->cnt)); ?> (<?php echo e($pct); ?>%)</span></div>
            <div class="pb-track"><div class="pb-fill" style="width:<?php echo e($bar); ?>%;background:#3b82f6"></div></div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($byPurpose->isEmpty()): ?><p style="font-size:12px;color:#94a3b8;text-align:center;padding:16px 0">No purpose data yet</p><?php endif; ?>
      </div>
    </div>

    <!-- ═══════════════ TRENDS ═══════════════ -->
    <div id="sec-trends" class="section" style="display:none">
      <div class="s-card" style="margin-bottom:16px">
        <h3>Interactions per Month (Last 12 Months)</h3>
        <div class="chart-h2"><canvas id="trendChart"></canvas></div>
      </div>
      <div class="s-card">
        <h3>Monthly Breakdown</h3>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Month</th><th>Interactions</th><th>% of 12-month total</th><th>Volume</th></tr></thead>
            <tbody>
              <?php $__currentLoopData = $monthArr; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ym => $cnt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $label=\Carbon\Carbon::createFromFormat('Y-m',$ym)->format('M Y');
                  $pct=$monthSum?round($cnt/$monthSum*100,1):0;
                  $bar=$maxMonth?round($cnt/$maxMonth*100):0;
                ?>
                <tr>
                  <td><strong style="color:#0f172a"><?php echo e($label); ?></strong></td>
                  <td><?php echo e(number_format($cnt)); ?></td>
                  <td><?php echo e($pct); ?>%</td>
                  <td style="min-width:100px"><div class="pb-track"><div class="pb-fill" style="width:<?php echo e($bar); ?>%;background:#3b82f6"></div></div></td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="footer">
      Helpline Analytics &middot; Auto-refreshes every 2 minutes &middot; <?php echo e(now()->format('d M Y')); ?>

    </div>

  </main>
</div><!-- /app -->

<script>
// ── PHP data ──────────────────────────────────────────────────────────
const last7      = <?php echo json_encode($last7, 15, 512) ?>;
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
const validPct   = <?php echo e($validPct); ?>;
const repeatPct  = <?php echo e($repeatPct); ?>;
const immPct     = <?php echo e($immPct); ?>;

// ── Chart defaults ────────────────────────────────────────────────────
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = '#f1f5f9';

const PAL = ['#3b82f6','#fbbf24','#4ade80','#f87171','#8b5cf6','#0d9488','#f59e0b','#06b6d4','#ec4899','#a3e635'];

function makeChart(id, type, labels, data, opts = {}) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  return new Chart(ctx, {
    type,
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: opts.barColor
          ? labels.map(() => opts.barColor)
          : (type === 'bar' && !opts.multi ? 'rgba(255,255,255,0.07)' : PAL.slice(0, labels.length)),
        borderColor: opts.line ? '#3b82f6' : (type === 'bar' ? 'transparent' : 'transparent'),
        borderWidth: opts.line ? 2 : 0,
        borderRadius: type === 'bar' ? 8 : 0,
        fill: opts.fill ?? false,
        tension: 0.4,
        pointBackgroundColor: '#3b82f6',
        pointRadius: opts.line ? 3 : 0,
        hoverBackgroundColor: opts.barColor ? opts.barColor : (type === 'bar' ? '#cbd5e1' : undefined),
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: opts.legend ?? (type === 'pie' || type === 'doughnut') },
        tooltip: {
          backgroundColor: 'rgba(255,255,255,0.98)',
          borderColor: '#e2e8f0',
          titleColor: '#0f172a',
          bodyColor: '#475569',
          borderWidth: 1,
          callbacks: { label: c => ` ${c.label}: ${Number(c.raw).toLocaleString()}` },
        },
      },
      scales: (type === 'bar' || type === 'line') ? {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255,255,255,0.04)' } },
        x: { ticks: { maxRotation: 0 }, grid: { display: false } },
      } : {},
      ...opts.extra,
    },
  });
}

// ── Activity bars (last 7 months) ──────────────────────────────────────
const actLabels = Object.keys(last7).map(ym => {
  const [y, m] = ym.split('-');
  return new Date(+y, +m - 1).toLocaleString('default', { month: 'short' });
});
const actData = Object.values(last7);
makeChart('activityChart', 'bar', actLabels, actData, {
  barColor: '#e2e8f0',
});
// Highlight the last (most recent) bar
(function () {
  const ctx = document.getElementById('activityChart');
  if (!ctx) return;
  const ch = Chart.getChart(ctx);
  if (!ch) return;
  const bg = actData.map((_, i) => i === actData.length - 1 ? '#3b82f6' : '#e2e8f0');
  ch.data.datasets[0].backgroundColor = bg;
  ch.update('none');
})();

// ── Overview donut ────────────────────────────────────────────────────
(function () {
  const ctx = document.getElementById('overviewDonut');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Valid', 'Repeat', 'Immediate', 'Other'],
      datasets: [{
        data: [validPct, repeatPct, immPct, Math.max(0, 100 - validPct - repeatPct - immPct)],
        backgroundColor: ['#3b82f6', '#fbbf24', '#f87171', 'rgba(255,255,255,0.06)'],
        borderWidth: 0,
        hoverOffset: 4,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: 'rgba(255,255,255,0.98)', borderColor: '#e2e8f0', titleColor: '#0f172a', bodyColor: '#475569', borderWidth: 1 },
      },
    },
  });
})();

// ── Province charts ────────────────────────────────────────────────────
const provLabels = byProvince.map(r => r.province);
const provData   = byProvince.map(r => r.cnt);
makeChart('provinceBarChart', 'bar', provLabels, provData, { barColor: '#3b82f6', legend: false });
makeChart('provincePieChart', 'pie',  provLabels, provData);

// ── Gender ─────────────────────────────────────────────────────────────
const gMap = { male:'Male', female:'Female', other:'Other', prefer_not_to_say:'Not say' };
makeChart('genderChart', 'doughnut', byGender.map(r => gMap[r.caller_gender] ?? r.caller_gender), byGender.map(r => r.cnt));

// ── Age groups ─────────────────────────────────────────────────────────
makeChart('ageChart', 'bar', Object.keys(ageGroups), Object.values(ageGroups), { barColor: '#8b5cf6', legend: false });

// ── Marital ────────────────────────────────────────────────────────────
makeChart('maritalChart', 'doughnut', byMarital.map(r => r.caller_marital_status.replace('_', ' ')), byMarital.map(r => r.cnt));

// ── Services ───────────────────────────────────────────────────────────
makeChart('serviceChart',  'bar', byService.map(r => r.services_requested),  byService.map(r => r.cnt),  { barColor: '#0d9488', legend: false, extra: { indexAxis: 'y' } });
makeChart('referralChart', 'bar', byReferral.map(r => r.referred_to),        byReferral.map(r => r.cnt), { barColor: '#fbbf24', legend: false, extra: { indexAxis: 'y' } });

// ── Monthly trend ──────────────────────────────────────────────────────
const mLabels = Object.keys(months).map(ym => {
  const [y, m] = ym.split('-');
  return new Date(+y, +m - 1).toLocaleString('default', { month: 'short', year: '2-digit' });
});
makeChart('trendChart', 'line', mLabels, Object.values(months), { line: true, fill: true, legend: false });

// ── Call period data ───────────────────────────────────────────────────
const callStats = <?php echo json_encode($callStats, 15, 512) ?>;

let callTrendChart = null;

function setCallPeriod(period, btn) {
  // Update active button
  document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active-period'));
  btn.classList.add('active-period');

  const s = callStats[period];

  // Update KPI values
  document.getElementById('c-total').textContent    = s.total.toLocaleString();
  document.getElementById('c-inbound').textContent  = s.inbound.toLocaleString();
  document.getElementById('c-outbound').textContent = s.outbound.toLocaleString();
  document.getElementById('c-missed').textContent   = s.missed.toLocaleString();
  document.getElementById('c-answered').textContent = s.answered.toLocaleString();
  document.getElementById('c-avgdur').textContent   = s.avg_dur + 's';

  // Build trend chart labels and data
  let labels, data, trendTitle;

  if (period === 'day') {
    trendTitle = 'Calls by Hour — Today';
    labels = Object.keys(s.trend).map(h => {
      const hr = parseInt(h);
      return hr === 0 ? '12am' : hr < 12 ? hr + 'am' : hr === 12 ? '12pm' : (hr - 12) + 'pm';
    });
    data = Object.values(s.trend);
  } else if (period === 'week') {
    trendTitle = 'Calls by Day — This Week';
    labels = Object.keys(s.trend).map(d => {
      const dt = new Date(d + 'T00:00:00');
      return dt.toLocaleDateString('default', { weekday: 'short', day: 'numeric' });
    });
    data = Object.values(s.trend);
  } else {
    trendTitle = 'Calls by Day — This Month';
    labels = Object.keys(s.trend).map(d => {
      const dt = new Date(d + 'T00:00:00');
      return dt.getDate();
    });
    data = Object.values(s.trend);
  }

  document.getElementById('trend-label').textContent = trendTitle;

  // Destroy and rebuild chart
  const ctx = document.getElementById('callTrendChart');
  if (callTrendChart) callTrendChart.destroy();

  callTrendChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Total',
          data,
          backgroundColor: data.map((_, i) => i === data.length - 1 ? '#3b82f6' : '#dbeafe'),
          borderRadius: 6,
          borderWidth: 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(255,255,255,0.98)',
          borderColor: '#e2e8f0',
          titleColor: '#0f172a',
          bodyColor: '#475569',
          borderWidth: 1,
          callbacks: { label: c => ` ${c.parsed.y} call${c.parsed.y !== 1 ? 's' : ''}` },
        },
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
        x: { grid: { display: false }, ticks: { maxRotation: 0, font: { size: 10 } } },
      },
    },
  });
}

// Initialise with "day" on load
window.addEventListener('DOMContentLoaded', () => {
  const dayBtn = document.querySelector('.period-btn.active-period');
  if (dayBtn) setCallPeriod('day', dayBtn);
});

// ── Section switcher ───────────────────────────────────────────────────
function showSection(name, btn) {
  document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
  document.querySelectorAll('.sb-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sec-' + name).style.display = 'block';
  btn.classList.add('active');
  // Re-init call chart when switching to calls tab
  if (name === 'calls') {
    const activeBtn = document.querySelector('.period-btn.active-period');
    const period = activeBtn?.onclick?.toString().match(/'(\w+)'/)?.[1] ?? 'day';
    if (activeBtn) setCallPeriod(period, activeBtn);
  }
}
</script>
</body>
</html>
<?php /**PATH C:\Users\Mazarura\crm-pbx\backend\resources\views/public-dashboard.blade.php ENDPATH**/ ?>