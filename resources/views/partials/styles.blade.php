<style>
/* Workerless identity "The Gap": Ember #FF5A36, Cream #FFF4E8, Char #140D0B */
:root {
    --bg: #f6f3ef;
    --glow: rgba(255, 90, 54, .10);
    --surface: #ffffff;
    --surface-2: #fbf9f6;
    --surface-3: #f3efea;
    --border: #ebe5de;
    --border-strong: #ddd4ca;
    --text: #17110f;
    --text-2: #433833;
    --muted: #7d716a;
    --accent: #ff5a36;
    --accent-2: #ff8a4c;
    --accent-soft: rgba(255, 90, 54, .10);
    --accent-text: #d63f1d;
    --ok: #0f9f6e;
    --ok-soft: rgba(15, 159, 110, .10);
    --warn: #b97504;
    --warn-soft: rgba(185, 117, 4, .11);
    --bad: #d61f69;
    --bad-soft: rgba(214, 31, 105, .09);
    --info: #2f6fed;
    --info-soft: rgba(47, 111, 237, .09);
    --shadow-sm: 0 1px 2px rgba(23, 17, 15, .05);
    --shadow: 0 1px 2px rgba(23, 17, 15, .05), 0 12px 32px -16px rgba(23, 17, 15, .16);
    --radius: 18px;
    --radius-sm: 11px;
    --font: "Inter var", Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    color-scheme: light;
}

@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
        --bg: #0a0a0b;
        --glow: rgba(255, 90, 54, .045);
        --surface: #111113;
        --surface-2: #151517;
        --surface-3: #1c1c1f;
        --border: #232326;
        --border-strong: #303034;
        --text: #ededef;
        --text-2: #c4c4c9;
        --muted: #8b8b93;
        --accent: #ff6a45;
        --accent-2: #ff9a5c;
        --accent-soft: rgba(255, 106, 69, .15);
        --accent-text: #ff9474;
        --ok: #34d399;
        --ok-soft: rgba(52, 211, 153, .12);
        --warn: #fbbf24;
        --warn-soft: rgba(251, 191, 36, .12);
        --bad: #f4508f;
        --bad-soft: rgba(244, 80, 143, .13);
        --info: #60a5fa;
        --info-soft: rgba(96, 165, 250, .12);
        --shadow-sm: 0 1px 2px rgba(0, 0, 0, .3);
        --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 16px 40px -20px rgba(0, 0, 0, .7);
        color-scheme: dark;
    }
}

:root[data-theme="dark"] {
    --bg: #0a0a0b;
    --glow: rgba(255, 90, 54, .045);
    --surface: #111113;
    --surface-2: #151517;
    --surface-3: #1c1c1f;
    --border: #232326;
    --border-strong: #303034;
    --text: #ededef;
    --text-2: #c4c4c9;
    --muted: #8b8b93;
    --accent: #ff6a45;
    --accent-2: #ff9a5c;
    --accent-soft: rgba(255, 106, 69, .15);
    --accent-text: #ff9474;
    --ok: #34d399;
    --ok-soft: rgba(52, 211, 153, .12);
    --warn: #fbbf24;
    --warn-soft: rgba(251, 191, 36, .12);
    --bad: #f4508f;
    --bad-soft: rgba(244, 80, 143, .13);
    --info: #60a5fa;
    --info-soft: rgba(96, 165, 250, .12);
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, .3);
    --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 16px 40px -20px rgba(0, 0, 0, .7);
    color-scheme: dark;
}

