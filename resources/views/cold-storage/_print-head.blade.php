<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    .ur {
        font-family: "Noto Nastaliq Urdu", serif;
        unicode-bidi: isolate;
        direction: rtl;
        margin-left: 6px;
        color: inherit;
    }

    .label .ur, .k .ur { font-size: 12px; opacity: 0.85; }
    :root {
        --ink: #0f172a;
        --muted: #64748b;
        --line: #e2e8f0;
        --soft: #f8fafc;
        --accent: #0f766e;
        --accent-dark: #115e59;
        --bg: #eef2f6;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 28px 16px 48px;
        background: var(--bg);
        color: var(--ink);
        font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        font-size: 13px;
        line-height: 1.45;
    }

    .toolbar {
        max-width: 1100px;
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .toolbar button {
        appearance: none;
        border: 0;
        border-radius: 8px;
        background: var(--accent);
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        padding: 10px 16px;
        cursor: pointer;
    }

    .toolbar button:hover { background: var(--accent-dark); }

    .sheet {
        max-width: 1100px;
        margin: 0 auto;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.1);
        overflow: hidden;
    }

    .accent-bar {
        height: 6px;
        background: linear-gradient(90deg, var(--accent), #14b8a6);
    }

    .sheet-body {
        padding: 36px 40px 42px;
    }

    .header {
        display: flex;
        justify-content: space-between;
        gap: 28px;
        align-items: flex-start;
    }

    .brand {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        max-width: 58%;
    }

    .brand img {
        height: 52px;
        width: auto;
        object-fit: contain;
    }

    .brand-mark {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        background: var(--accent);
        color: #fff;
        display: grid;
        place-items: center;
        font-weight: 700;
        font-size: 18px;
        letter-spacing: 0.5px;
        flex-shrink: 0;
    }

    .brand-copy strong {
        display: block;
        font-size: 16px;
        color: var(--ink);
        margin-bottom: 4px;
    }

    .brand-copy span,
    .brand-copy div {
        color: var(--muted);
        font-size: 12px;
    }

    .doc-title {
        text-align: right;
    }

    .doc-title h1 {
        margin: 0;
        font-size: 28px;
        letter-spacing: 1.5px;
        font-weight: 700;
        color: var(--accent-dark);
        text-transform: uppercase;
    }

    .doc-title .number {
        margin-top: 6px;
        font-size: 13px;
        color: var(--muted);
        font-weight: 600;
    }

    .party-grid {
        margin-top: 28px;
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 20px;
    }

    .card {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 14px 16px;
    }

    .card .label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--accent);
        margin-bottom: 8px;
    }

    .card strong {
        display: block;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .card p {
        margin: 0;
        color: var(--muted);
        font-size: 12.5px;
    }

    .meta-list {
        display: grid;
        gap: 8px;
    }

    .meta-list div {
        display: grid;
        grid-template-columns: 130px 1fr;
        gap: 8px;
        font-size: 12.5px;
    }

    .meta-list .k { color: var(--muted); }
    .meta-list .v { color: var(--ink); font-weight: 600; }

    .section-title {
        margin: 28px 0 10px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead th {
        background: var(--accent-dark);
        color: #fff;
        text-align: left;
        font-size: 11px;
        font-weight: 650;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 11px 12px;
    }

    thead th:first-child { border-radius: 8px 0 0 0; }
    thead th:last-child { border-radius: 0 8px 0 0; }

    tbody td {
        padding: 12px;
        border-bottom: 1px solid var(--line);
        vertical-align: top;
        font-size: 13px;
    }

    tbody tr:nth-child(even) td { background: #fafbfc; }

    .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .muted { color: var(--muted); font-size: 12px; }

    .totals {
        width: 320px;
        margin: 22px 0 0 auto;
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow: hidden;
    }

    .totals .row {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 9px 14px;
        font-size: 13px;
        color: var(--muted);
        background: #fff;
    }

    .totals .row + .row { border-top: 1px solid var(--line); }
    .totals .row span:last-child { color: var(--ink); font-weight: 600; font-variant-numeric: tabular-nums; }

    .totals .grand {
        background: var(--accent-dark);
        color: #fff;
        font-weight: 700;
    }

    .totals .grand span:last-child { color: #fff; }

    .totals .due {
        background: #ecfdf5;
        color: var(--accent-dark);
    }

    .totals .due span:last-child { color: var(--accent-dark); }

    .notes {
        margin-top: 28px;
        padding-top: 16px;
        border-top: 1px dashed var(--line);
        color: var(--muted);
        font-size: 12px;
    }

    .footer {
        margin-top: 28px;
        display: flex;
        justify-content: space-between;
        gap: 16px;
        color: var(--muted);
        font-size: 11px;
    }

    @media print {
        body {
            background: #fff;
            padding: 0;
        }

        .toolbar { display: none !important; }

        .sheet {
            box-shadow: none;
            border-radius: 0;
            max-width: none;
        }

        .sheet-body { padding: 18mm 16mm; }

        tbody tr:nth-child(even) td { background: transparent; }

        thead th {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .totals .grand,
        .totals .due,
        .accent-bar {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }

    @media (max-width: 720px) {
        .sheet-body { padding: 24px 18px 28px; }
        .header, .party-grid { display: block; }
        .brand, .doc-title { max-width: none; }
        .doc-title { text-align: left; margin-top: 18px; }
        .totals { width: 100%; }
        .meta-list div { grid-template-columns: 96px 1fr; }
    }
</style>
