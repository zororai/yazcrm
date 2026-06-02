<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate — {{ strtoupper($signup->first_name . ' ' . $signup->surname) }}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Open+Sans:wght@400;600;700;800&display=swap');

  * { margin:0; padding:0; box-sizing:border-box; }

  body {
    background: #d4c89a;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    font-family: 'Open Sans', sans-serif;
  }

  .cert-wrap {
    width: 1050px;
    position: relative;
  }

  /* ── Outer gold frame ── */
  .cert {
    width: 1050px;
    min-height: 720px;
    background: #faf6e8;
    border: 10px solid #c9a84c;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  /* Gold inner border line */
  .cert::before {
    content: '';
    position: absolute;
    inset: 8px;
    border: 2px solid #c9a84c;
    pointer-events: none;
    z-index: 10;
  }

  /* ── Dark maroon right-side decorative swoosh ── */
  .swoosh {
    position: absolute;
    right: 0;
    top: 0;
    width: 220px;
    height: 100%;
    background: linear-gradient(160deg, #6b1a2b 0%, #3a0d1a 60%, #1a0a14 100%);
    clip-path: polygon(35% 0%, 100% 0%, 100% 100%, 0% 100%);
    z-index: 1;
  }

  /* Gold diagonal accent on swoosh */
  .swoosh::after {
    content: '';
    position: absolute;
    left: -4px;
    top: 0;
    width: 30px;
    height: 100%;
    background: linear-gradient(to right, transparent, rgba(201,168,76,0.6), transparent);
  }

  /* ── Main content area ── */
  .content {
    position: relative;
    z-index: 5;
    padding: 30px 260px 30px 40px;
    flex: 1;
    display: flex;
    flex-direction: column;
  }

  /* ── Header row ── */
  .header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  .cert-title {
    font-family: 'Playfair Display', serif;
    font-size: 62px;
    font-weight: 900;
    color: #1a1a4e;
    letter-spacing: 2px;
    line-height: 1;
  }

  .yalep-logo {
    text-align: right;
    font-size: 11px;
    color: #666;
    padding-top: 4px;
  }

  .yalep-logo .yalep-text {
    font-size: 28px;
    font-weight: 900;
    letter-spacing: -1px;
  }

  .yalep-text .y  { color: #ffd700; }
  .yalep-text .a  { color: #e87422; }
  .yalep-text .le { color: #2196F3; }
  .yalep-text .p  { color: #9c27b0; }
  .yalep-text .bb { color: #e87422; font-size: 32px; }

  /* ── OF COMPLETION ribbon ── */
  .ribbon {
    background: #1a1a4e;
    color: #fff;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 4px;
    padding: 8px 24px 8px 0;
    margin-bottom: 28px;
    clip-path: polygon(0 0, calc(100% - 16px) 0, 100% 50%, calc(100% - 16px) 100%, 0 100%);
    width: fit-content;
    min-width: 320px;
  }

  /* ── Certify text ── */
  .certify-label {
    font-size: 13px;
    letter-spacing: 3px;
    color: #444;
    text-transform: uppercase;
    margin-bottom: 12px;
  }

  /* ── NAME ── */
  .recipient-name {
    font-family: 'Open Sans', sans-serif;
    font-size: 52px;
    font-weight: 900;
    color: #e87422;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 20px;
    line-height: 1;
    text-shadow: 1px 1px 0 rgba(0,0,0,0.08);
  }

  /* ── Programme text ── */
  .programme-text {
    font-size: 15px;
    font-weight: 700;
    color: #333;
    text-align: center;
    line-height: 1.6;
    margin-bottom: 30px;
    padding-right: 20px;
  }

  /* ── Signatures ── */
  .signatures {
    display: flex;
    gap: 80px;
    margin-bottom: 20px;
    align-items: flex-end;
  }

  .sig-block { min-width: 180px; }

  .sig-line {
    border-top: 2px solid #333;
    padding-top: 6px;
    margin-top: 30px;
  }

  .sig-name { font-size: 12px; font-weight: 800; color: #111; letter-spacing: 0.5px; }
  .sig-title { font-size: 10px; color: #444; letter-spacing: 0.3px; }
  .sig-org { font-size: 10px; font-weight: 700; color: #111; letter-spacing: 0.3px; }

  /* Handwriting-style signature */
  .sig-graphic {
    font-size: 38px;
    color: #222;
    font-family: 'Playfair Display', serif;
    font-style: italic;
    line-height: 1;
    height: 40px;
    display: flex;
    align-items: flex-end;
  }

  /* ── Bottom logos ── */
  .logos-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: auto;
  }

  .logo-box {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* NAC logo approximation */
  .logo-nac {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1a5276, #2980b9);
    color: #fff;
    font-size: 7px;
    font-weight: 700;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    border: 2px solid #1a5276;
  }

  /* Youth Advocates logo text */
  .logo-ya {
    font-size: 15px;
    font-weight: 900;
    color: #e87422;
    letter-spacing: -0.5px;
    border-bottom: 2px solid #e87422;
    padding-bottom: 2px;
  }

  .logo-ya span { color: #2196F3; }

  /* UNICEF logo */
  .logo-unicef {
    font-size: 16px;
    font-weight: 900;
    color: #009edb;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .unicef-globe {
    width: 22px; height: 22px;
    border-radius: 50%;
    border: 2px solid #009edb;
    background: radial-gradient(circle at 40% 40%, #cce8f4, #009edb44);
    display: inline-block;
  }

  /* ── 393 badge on swoosh ── */
  .badge-393 {
    position: absolute;
    right: 30px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    text-align: center;
    width: 140px;
  }

  .badge-stars { color: #ffd700; font-size: 16px; letter-spacing: 2px; }

  .badge-inner {
    background: linear-gradient(145deg, #1a0a14, #3a0d1a);
    border: 3px solid #c9a84c;
    border-radius: 50%;
    width: 120px;
    height: 120px;
    margin: 4px auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  .badge-powered { font-size: 8px; color: #c9a84c; letter-spacing: 2px; text-transform: uppercase; }
  .badge-num { font-size: 42px; font-weight: 900; color: #fff; line-height: 1; }
  .badge-helpline { font-size: 8px; color: #c9a84c; letter-spacing: 1px; text-transform: uppercase; }

  /* ── Print styles ── */
  @media print {
    body { background: none; padding: 0; margin: 0; }
    .cert-wrap { width: 100%; }
    .cert { width: 100%; min-height: 100vh; border-width: 8px; }
    .no-print { display: none !important; }
    @page { size: A4 landscape; margin: 0; }
  }
</style>
</head>
<body>

<!-- Print button -->
<div class="no-print" style="position:fixed;top:16px;right:16px;z-index:999;display:flex;gap:8px">
  <button onclick="window.print()"
    style="background:#1a1a4e;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px">
    🖨 Print / Save as PDF
  </button>
  <button onclick="window.close()"
    style="background:#eee;color:#333;border:none;padding:10px 20px;border-radius:8px;font-size:14px;cursor:pointer">
    ✕ Close
  </button>
</div>

<div class="cert-wrap">
  <div class="cert">

    <!-- Dark maroon right swoosh -->
    <div class="swoosh"></div>

    <!-- 393 Badge -->
    <div class="badge-393">
      <div class="badge-stars">★★★★★</div>
      <div class="badge-inner">
        <div class="badge-powered">POWERED BY</div>
        <div class="badge-num">393</div>
        <div class="badge-helpline">YOUTH HELPLINE</div>
      </div>
      <div class="badge-stars">★★★★★</div>
    </div>

    <!-- Main content -->
    <div class="content">

      <!-- Header -->
      <div class="header-row">
        <div class="cert-title">CERTIFICATE</div>
        <div class="yalep-logo">
          <div class="yalep-text">
            <span class="y">Y</span><span class="a">A</span><span class="le">Le</span><span class="p">P</span><span class="bb">!!</span>
          </div>
          <div style="font-size:9px;color:#666;letter-spacing:1px">Youth Advocates Leadership Program</div>
        </div>
      </div>

      <!-- Ribbon -->
      <div class="ribbon">OF COMPLETION</div>

      <!-- This is to certify -->
      <div class="certify-label">This is to certify that</div>

      <!-- NAME -->
      <div class="recipient-name">
        {{ strtoupper(trim($signup->first_name . ' ' . $signup->surname)) }}
      </div>

      <!-- Programme description -->
      <div class="programme-text">
        Completed an Online Youth Advocates Leadership Programme<br>
        on HIV and Sexual Reproductive Health
      </div>

      <!-- Signatures -->
      <div class="signatures">
        <div class="sig-block">
          <div class="sig-graphic">A/Maje</div>
          <div class="sig-line">
            <div class="sig-name">NOKUPHIWA MOYO</div>
            <div class="sig-title">HEAD OF IMPACT AND PROGRAMS COORDINATOR</div>
            <div class="sig-org">YOUTH ADVOCATES</div>
          </div>
        </div>
        <div class="sig-block">
          <div class="sig-graphic">Tsa</div>
          <div class="sig-line">
            <div class="sig-name">TATENDA SONGORE</div>
            <div class="sig-title">EXECUTIVE DIRECTOR</div>
            <div class="sig-org">YOUTH ADVOCATES</div>
          </div>
        </div>
      </div>

      <!-- Logos -->
      <div class="logos-row">
        <div class="logo-box">
          <div class="logo-nac">
            <div>NAC</div>
            <div style="font-size:5px">NATIONAL</div>
            <div style="font-size:5px">AIDS COUNCIL</div>
          </div>
        </div>
        <div class="logo-box">
          <div class="logo-nac" style="background:linear-gradient(135deg,#c0392b,#e74c3c)">
            <div style="font-size:6px;font-weight:900">NATIONAL</div>
            <div style="font-size:5px">AIDS</div>
            <div style="font-size:5px">COUNCIL</div>
          </div>
        </div>
        <div class="logo-box">
          <div class="logo-ya">youth<br><span>advocates</span></div>
        </div>
        <div class="logo-box">
          <div class="logo-unicef">
            <span class="unicef-globe"></span>
            unicef<span style="color:#ffd700">®</span>
          </div>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /cert -->
</div>

<script>
// Auto-show print dialog on load if ?print=1
if (new URLSearchParams(location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
}
</script>
</body>
</html>