*, *::before, *::after { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body {
    margin: 0;
    min-height: 100vh;
    font-family: var(--font);
    font-size: 14px;
    line-height: 1.55;
    color: var(--text);
    background:
        radial-gradient(900px 420px at 70% -120px, var(--glow), transparent 70%),
        var(--bg);
    background-attachment: fixed;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    font-feature-settings: "cv11", "ss01";
}
a { color: inherit; text-decoration: none; }
h1, h2, h3, p { margin: 0; }
.num { font-variant-numeric: tabular-nums; }
.mono { font-family: var(--mono); font-size: 12.5px; }
.muted { color: var(--muted); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 8px; }
svg.i { width: 18px; height: 18px; flex: none; }

/* ---------- layout ---------- */
.app { display: grid; grid-template-columns: 268px minmax(0, 1fr); min-height: 100vh; }

.sidebar {
    position: sticky; top: 0; height: 100vh;
    display: flex; flex-direction: column; gap: 28px;
    padding: 24px 16px 20px;
    border-right: 1px solid var(--border);
    background: color-mix(in srgb, var(--surface) 72%, transparent);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}
.brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; }
.brand-mark { display: grid; flex: none; border-radius: 10px; box-shadow: 0 10px 22px -10px rgba(255, 90, 54, .55); }
.brand-mark svg { display: block; }
.brand-name { font-size: 17px; font-weight: 750; letter-spacing: -.035em; }
.brand-less { color: var(--accent); }
.brand-sub { font-size: 12px; color: var(--muted); margin-top: -2px; }

.nav { display: flex; flex-direction: column; gap: 2px; }
.nav-label { font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); padding: 0 12px 8px; }
.nav a {
    display: flex; align-items: center; gap: 12px; height: 40px; padding: 0 12px; border-radius: var(--radius-sm);
    color: var(--text-2); font-weight: 550; transition: background .15s, color .15s;
}
.nav a:hover { background: var(--surface-3); color: var(--text); }
.nav a[aria-current="page"] { background: var(--surface-3); color: var(--text); box-shadow: inset 0 0 0 1px var(--border); }
.nav a[aria-current="page"] svg { color: var(--accent); }
.nav .badge { margin-left: auto; }

.sidebar-foot { margin-top: auto; padding: 14px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface); font-size: 12px; color: var(--muted); display: grid; gap: 6px; }
.sidebar-foot strong { color: var(--text); font-weight: 600; }
.sidebar-foot .row { display: flex; justify-content: space-between; gap: 8px; }

.main { min-width: 0; display: flex; flex-direction: column; }
.topbar {
    position: sticky; top: 0; z-index: 20;
    display: flex; align-items: center; gap: 16px;
    padding: 18px 36px;
    background: color-mix(in srgb, var(--bg) 78%, transparent);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid transparent;
}
.topbar .brand { display: none; padding: 0; }
.page-title { font-size: 20px; font-weight: 700; letter-spacing: -.025em; }
.page-sub { font-size: 13px; color: var(--muted); }
.topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }

.tabs { display: none; }
.content { padding: 8px 36px 56px; display: grid; gap: 22px; width: 100%; }

/* Navigation loader: the page blurs and a small spinner appears in the middle.
   Shown after 0.12 s, so fast navigations never flash. */
.overlay {
    position: fixed; inset: 0; z-index: 90; display: grid; place-items: center;
    background: color-mix(in srgb, var(--bg) 40%, transparent);
    backdrop-filter: blur(6px) saturate(.9);
    -webkit-backdrop-filter: blur(6px) saturate(.9);
    opacity: 0; visibility: hidden; pointer-events: none;
    transition: opacity .2s ease, visibility 0s linear .2s;
}
.is-loading .overlay { opacity: 1; visibility: visible; pointer-events: auto; transition: opacity .2s ease .12s, visibility 0s linear .12s; }
.spinner {
    position: relative; width: 68px; height: 68px; border-radius: 20px; display: grid; place-items: center;
    background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow);
    transform: scale(.94); transition: transform .25s cubic-bezier(.2, .9, .3, 1.2) .12s;
}
.is-loading .spinner { transform: none; }
.spinner::before {
    content: ""; width: 30px; height: 30px; border-radius: 50%;
    border: 2.5px solid var(--border-strong); border-top-color: var(--accent);
    animation: spin .7s linear infinite;
}
.spinner::after {
    content: ""; position: absolute; width: 7px; height: 7px; border-radius: 50%; background: var(--accent);
    animation: breathe 1.4s ease-in-out infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes breathe { 0%, 100% { transform: scale(.7); opacity: .6; } 50% { transform: scale(1); opacity: 1; } }

/* ---------- elements ---------- */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); }
.card-head { display: flex; align-items: center; gap: 12px; padding: 18px 22px; border-bottom: 1px solid var(--border); }
.card-title { font-size: 15px; font-weight: 650; letter-spacing: -.01em; }
.card-sub { font-size: 12.5px; color: var(--muted); }
.card-head .end { margin-left: auto; display: flex; gap: 8px; align-items: center; }
.card-body { padding: 20px 22px; }

.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 36px; padding: 0 14px; border-radius: 10px;
    border: 1px solid var(--border); background: var(--surface); color: var(--text);
    font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;
    box-shadow: var(--shadow-sm); transition: background .15s, border-color .15s, transform .05s;
}
.btn:hover { background: var(--surface-3); border-color: var(--border-strong); }
.btn:active { transform: translateY(1px); }
.btn svg.i { width: 16px; height: 16px; }
.btn-icon { width: 36px; padding: 0; }
.btn-primary { color: #fff; border-color: transparent; background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 8px 18px -10px var(--accent); }
.btn-primary:hover { background: linear-gradient(135deg, var(--accent), var(--accent-2)); filter: brightness(1.06); }
.btn-sm { height: 30px; padding: 0 10px; font-size: 12.5px; border-radius: 9px; }
.btn-ghost-bad { color: var(--bad); }
.btn-ghost-bad:hover { background: var(--bad-soft); border-color: transparent; }
.btn[aria-pressed="true"] { color: var(--ok); border-color: color-mix(in srgb, var(--ok) 35%, var(--border)); background: var(--ok-soft); }
.btn[aria-pressed="true"] .live-dot { background: var(--ok); box-shadow: 0 0 0 0 var(--ok); animation: pulse 1.8s infinite; }
.live-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--muted); }

.badge { display: inline-grid; place-items: center; min-width: 22px; height: 20px; padding: 0 7px; border-radius: 999px; font-size: 11.5px; font-weight: 700; background: var(--surface-3); color: var(--text-2); font-variant-numeric: tabular-nums; }
.badge-bad { background: var(--bad-soft); color: var(--bad); }
.badge-warn { background: var(--warn-soft); color: var(--warn); }

.pill { display: inline-flex; align-items: center; gap: 7px; height: 24px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--surface-3); color: var(--text-2); }
.pill::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.pill-ok { background: var(--ok-soft); color: var(--ok); }
.pill-warn { background: var(--warn-soft); color: var(--warn); }
.pill-bad { background: var(--bad-soft); color: var(--bad); }
.pill-info { background: var(--info-soft); color: var(--info); }
.pill-accent { background: var(--accent-soft); color: var(--accent-text); }
.pill-accent::before { animation: pulse 1.8s infinite; box-shadow: 0 0 0 0 var(--accent); }

.flash { display: flex; align-items: center; gap: 12px; padding: 13px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); box-shadow: var(--shadow); font-weight: 550; }
.flash-ok { border-color: color-mix(in srgb, var(--ok) 30%, var(--border)); }
.flash-ok svg { color: var(--ok); }
.flash-warn svg { color: var(--warn); }
.flash-fail svg { color: var(--bad); }

/* ---------- hero ---------- */
.hero {
    position: relative; overflow: hidden;
    display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 28px; align-items: center;
    padding: 28px 30px; border-radius: 22px; border: 1px solid var(--border); box-shadow: var(--shadow);
    background:
        radial-gradient(600px 240px at 0% 0%, var(--hero-tint), transparent 70%),
        var(--surface);
    --hero-tint: var(--ok-soft); --hero-color: var(--ok);
}
.hero--warn { --hero-tint: var(--warn-soft); --hero-color: var(--warn); }
.hero--fail { --hero-tint: var(--bad-soft); --hero-color: var(--bad); }
.hero--live { --hero-tint: var(--accent-soft); --hero-color: var(--accent); }
.hero::after {
    content: ""; position: absolute; inset: 0; pointer-events: none; opacity: .5;
    background-image: radial-gradient(var(--border) 1px, transparent 1px); background-size: 18px 18px;
    mask-image: linear-gradient(90deg, transparent 40%, #000);
    -webkit-mask-image: linear-gradient(90deg, transparent 40%, #000);
}
.hero-main { position: relative; z-index: 1; display: flex; align-items: center; gap: 20px; min-width: 0; }
.orb { position: relative; width: 56px; height: 56px; flex: none; border-radius: 50%; display: grid; place-items: center; background: var(--hero-tint); }
.orb span { width: 18px; height: 18px; border-radius: 50%; background: var(--hero-color); box-shadow: 0 0 0 0 var(--hero-color); animation: pulse 2.2s infinite; }
.eyebrow { font-size: 11.5px; font-weight: 650; letter-spacing: .09em; text-transform: uppercase; color: var(--hero-color); }
.hero-title { font-size: 26px; font-weight: 750; letter-spacing: -.035em; line-height: 1.2; margin-top: 2px; }
.hero-text { color: var(--muted); margin-top: 4px; max-width: 520px; }
.hero-meta { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(2, minmax(150px, auto)); gap: 14px 32px; margin: 0; }
.hero-meta dt { font-size: 12px; color: var(--muted); }
.hero-meta dd { margin: 2px 0 0; font-weight: 650; }

/* ---------- simple mode ---------- */
.app--simple { grid-template-columns: minmax(0, 1fr); }
.app--simple .topbar { border-bottom-color: var(--border); }
.app--simple .topbar .brand { display: flex; }
.kpis.kpis--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.failed-list li { flex-wrap: wrap; }
.failed-list .actions { margin-left: auto; }
.simple-foot { text-align: center; color: var(--muted); font-size: 13px; padding: 4px 0 8px; }

/* mode switch and help links */
.seg button { height: 30px; padding: 0 12px; border: 0; background: transparent; border-radius: 8px; font: inherit; font-size: 13px; font-weight: 600; color: var(--muted); cursor: pointer; }
.seg button:hover { color: var(--text); }
.seg button[aria-current="page"] { background: var(--surface); color: var(--text); box-shadow: var(--shadow-sm); }
.mode-switch { margin: 0; }
.nav .nav-ext { margin-left: auto; color: var(--muted); display: grid; }
.nav .nav-ext svg.i { width: 14px; height: 14px; }

.hero-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 14px; font-size: 13px; }
.hero-actions form { margin: 0; }

/* ---------- hosting compatibility ---------- */
.compat { --v: var(--muted); --v-soft: var(--surface-3); }
.compat--ok { --v: var(--ok); --v-soft: var(--ok-soft); }
.compat--warn { --v: var(--warn); --v-soft: var(--warn-soft); }
.compat--fail { --v: var(--bad); --v-soft: var(--bad-soft); }
.compat--running { --v: var(--accent); --v-soft: var(--accent-soft); }
.compat .card-head form { margin: 0; }
.verdict { display: flex; align-items: center; gap: 18px; padding: 22px; border-bottom: 1px solid var(--border); background: linear-gradient(90deg, var(--v-soft), transparent 70%); }
.verdict:last-child { border-bottom: 0; }
.verdict-icon { width: 52px; height: 52px; flex: none; border-radius: 16px; display: grid; place-items: center; background: var(--v-soft); color: var(--v); }
.verdict-icon svg.i { width: 24px; height: 24px; }
.verdict-title { margin: 0; font-size: 19px; font-weight: 720; letter-spacing: -.02em; color: var(--v); }
.compat--none .verdict-title { color: var(--text); }
.verdict-text { color: var(--text-2); margin-top: 3px; max-width: 760px; }
.check-pending { background: var(--accent-soft); color: var(--accent); }
.mini-spin { width: 16px; height: 16px; border-radius: 50%; border: 2px solid currentColor; border-right-color: transparent; animation: spin .7s linear infinite; display: block; }
.btn:disabled { opacity: .5; pointer-events: none; }

/* ---------- KPIs ---------- */
.kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
.kpi { padding: 20px 22px; display: grid; gap: 14px; }
.kpi-head { display: flex; align-items: center; gap: 10px; color: var(--text-2); font-weight: 600; font-size: 13px; }
.kpi-icon { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: var(--surface-3); color: var(--text-2); }
.kpi-icon svg.i { width: 17px; height: 17px; }
.kpi-icon.ok { background: var(--ok-soft); color: var(--ok); }
.kpi-icon.bad { background: var(--bad-soft); color: var(--bad); }
.kpi-icon.info { background: var(--info-soft); color: var(--info); }
.kpi-icon.accent { background: var(--accent-soft); color: var(--accent); }
.kpi-value { font-size: 32px; font-weight: 720; letter-spacing: -.04em; line-height: 1; font-variant-numeric: tabular-nums; }
.kpi-foot { font-size: 12.5px; color: var(--muted); }

/* ---------- chart ---------- */
.legend { display: flex; gap: 14px; font-size: 12px; color: var(--muted); }
.legend span { display: inline-flex; align-items: center; gap: 6px; }
.legend i { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }
.chart { display: flex; align-items: flex-end; gap: 6px; height: 180px; padding-top: 8px; }
.bar { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; gap: 2px; border-radius: 7px; position: relative; }
.bar:hover { background: var(--surface-2); }
.bar .seg { width: 100%; border-radius: 5px; min-height: 0; transition: opacity .15s; }
.bar .seg-ok { background: linear-gradient(180deg, var(--accent-2), var(--accent)); }
.bar .seg-bad { background: var(--bad); }
.bar .seg-empty { height: 3px; background: var(--surface-3); }
.chart-axis { display: flex; justify-content: space-between; margin-top: 10px; font-size: 11.5px; color: var(--muted); font-variant-numeric: tabular-nums; }

/* ---------- grids ---------- */
.grid-2 { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 22px; align-items: start; }

/* ---------- lists ---------- */
.list { list-style: none; margin: 0; padding: 6px 0; }
.list li { display: flex; align-items: center; gap: 14px; padding: 11px 22px; }
.list li + li { border-top: 1px solid var(--border); }
.list .grow { min-width: 0; flex: 1; }
.list .title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.list .sub { font-size: 12.5px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.list .end { text-align: right; font-size: 12.5px; color: var(--muted); white-space: nowrap; }
.dot { width: 8px; height: 8px; border-radius: 50%; flex: none; background: var(--muted); }
.dot-ok { background: var(--ok); } .dot-bad { background: var(--bad); } .dot-warn { background: var(--warn); }

.check-icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; flex: none; }
.check-icon svg.i { width: 17px; height: 17px; }
.check-ok { background: var(--ok-soft); color: var(--ok); }
.check-warn { background: var(--warn-soft); color: var(--warn); }
.check-fail { background: var(--bad-soft); color: var(--bad); }
.checks li { align-items: flex-start; padding-top: 15px; padding-bottom: 15px; }
.checks .title { white-space: normal; }
.checks .sub { white-space: normal; margin-top: 2px; line-height: 1.5; }

.env { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; }
.env div { padding: 13px 22px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; gap: 16px; }
.env div:nth-child(-n+2) { border-top: 0; }
.env div:nth-child(odd) { border-right: 1px solid var(--border); }
.env dt { color: var(--muted); }
.env dd { margin: 0; font-weight: 600; text-align: right; }

/* ---------- tables ---------- */
.table-card { overflow: hidden; }
.table { width: 100%; border-collapse: collapse; }
.table th {
    text-align: left; padding: 11px 18px; font-size: 11.5px; font-weight: 650; letter-spacing: .06em; text-transform: uppercase;
    color: var(--muted); background: var(--surface-2); border-bottom: 1px solid var(--border); white-space: nowrap;
}
.table td { padding: 14px 18px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.table tbody tr:last-child td { border-bottom: 0; }
.table tbody tr { transition: background .12s; }
.table tbody tr:hover { background: var(--surface-2); }
.table .right { text-align: right; }
.job-name { font-weight: 650; display: block; max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.job-ns { display: block; color: var(--muted); font-family: var(--mono); font-size: 11.5px; max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.error-text { color: var(--text-2); font-family: var(--mono); font-size: 12px; max-width: 420px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; }
.queue-tag { display: inline-block; padding: 2px 8px; border-radius: 7px; background: var(--surface-3); font-family: var(--mono); font-size: 12px; color: var(--text-2); }
.actions { display: flex; gap: 6px; justify-content: flex-end; }
.actions form { margin: 0; }

.seg { display: inline-flex; padding: 3px; gap: 2px; border-radius: 11px; background: var(--surface-3); border: 1px solid var(--border); }
.seg a { height: 30px; padding: 0 12px; display: inline-flex; align-items: center; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--muted); }
.seg a:hover { color: var(--text); }
.seg a[aria-current="page"] { background: var(--surface); color: var(--text); box-shadow: var(--shadow-sm); }

.pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-top: 1px solid var(--border); background: var(--surface-2); }
.pager .btn[aria-disabled="true"] { opacity: .45; pointer-events: none; }

.empty { display: grid; justify-items: center; text-align: center; gap: 8px; padding: 56px 24px; }
.empty-icon { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; background: var(--surface-3); color: var(--muted); margin-bottom: 6px; }
.empty-icon.ok { background: var(--ok-soft); color: var(--ok); }
.empty-icon svg.i { width: 24px; height: 24px; }
.empty h3 { font-size: 15px; font-weight: 650; }
.empty p { color: var(--muted); max-width: 380px; }

.section-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.section-head .end { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
.chips { display: flex; gap: 8px; flex-wrap: wrap; }

/* ---------- confirm dialog ---------- */
.modal {
    width: min(420px, calc(100vw - 32px)); padding: 0; border: 1px solid var(--border); border-radius: 22px;
    background: var(--surface); color: var(--text); box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .45);
    --tone: var(--bad); --tone-soft: var(--bad-soft);
}
.modal[data-tone="accent"] { --tone: var(--accent); --tone-soft: var(--accent-soft); }
.modal::backdrop { background: rgba(8, 8, 12, .45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
.modal[open] { animation: modal-in .22s cubic-bezier(.2, .9, .3, 1.15); }
.modal[open]::backdrop { animation: fade-in .2s ease-out; }
.modal-box { padding: 28px 26px 22px; display: grid; justify-items: center; text-align: center; gap: 8px; margin: 0; }
.modal-icon {
    width: 56px; height: 56px; border-radius: 18px; display: grid; place-items: center; margin-bottom: 10px;
    background: var(--tone-soft); color: var(--tone);
    box-shadow: 0 0 0 8px color-mix(in srgb, var(--tone-soft) 50%, transparent);
}
.modal-icon svg.i { width: 24px; height: 24px; }
.modal-icon span { display: none; }
.modal[data-tone="danger"] .tone-danger, .modal[data-tone="accent"] .tone-accent { display: grid; }
.modal-title { font-size: 18px; font-weight: 700; letter-spacing: -.02em; }
.modal-text { color: var(--muted); max-width: 320px; }
.modal-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; margin-top: 18px; }
.modal-actions .btn { height: 42px; border-radius: 12px; font-size: 14px; }
.modal-ok { color: #fff; border-color: transparent; background: var(--tone); box-shadow: 0 10px 22px -12px var(--tone); }
.modal[data-tone="accent"] .modal-ok { background: linear-gradient(135deg, var(--accent), var(--accent-2)); }
.modal-ok:hover { background: var(--tone); filter: brightness(1.07); }
.btn.is-busy { opacity: .6; pointer-events: none; }

@keyframes modal-in { from { opacity: 0; transform: translateY(10px) scale(.96); } to { opacity: 1; transform: none; } }
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }

@media (max-width: 520px) {
    .modal { width: 100vw; max-width: 100vw; margin: auto 0 0; border-radius: 24px 24px 0 0; border-bottom: 0; }
    .modal[open] { animation: sheet-in .26s cubic-bezier(.2, .9, .3, 1); }
    .modal-box { padding: 26px 20px calc(20px + env(safe-area-inset-bottom)); }
    @keyframes sheet-in { from { transform: translateY(100%); } to { transform: none; } }
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 45%, transparent); }
    70% { box-shadow: 0 0 0 9px transparent; }
    100% { box-shadow: 0 0 0 0 transparent; }
}
.orb span { color: var(--hero-color); }

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
}

/* ---------- responsive ---------- */
@media (max-width: 1180px) {
    .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .grid-2 { grid-template-columns: minmax(0, 1fr); }
    .hero { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 1023px) {
    .app { grid-template-columns: minmax(0, 1fr); }
    .sidebar { display: none; }
    .topbar { padding: 14px 16px; border-bottom-color: var(--border); }
    .topbar .brand { display: flex; }
    .topbar .titles { display: none; }
    .tabs {
        display: flex; gap: 6px; overflow-x: auto; padding: 12px 16px 4px; scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }
    .tabs::-webkit-scrollbar { display: none; }
    .tabs a {
        display: inline-flex; align-items: center; gap: 8px; height: 36px; padding: 0 14px; border-radius: 999px; flex: none;
        border: 1px solid var(--border); background: var(--surface); font-weight: 600; color: var(--text-2);
    }
    .tabs a[aria-current="page"] { background: var(--text); color: var(--bg); border-color: var(--text); }
    .tabs a[aria-current="page"] .badge { background: color-mix(in srgb, var(--bg) 22%, transparent); color: var(--bg); }
    .tabs svg.i { width: 16px; height: 16px; }
    .content { padding: 12px 16px 40px; gap: 16px; }
    .mobile-title { display: block; }
}

@media (min-width: 1024px) {
    .mobile-title { display: none; }
}

@media (max-width: 760px) {
    .hero { padding: 22px 20px; border-radius: 18px; gap: 20px; }
    .hero-title { font-size: 21px; }
    .orb { width: 46px; height: 46px; }
    .orb span { width: 15px; height: 15px; }
    .hero-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
    .kpis { gap: 12px; }
    .kpi { padding: 16px; gap: 10px; }
    .kpi-value { font-size: 26px; }
    .card-head, .card-body { padding-left: 16px; padding-right: 16px; }
    .list li { padding-left: 16px; padding-right: 16px; }
    .chart { height: 140px; gap: 3px; }
    .env { grid-template-columns: minmax(0, 1fr); }
    .env div { border-right: 0 !important; padding: 12px 16px; }
    .env div:nth-child(2) { border-top: 1px solid var(--border); }
    .topbar-actions .label { display: none; }

    /* tables become cards */
    .table thead { display: none; }
    .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
    .table tbody tr { padding: 14px 16px; border-bottom: 1px solid var(--border); }
    .table tbody tr:last-child { border-bottom: 0; }
    .table td { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 5px 0; border: 0; text-align: right; }
    .table td::before { content: attr(data-label); color: var(--muted); font-size: 12.5px; font-weight: 550; text-align: left; flex: none; }
    .table td.cell-main { display: block; text-align: left; padding-bottom: 8px; }
    .table td.cell-main::before { display: none; }
    .job-name, .job-ns { max-width: 100%; }
    .error-text { max-width: 60vw; }
    .actions { justify-content: flex-end; }
    .pager { padding: 12px 16px; }
}

@media (max-width: 760px) {
    .kpis.kpis--3 { grid-template-columns: minmax(0, 1fr); }
    .failed-list .actions { width: 100%; justify-content: flex-start; margin-left: 22px; }
    .mode-switch button { padding: 0 9px; }
}

@media (max-width: 420px) {
    .kpis { grid-template-columns: minmax(0, 1fr); }
    .topbar .brand-name { display: none; }
}
</style>
