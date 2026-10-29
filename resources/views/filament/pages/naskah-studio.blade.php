{{-- Naskah Studio — Word processor web lengkap (menggabungkan WordCraft & LibreOffice Writer) --}}
<x-filament-panels::page>
    <!-- KaTeX math typesetting (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/word-studio.js') }}"></script>

    <style>
        .ns-root {
            display: flex;
            flex-direction: column;
            gap: .65rem;
            font-family: var(--rb-font-display, system-ui, -apple-system, sans-serif);
            color: var(--rb-ink, #0f172a);
        }
        .ns-root--dark {
            --rb-surface: #0f172a;
            --rb-surface-soft: #1e293b;
            --rb-surface-muted: #334155;
            --rb-ink: #f8fafc;
            --rb-ink-soft: #cbd5e1;
            --rb-ink-muted: #94a3b8;
            --rb-border-color: #334155;
            background: #0b1120;
            color: #f8fafc;
        }

        /* Topbar akses cepat */
        .ns-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .45rem .85rem;
            background: var(--rb-surface, #ffffff);
            border: 1px solid var(--rb-border-color, #e2e8f0);
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(15,23,42,.05);
        }
        .ns-topbar__left { display: flex; align-items: center; gap: .5rem; flex: 1; min-width: 0; }
        .ns-topbar__title-input {
            border: none;
            background: transparent;
            font-size: .95rem;
            font-weight: 700;
            color: inherit;
            padding: .15rem .4rem;
            border-bottom: 1px dashed var(--rb-border-color, #cbd5e1);
            outline: none;
            max-width: 320px;
        }
        .ns-topbar__title-input:focus { border-bottom-color: #2563eb; background: rgba(37,99,235,.05); }
        .ns-topbar__actions { display: flex; align-items: center; gap: .35rem; }

        /* Ribbon */
        .ns-ribbon {
            border: 1px solid var(--rb-border-color, #cbd5e1);
            background: var(--rb-surface, #ffffff);
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(15,23,42,.06);
            overflow: hidden;
        }
        .ns-ribbon__tabs {
            display: flex;
            gap: .15rem;
            flex-wrap: wrap;
            padding: .35rem .5rem 0;
            background: var(--rb-surface-soft, #f8fafc);
            border-bottom: 1px solid var(--rb-border-color, #e2e8f0);
        }
        .ns-ribbon__tab {
            border: 1px solid transparent;
            border-bottom: none;
            background: transparent;
            color: var(--rb-ink-soft, #475569);
            font-family: var(--rb-font-mono, ui-monospace, monospace);
            font-size: .67rem;
            font-weight: 700;
            padding: .35rem .7rem;
            cursor: pointer;
            border-radius: 3px 3px 0 0;
            transition: all .15s;
        }
        .ns-ribbon__tab:hover { background: var(--rb-surface, #ffffff); color: var(--rb-ink, #0f172a); }
        .ns-ribbon__tab--active {
            background: var(--rb-surface, #ffffff);
            color: #1e40af;
            border-color: var(--rb-border-color, #cbd5e1);
            border-bottom: 2px solid #2563eb;
            margin-bottom: -1px;
        }
        .ns-ribbon__body {
            display: flex;
            gap: .85rem;
            flex-wrap: wrap;
            align-items: stretch;
            padding: .5rem .75rem;
            background: var(--rb-surface, #ffffff);
            min-height: 58px;
        }
        .ns-group {
            display: flex;
            flex-direction: column;
            gap: .3rem;
            padding-right: .85rem;
            border-right: 1px solid var(--rb-border-color, #e2e8f0);
        }
        .ns-group:last-child { border-right: none; }
        .ns-group__label {
            font-family: var(--rb-font-mono, ui-monospace, monospace);
            font-size: .52rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--rb-ink-muted, #64748b);
            font-weight: 700;
        }
        .ns-group__row { display: flex; gap: .25rem; flex-wrap: wrap; align-items: center; }

        .ns-btn {
            border: 1px solid var(--rb-border-color, #cbd5e1);
            background: var(--rb-surface, #ffffff);
            color: var(--rb-ink, #0f172a);
            font-size: .65rem;
            font-weight: 600;
            padding: .25rem .5rem;
            border-radius: 3px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            line-height: 1.2;
            transition: all .12s;
        }
        .ns-btn:hover { background: var(--rb-surface-soft, #f1f5f9); border-color: #94a3b8; }
        .ns-btn--primary { background: #1e40af; color: #ffffff; border-color: #1e40af; }
        .ns-btn--primary:hover { background: #1d4ed8; }
        .ns-btn--active { background: #eff6ff; color: #1d4ed8; border-color: #3b82f6; font-weight: 700; }
        .ns-btn--icon { padding: .25rem .45rem; min-width: 1.75rem; justify-content: center; }

        /* Penggaris Horisontal (Ruler) */
        .ns-ruler {
            display: flex;
            align-items: center;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            height: 22px;
            max-width: 860px;
            margin: 0 auto;
            position: relative;
            font-family: var(--rb-font-mono, ui-monospace, monospace);
            font-size: .55rem;
            color: #64748b;
            user-select: none;
            border-radius: 2px 2px 0 0;
        }
        .ns-ruler__track {
            display: flex;
            width: 100%;
            height: 100%;
            align-items: flex-end;
            position: relative;
            padding: 0 25px;
        }
        .ns-ruler__unit {
            flex: 1;
            text-align: center;
            border-right: 1px solid #cbd5e1;
            height: 60%;
            font-size: .52rem;
            line-height: 1;
            position: relative;
        }
        .ns-ruler__margin-left, .ns-ruler__margin-right {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 25px;
            background: rgba(148,163,184,.3);
        }
        .ns-ruler__margin-left { left: 0; }
        .ns-ruler__margin-right { right: 0; }
        .ns-ruler__marker {
            position: absolute;
            top: 2px;
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 6px solid #1e40af;
            cursor: pointer;
        }

        /* Workspace & Canvas */
        .ns-workspace {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .ns-workspace { grid-template-columns: 1fr; }
        }

        /* Panel Navigasi / Navigator */
        .ns-nav {
            border: 1px solid var(--rb-border-color, #cbd5e1);
            background: var(--rb-surface, #ffffff);
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(15,23,42,.05);
            position: sticky;
            top: .5rem;
            max-height: 82vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .ns-nav__header {
            display: flex;
            border-bottom: 1px solid var(--rb-border-color, #e2e8f0);
            background: var(--rb-surface-soft, #f8fafc);
        }
        .ns-nav__tab {
            flex: 1;
            padding: .45rem .3rem;
            border: none;
            background: transparent;
            font-family: var(--rb-font-mono, ui-monospace, monospace);
            font-size: .58rem;
            font-weight: 700;
            color: var(--rb-ink-soft, #64748b);
            cursor: pointer;
            text-align: center;
        }
        .ns-nav__tab--active {
            color: #1e40af;
            background: var(--rb-surface, #ffffff);
            border-bottom: 2px solid #1e40af;
        }
        .ns-nav__body {
            padding: .4rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: .15rem;
        }
        .ns-nav__item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            text-align: left;
            border: 1px solid transparent;
            background: transparent;
            padding: .3rem .5rem;
            font-size: .7rem;
            color: var(--rb-ink, #0f172a);
            border-radius: 3px;
            cursor: pointer;
        }
        .ns-nav__item:hover { background: var(--rb-surface-soft, #f1f5f9); }
        .ns-nav__item--active { background: #eff6ff; color: #1e40af; font-weight: 600; border-color: #bfdbfe; }
        .ns-nav__item--l2 { padding-left: 1.1rem; font-size: .67rem; color: var(--rb-ink-soft, #475569); }
        .ns-nav__item--l3 { padding-left: 1.7rem; font-size: .63rem; color: var(--rb-ink-muted, #64748b); }

        /* Kanvas Lembar Dokumen */
        .ns-canvas {
            background: #cbd5e1;
            border: 1px solid var(--rb-border-color, #94a3b8);
            border-radius: 4px;
            padding: 1.5rem 1rem 3rem;
            min-height: 80vh;
            overflow-x: auto;
        }
        .ns-canvas--web { background: transparent; border: none; padding: 0; }
        .ns-canvas--read { background: #fdfbf7; padding: 2rem 1rem; }
        .ns-canvas--gridlines {
            background-image: linear-gradient(to right, rgba(148,163,184,.15) 1px, transparent 1px), linear-gradient(to bottom, rgba(148,163,184,.15) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        .ns-page {
            background: #ffffff;
            color: #0f172a;
            max-width: 860px;
            min-height: 1100px;
            margin: 0 auto;
            box-shadow: 0 4px 6px -1px rgba(15,23,42,.12), 0 20px 25px -5px rgba(15,23,42,.15);
            position: relative;
            box-sizing: border-box;
            transition: all .2s;
        }
        .ns-page--landscape {
            max-width: 1100px;
            min-height: 860px;
        }
        .ns-page--web { max-width: 100%; box-shadow: none; min-height: auto; border: none !important; }
        .ns-page--read { max-width: 720px; box-shadow: 0 1px 3px rgba(15,23,42,.08); border: none; }
        .ns-page--border { border: 2px solid #94a3b8; }

        /* Tanda air (Watermark) */
        .ns-watermark {
            position: absolute;
            top: 35%;
            left: 5%;
            right: 5%;
            text-align: center;
            font-size: 4.8rem;
            font-weight: 900;
            letter-spacing: .15em;
            color: rgba(30,64,175,.09);
            transform: rotate(-30deg);
            pointer-events: none;
            user-select: none;
            text-transform: uppercase;
            z-index: 0;
        }

        /* Blok naskah */
        .ns-blocks-wrap { position: relative; z-index: 1; }
        .ns-block {
            position: relative;
            margin-bottom: .4rem;
            border: 1px dashed transparent;
            border-radius: 3px;
            padding: .15rem .35rem;
            transition: border-color .15s, background .15s;
        }
        .ns-block:hover { border-color: #cbd5e1; }
        .ns-block--active, .ns-block:focus-within {
            border-color: #3b82f6;
            background: rgba(239, 246, 255, .55);
        }
        .ns-block__gutter {
            position: absolute;
            right: .25rem;
            top: -1.45rem;
            display: none;
            align-items: center;
            gap: .2rem;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(4px);
            border: 1px solid #cbd5e1;
            padding: .15rem .3rem;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(15,23,42,.12);
            z-index: 25;
        }
        .ns-block:hover .ns-block__gutter, .ns-block:focus-within .ns-block__gutter { display: flex; }
        .ns-block__btn {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #475569;
            font-size: .6rem;
            padding: .15rem .3rem;
            border-radius: 2px;
            cursor: pointer;
            line-height: 1;
        }
        .ns-block__btn:hover { background: #eff6ff; color: #1e40af; border-color: #93c5fd; }
        .ns-block__btn--ai { background: #fdf2f8; color: #be185d; border-color: #fbcfe8; font-weight: 700; }
        .ns-block__btn--ai:hover { background: #fce7f3; color: #9d174d; }

        /* Textarea blok */
        .ns-block textarea {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            resize: none;
            overflow: hidden;
            display: block;
            color: inherit;
        }
        .ns-block--heading textarea { font-family: var(--rb-font-display, system-ui); font-weight: 800; color: #1e40af; }
        .ns-block--h1 textarea { font-size: 1.55rem; line-height: 1.35; margin-bottom: .2rem; }
        .ns-block--h2 textarea { font-size: 1.3rem; line-height: 1.4; margin-bottom: .15rem; }
        .ns-block--h3 textarea { font-size: 1.1rem; line-height: 1.45; }
        .ns-block--quote {
            border-left: 4px solid #1e40af;
            background: #f8fafc;
            padding: .45rem .85rem;
            border-radius: 0 3px 3px 0;
        }
        .ns-block--quote textarea { font-style: italic; color: #334155; }
        .ns-block--bullet { display: flex; align-items: flex-start; gap: .4rem; }
        .ns-block--bullet::before { content: "•"; color: #1e40af; font-weight: 900; font-size: 1.1rem; line-height: 1.6; }
        .ns-block--numbered { display: flex; align-items: flex-start; gap: .4rem; }
        .ns-block--numbered__num { font-weight: 700; color: #1e40af; font-size: .85rem; line-height: 1.7; flex: 0 0 1.4rem; }

        .ns-block--dropcap textarea::first-letter {
            font-size: 3.1rem;
            float: left;
            line-height: .8;
            padding: .15rem .35rem 0 0;
            font-weight: 800;
            color: #1e40af;
            font-family: Georgia, serif;
        }
        .ns-callout {
            border-left: 4px solid #3b82f6;
            background: #eff6ff;
            padding: .65rem .85rem;
            border-radius: 0 4px 4px 0;
            margin: .4rem 0;
            display: flex;
            gap: .5rem;
        }
        .ns-callout--warning { border-left-color: #f59e0b; background: #fffbeb; }
        .ns-callout--tip { border-left-color: #10b981; background: #ecfdf5; }
        .ns-callout--note { border-left-color: #8b5cf6; background: #f5f3ff; }
        .ns-callout--success { border-left-color: #22c55e; background: #f0fdf4; }
        .ns-callout__icon { font-size: 1.1rem; line-height: 1.2; flex: 0 0 auto; }

        /* Alignment & typography classes */
        .align-left { text-align: left; }
        .align-center { text-align: center; }
        .align-right { text-align: right; }
        .align-justify { text-align: justify; }

        /* Tabel interaktif */
        .ns-table-wrap {
            overflow-x: auto;
            margin: .5rem 0;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
        }
        .ns-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
        }
        .ns-table th, .ns-table td {
            border: 1px solid #cbd5e1;
            padding: .35rem .5rem;
            min-width: 60px;
        }
        .ns-table th { background: #eff6ff; font-weight: 700; color: #1e40af; }
        .ns-table input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: inherit;
            color: inherit;
        }
        .ns-table-toolbar {
            display: flex;
            gap: .3rem;
            background: #f8fafc;
            padding: .25rem .4rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: .65rem;
        }

        /* Gambar */
        .ns-image-wrap { margin: .5rem 0; text-align: center; }
        .ns-image-wrap img { border-radius: 4px; box-shadow: 0 2px 8px rgba(15,23,42,.1); max-width: 100%; }

        /* Pemisah halaman */
        .ns-pagebreak-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            padding: .85rem 0;
            border-top: 2px dashed #94a3b8;
            border-bottom: 2px dashed #94a3b8;
            margin: 1.25rem 0;
            color: #64748b;
            font-family: var(--rb-font-mono, monospace);
            font-size: .68rem;
            font-weight: 700;
            background: #f8fafc;
            border-radius: 3px;
            user-select: none;
        }

        /* Nomor baris */
        .ns-lineno {
            position: absolute;
            left: -2.3rem;
            color: #94a3b8;
            font-family: var(--rb-font-mono, monospace);
            font-size: .62rem;
            user-select: none;
            text-align: right;
            width: 1.8rem;
        }

        /* Panel Samping (Drawer Kanan / Task Pane) */
        .ns-drawer {
            border: 1px solid var(--rb-border-color, #cbd5e1);
            background: var(--rb-surface, #ffffff);
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(15,23,42,.08);
            width: 360px;
            max-width: calc(100vw - 2rem);
            max-height: 84vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: sticky;
            top: .5rem;
        }
        .ns-drawer__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .5rem .75rem;
            background: var(--rb-surface-soft, #f8fafc);
            border-bottom: 1px solid var(--rb-border-color, #e2e8f0);
            font-family: var(--rb-font-mono, monospace);
            font-size: .65rem;
            font-weight: 700;
            color: #1e40af;
        }
        .ns-drawer__body {
            padding: .6rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }
        .ns-comment-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: .5rem;
            background: var(--rb-surface-soft, #f8fafc);
            font-size: .72rem;
        }
        .ns-comment-card--resolved { opacity: .65; border-left: 3px solid #16a34a; }
        .ns-rev-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: .5rem;
            background: #fff;
            font-size: .7rem;
        }
        .ns-rev-diff-del { color: #dc2626; text-decoration: line-through; background: #fee2e2; padding: .1rem .2rem; border-radius: 2px; }
        .ns-rev-diff-ins { color: #16a34a; font-weight: 700; background: #dcfce7; padding: .1rem .2rem; border-radius: 2px; }

        /* Modal Dialog */
        .ns-dialog-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.5);
            backdrop-filter: blur(2px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .ns-dialog {
            background: var(--rb-surface, #ffffff);
            border: 1px solid var(--rb-border-color, #cbd5e1);
            border-radius: 6px;
            width: min(520px, 96vw);
            max-height: 88vh;
            box-shadow: 0 20px 25px -5px rgba(15,23,42,.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: nsScaleIn .15s ease-out;
        }
        @keyframes nsScaleIn { from { transform: scale(.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .ns-dialog__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .65rem .95rem;
            background: #1e40af;
            color: #ffffff;
            font-family: var(--rb-font-mono, monospace);
            font-size: .72rem;
            font-weight: 700;
        }
        .ns-dialog__body {
            padding: .95rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: .75rem;
            font-size: .78rem;
        }
        .ns-dialog__footer {
            display: flex;
            justify-content: flex-end;
            gap: .4rem;
            padding: .65rem .95rem;
            background: var(--rb-surface-soft, #f8fafc);
            border-top: 1px solid var(--rb-border-color, #e2e8f0);
        }

        /* Bilah Status (Status Bar) */
        .ns-statusbar {
            display: flex;
            gap: .85rem;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid var(--rb-border-color, #cbd5e1);
            background: var(--rb-surface, #ffffff);
            border-radius: 4px;
            padding: .35rem .75rem;
            font-family: var(--rb-font-mono, monospace);
            font-size: .63rem;
            color: var(--rb-ink-muted, #64748b);
            box-shadow: 0 1px 2px rgba(15,23,42,.04);
        }
        .ns-statusbar__spacer { flex: 1; }

        /* Floating Assistant (Wordcraft / Xishie FAB) */
        .ns-fab {
            position: fixed;
            right: 1.5rem;
            bottom: 1.5rem;
            z-index: 60;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem 1rem;
            border-radius: 999px;
            background: #1e40af;
            color: #ffffff;
            border: 2px solid #0f172a;
            box-shadow: 0 6px 18px rgba(15,23,42,.35);
            font-family: var(--rb-font-mono, monospace);
            font-size: .72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
        }
        .ns-fab:hover { background: #1d4ed8; transform: translateY(-2px); }
        .ns-fab__dot { width: 9px; height: 9px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 0 3px rgba(74,222,128,.35); }

        .ns-assistant {
            position: fixed;
            right: 1.5rem;
            bottom: 5.2rem;
            z-index: 61;
            width: min(390px, calc(100vw - 2rem));
            max-height: min(74vh, 650px);
            display: flex;
            flex-direction: column;
            border: 1px solid #cbd5e1;
            background: var(--rb-surface, #ffffff);
            border-radius: 6px;
            box-shadow: 0 14px 34px rgba(15,23,42,.35);
            overflow: hidden;
        }
        .ns-assistant__titlebar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e40af;
            color: #ffffff;
            padding: .5rem .75rem;
            font-family: var(--rb-font-mono, monospace);
            font-size: .68rem;
            font-weight: 700;
        }
        .ns-assistant__body { padding: .7rem; overflow-y: auto; display: flex; flex-direction: column; gap: .6rem; }
        .ns-tools-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .3rem; }
        .ns-tool-card {
            display: flex;
            align-items: center;
            gap: .35rem;
            border: 1px solid #cbd5e1;
            background: var(--rb-surface-soft, #f8fafc);
            padding: .35rem .45rem;
            border-radius: 3px;
            cursor: pointer;
            text-align: left;
            font-family: var(--rb-font-mono, monospace);
            font-size: .58rem;
            color: var(--rb-ink, #0f172a);
        }
        .ns-tool-card:hover { border-color: #2563eb; }
        .ns-tool-card--on { background: #eff6ff; border-color: #2563eb; color: #1e40af; font-weight: 700; }
        .ns-chatlog {
            max-height: 15rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: .4rem;
        }
        .ns-msg {
            font-family: var(--rb-font-mono, monospace);
            font-size: .63rem;
            line-height: 1.55;
            padding: .45rem .6rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .ns-msg--user { background: #f1f5f9; }
        .ns-msg--assistant { background: #eff6ff; border-left: 3px solid #1e40af; }
        .ns-msg__role { display: block; font-weight: 700; font-size: .53rem; letter-spacing: .06em; text-transform: uppercase; color: #1e40af; margin-bottom: .15rem; }

        /* Unified Word Sheet & Typography Styles */
        .word-sheet-content {
            outline: none !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            line-height: 1.5;
            cursor: text;
        }
        .word-title-text {
            font-size: 26pt;
            font-weight: 700;
            color: inherit;
            line-height: 1.2;
            margin: 8px 0 16px;
            outline: none;
        }
        .word-subtitle-text {
            font-size: 14pt;
            font-weight: 400;
            color: #64748b;
            line-height: 1.4;
            margin-bottom: 24px;
            outline: none;
        }
        .word-heading-1 {
            font-size: 18pt;
            font-weight: 700;
            color: #1e40af;
            line-height: 1.3;
            margin: 24px 0 8px;
            outline: none;
        }
        .word-heading-2 {
            font-size: 14pt;
            font-weight: 600;
            color: #2563eb;
            line-height: 1.35;
            margin: 20px 0 6px;
            outline: none;
        }
        .word-heading-3 {
            font-size: 12pt;
            font-weight: 600;
            color: #1e3a8a;
            line-height: 1.4;
            margin: 16px 0 4px;
            outline: none;
        }
        .word-paragraph-text {
            font-size: 11pt;
            line-height: 1.6;
            color: inherit;
            margin-bottom: 12px;
            outline: none;
        }
        .word-drop-cap::first-letter {
            float: left;
            font-size: 3.2em;
            line-height: 0.8;
            padding-top: 4px;
            padding-right: 8px;
            padding-bottom: 2px;
            font-weight: 700;
            color: var(--rb-primary, #0f6cbd);
        }
        .word-quote-block {
            border-left: 4px solid #3b82f6;
            padding: 8px 16px;
            margin: 16px 0;
            background: rgba(59, 130, 246, 0.05);
            font-style: italic;
            color: #334155;
            outline: none;
        }
        .word-quote-text {
            margin: 0;
            outline: none;
        }
        .word-bullet-list {
            margin: 8px 0 16px 28px;
            padding-left: 8px;
            list-style-type: disc;
        }
        .word-bullet-list li {
            font-size: 11pt;
            line-height: 1.6;
            margin-bottom: 4px;
            outline: none;
        }
        .word-numbered-list {
            margin: 8px 0 16px 28px;
            padding-left: 8px;
            list-style-type: decimal;
        }
        .word-numbered-list li {
            font-size: 11pt;
            line-height: 1.6;
            margin-bottom: 4px;
            outline: none;
        }
        .word-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            border: 1px solid #d1d5db;
        }
        .word-table th, .word-table td {
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            min-height: 24px;
            outline: none;
        }
        .word-table th {
            background: #f8fafc;
            font-weight: 700;
        }
        .word-page-break-marker {
            user-select: none;
        }

        /* Floating Mini-toolbar & Dropdowns */
        .word-mini-toolbar {
            display: inline-flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);
            padding: 3px 6px;
            gap: 2px;
            z-index: 1000;
        }
        .ns-root--dark .word-mini-toolbar {
            background: #1e293b;
            border-color: #475569;
        }
        .word-mini-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 28px;
            min-width: 28px;
            padding: 0 6px;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            color: #1e293b;
        }
        .ns-root--dark .word-mini-btn {
            color: #f1f5f9;
        }
        .word-mini-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        .ns-root--dark .word-mini-btn:hover {
            background: #334155;
            border-color: #64748b;
        }
        .word-mini-btn.active {
            background: #e0e7ff;
            border-color: #818cf8;
            color: #3730a3;
        }
        .word-mini-copilot-btn {
            background: linear-gradient(135deg, #eef2ff 0%, #f3e8ff 100%);
            border: 1px solid #c7d2fe;
            color: #4338ca;
            font-weight: 700;
        }
        .word-mini-divider {
            width: 1px;
            height: 18px;
            background: #e2e8f0;
            margin: 0 4px;
        }
        .word-mini-dropdown {
            position: absolute;
            top: 32px;
            left: 0;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            padding: 4px;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .ns-root--dark .word-mini-dropdown {
            background: #1e293b;
            border-color: #475569;
        }
        .word-mini-dropdown-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            color: #334155;
        }
        .ns-root--dark .word-mini-dropdown-item {
            color: #e2e8f0;
        }
        .word-mini-dropdown-item:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .ns-root--dark .word-mini-dropdown-item:hover {
            background: #334155;
            color: #93c5fd;
        }

        /* Right-Click Context Menu (Microsoft Word & LibreOffice Writer Menu) */
        .word-context-menu {
            position: fixed;
            z-index: 9999;
            min-width: 255px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 6px;
            font-size: 12.5px;
            color: #1e293b;
            user-select: none;
        }
        .ns-root--dark .word-context-menu {
            background: #1e293b;
            border-color: #334155;
            color: #f1f5f9;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .word-context-minibar {
            display: flex;
            align-items: center;
            gap: 3px;
            padding: 4px 6px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            margin-bottom: 6px;
        }
        .ns-root--dark .word-context-minibar {
            background: #0f172a;
            border-color: #334155;
        }
        .word-context-sep {
            width: 1px;
            height: 16px;
            background: #cbd5e1;
            margin: 0 3px;
        }
        .ns-root--dark .word-context-sep {
            background: #475569;
        }
        .word-context-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 4px 0;
        }
        .ns-root--dark .word-context-divider {
            background: #334155;
        }
        .word-context-group {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        .word-context-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 5px 9px;
            border: none;
            border-radius: 4px;
            background: transparent;
            color: inherit;
            font-size: 12px;
            cursor: pointer;
            text-align: left;
            transition: background 0.1s ease;
        }
        .word-context-item:hover {
            background: #f1f5f9;
        }
        .ns-root--dark .word-context-item:hover {
            background: #334155;
        }
        .word-context-label {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .word-context-shortcut {
            font-size: 10.5px;
            color: #64748b;
            font-family: inherit;
        }
        .ns-root--dark .word-context-shortcut {
            color: #94a3b8;
        }
        .word-context-copilot-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            color: #2563eb;
        }
        .ns-root--dark .word-context-copilot-badge {
            color: #60a5fa;
        }
        .word-in-canvas-copilot {
            position: absolute;
            z-index: 998;
        }
        .word-block-copilot-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%);
            border: 1px solid #c7d2fe;
            border-radius: 14px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 600;
            color: #4f46e5;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
        }
        .word-block-copilot-btn:hover {
            background: #eef2ff;
            border-color: #818cf8;
        }
    </style>

    <div class="ns-root @if($darkMode) ns-root--dark @endif"
         style="position: relative;"
         x-data="wordStudioApp({
             wire: $wire,
             zenMode: @entangle('zenMode'),
             activeTaskPane: @entangle('activeTaskPane'),
             commentsOpen: @entangle('showComments'),
             revisionsOpen: @entangle('showRevisions'),
             assistantPane: @entangle('assistantOpen'),
             navTab: @entangle('navigationTab'),
             activeBlock: @entangle('activeBlock')
         })">

        <!-- ===================================================================== -->
        <!-- FLOATING SELECTION MINI-TOOLBAR (MICROSOFT WORD 365 BUBBLE MENU)      -->
        <!-- ===================================================================== -->
        <div x-cloak
            x-show="showMiniToolbar"
            @click.outside="showMiniToolbar = false"
            class="word-mini-toolbar"
            :style="`top: ${toolbarTop}px; left: ${toolbarLeft}px; position: absolute; z-index: 999;`"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 transform translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 transform translate-y-2 scale-95">

            <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.bold }" @mousedown.prevent="format('bold')" title="Huruf Tebal (Ctrl+B)">
                <strong>B</strong>
            </button>
            <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.italic }" @mousedown.prevent="format('italic')" title="Huruf Miring (Ctrl+I)">
                <em>I</em>
            </button>
            <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.underline }" @mousedown.prevent="format('underline')" title="Garis Bawah (Ctrl+U)">
                <u>U</u>
            </button>
            <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.strike }" @mousedown.prevent="format('strikeThrough')" title="Coret (Strikethrough)">
                <s>S</s>
            </button>

            <span class="word-mini-divider"></span>

            <button type="button" class="word-mini-btn" @mousedown.prevent="format('hiliteColor', '#fde68a')" title="Stabilo Kuning">
                <span style="background:#fde68a; padding:1px 4px; border-radius:2px; font-weight:700; color:#0f172a;">A</span>
            </button>
            <button type="button" class="word-mini-btn" @mousedown.prevent="format('foreColor', '#0f6cbd')" title="Warna Biru Word">
                <span style="color:#0f6cbd; font-weight:700; border-bottom:2px solid #0f6cbd;">A</span>
            </button>

            <span class="word-mini-divider"></span>

            <!-- Xishie Copilot Quick Action Dropdown -->
            <div style="position:relative;">
                <button type="button" class="word-mini-btn word-mini-copilot-btn" @click="copilotMenuOpen = !copilotMenuOpen" title="Bantuan Cerdas Xishie Copilot">
                    <span>✨ Xishie ▾</span>
                </button>
                <div x-show="copilotMenuOpen" @click.outside="copilotMenuOpen = false" class="word-mini-dropdown" style="width: 210px;">
                    <button type="button" @mousedown.prevent @click="runAi('/poles')" class="word-mini-dropdown-item">
                        <span>🪄 Poles Kalimat Terpilih</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="runAi('/parafrase')" class="word-mini-dropdown-item">
                        <span>🔄 Parafrase Anti-Plagiasi</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="runAi('/kembangkan')" class="word-mini-dropdown-item">
                        <span>✍️ Elaborasi &amp; Kembangkan</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="runAi('/ringkaskan')" class="word-mini-dropdown-item">
                        <span>✂️ Padatkan / Buat Ringkas</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="runAi('/puebi')" class="word-mini-dropdown-item">
                        <span>🔤 Perbaiki Ejaan &amp; PUEBI</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="runAi('/terjemahkan')" class="word-mini-dropdown-item">
                        <span>🌐 Terjemahkan Akademik</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="runAi('/sitasi')" class="word-mini-dropdown-item">
                        <span>📚 Rekomendasikan Sitasi</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="runAi('/cek-pedoman')" class="word-mini-dropdown-item">
                        <span>🎯 Cek Kepatuhan Pedoman</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- RIGHT-CLICK CONTEXT MENU (MICROSOFT WORD & LIBREOFFICE WRITER MENU)   -->
        <!-- ===================================================================== -->
        <div x-cloak
            x-show="showContextMenu"
            @click.outside="closeContextMenu()"
            @keydown.window.escape="closeContextMenu()"
            class="word-context-menu"
            :style="`top: ${contextMenuY}px; left: ${contextMenuX}px;`"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95">

            <!-- Mini-Toolbar Strip at Top -->
            <div class="word-context-minibar">
                <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.bold }" @mousedown.prevent="format('bold')" title="Huruf Tebal (Ctrl+B)"><strong>B</strong></button>
                <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.italic }" @mousedown.prevent="format('italic')" title="Huruf Miring (Ctrl+I)"><em>I</em></button>
                <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.underline }" @mousedown.prevent="format('underline')" title="Garis Bawah (Ctrl+U)"><u>U</u></button>
                <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.strike }" @mousedown.prevent="format('strikeThrough')" title="Coret Tengah (Ctrl+D)"><s>S</s></button>
                <span class="word-context-sep"></span>
                <button type="button" class="word-mini-btn" @mousedown.prevent="applyTextColor('#dc2626')" title="Warna Font"><span style="color:#dc2626; font-weight:700;">A</span></button>
                <button type="button" class="word-mini-btn" @mousedown.prevent="applyHighlight('#fef08a')" title="Sorotan Teks"><span style="background:#fef08a; padding:1px 3px; border-radius:2px; font-weight:700;">✎</span></button>
                <button type="button" class="word-mini-btn" @mousedown.prevent="clearDirectFormatting()" title="Hapus Format Langsung (Ctrl+Space)">T<sub>x</sub></button>
            </div>

            <!-- Clipboard Group -->
            <div class="word-context-group">
                <button type="button" class="word-context-item" @mousedown.prevent="cut(); closeContextMenu()">
                    <span class="word-context-label">
                        <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.294a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z"/></svg>
                        Potong (Cut)
                    </span>
                    <span class="word-context-shortcut">Ctrl+X</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="copy(); closeContextMenu()">
                    <span class="word-context-label">
                        <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
                        Salin (Copy)
                    </span>
                    <span class="word-context-shortcut">Ctrl+C</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="paste(); closeContextMenu()">
                    <span class="word-context-label">
                        <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                        Tempel (Paste)
                    </span>
                    <span class="word-context-shortcut">Ctrl+V</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="copyCurrentFormat(); closeContextMenu()">
                    <span class="word-context-label">
                        <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
                        Salin Format (Format Painter)
                    </span>
                    <span class="word-context-shortcut">Ctrl+Shift+C</span>
                </button>
            </div>

            <!-- Table Tools Contextual Group (ketika klik kanan di dalam sel tabel) -->
            <template x-if="contextIsTable">
                <div class="word-context-group">
                    <div class="word-context-divider"></div>
                    <div style="padding: 2px 9px 4px; font-size: 10px; font-weight: 700; color: #0f6cbd; text-transform: uppercase; letter-spacing: 0.05em;">
                        Alat Tabel (Table Tools)
                    </div>
                    <button type="button" class="word-context-item" @mousedown.prevent="insertTableRow(true); closeContextMenu()">
                        <span class="word-context-label">➕ Sisipkan Baris di Bawah</span>
                        <span class="word-context-shortcut">Row Below</span>
                    </button>
                    <button type="button" class="word-context-item" @mousedown.prevent="insertTableRow(false); closeContextMenu()">
                        <span class="word-context-label">➕ Sisipkan Baris di Atas</span>
                        <span class="word-context-shortcut">Row Above</span>
                    </button>
                    <button type="button" class="word-context-item" @mousedown.prevent="insertTableColumn(true); closeContextMenu()">
                        <span class="word-context-label">➕ Sisipkan Kolom di Kanan</span>
                        <span class="word-context-shortcut">Col Right</span>
                    </button>
                    <button type="button" class="word-context-item" @mousedown.prevent="insertTableColumn(false); closeContextMenu()">
                        <span class="word-context-label">➕ Sisipkan Kolom di Kiri</span>
                        <span class="word-context-shortcut">Col Left</span>
                    </button>
                    <button type="button" class="word-context-item" style="color:#dc2626;" @mousedown.prevent="deleteTableRow(); closeContextMenu()">
                        <span class="word-context-label">🗑️ Hapus Baris Ini</span>
                        <span class="word-context-shortcut">Del Row</span>
                    </button>
                    <button type="button" class="word-context-item" style="color:#dc2626;" @mousedown.prevent="deleteTableColumn(); closeContextMenu()">
                        <span class="word-context-label">🗑️ Hapus Kolom Ini</span>
                        <span class="word-context-shortcut">Del Col</span>
                    </button>
                    <button type="button" class="word-context-item" style="color:#dc2626;" @mousedown.prevent="deleteTable(); closeContextMenu()">
                        <span class="word-context-label">✕ Hapus Seluruh Tabel</span>
                        <span class="word-context-shortcut">Del Table</span>
                    </button>
                </div>
            </template>

            <div class="word-context-divider"></div>

            <!-- Styles Group -->
            <div class="word-context-group">
                <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('normal'); closeContextMenu()">
                    <span class="word-context-label">¶ Paragraf Normal</span>
                    <span class="word-context-shortcut">Ctrl+Alt+0</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('heading1'); closeContextMenu()">
                    <span class="word-context-label" style="font-weight:700;">H1 Judul 1 (Heading 1)</span>
                    <span class="word-context-shortcut">Ctrl+Alt+1</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('heading2'); closeContextMenu()">
                    <span class="word-context-label" style="font-weight:600;">H2 Judul 2 (Heading 2)</span>
                    <span class="word-context-shortcut">Ctrl+Alt+2</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('bullet'); closeContextMenu()">
                    <span class="word-context-label">• Daftar Butir (Bullets)</span>
                    <span class="word-context-shortcut">Ctrl+Shift+L</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('numbered'); closeContextMenu()">
                    <span class="word-context-label">1. Daftar Bernomor (Numbered)</span>
                    <span class="word-context-shortcut">1. + Spasi</span>
                </button>
            </div>

            <div class="word-context-divider"></div>

            <!-- Insert Group -->
            <div class="word-context-group">
                <button type="button" class="word-context-item" @mousedown.prevent="insertPageBreak(); closeContextMenu()">
                    <span class="word-context-label">
                        <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                        Pemisah Halaman (Page Break)
                    </span>
                    <span class="word-context-shortcut">Ctrl+Enter</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="insertTable(3, 3); closeContextMenu()">
                    <span class="word-context-label">
                        <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1v-2h5v1a1 1 0 01-1 1h-4zm5-4h-5V8h5v2zM9 8v2H4V8a1 1 0 011-1h4z" clip-rule="evenodd"/></svg>
                        Sisipkan Tabel 3×3
                    </span>
                    <span class="word-context-shortcut">Word Grid</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="insertHorizontalRule(); closeContextMenu()">
                    <span class="word-context-label">— Garis Pembatas Horisontal</span>
                    <span class="word-context-shortcut">--- + Enter</span>
                </button>
            </div>

            <div class="word-context-divider"></div>

            <!-- Xishie Assistant AI Copilot Group -->
            <div class="word-context-group">
                <div style="padding: 2px 9px 4px; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">
                    Xishie Assistant AI Copilot
                </div>
                <button type="button" class="word-context-item" @mousedown.prevent="triggerContextAssistant('/poles'); closeContextMenu()">
                    <span class="word-context-label">
                        <span class="word-context-copilot-badge">🪄 /poles</span>
                        <span>Poles Gaya Bahasa & Diksi</span>
                    </span>
                    <span class="word-context-shortcut">AI</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="triggerContextAssistant('/parafrase'); closeContextMenu()">
                    <span class="word-context-label">
                        <span class="word-context-copilot-badge">🔄 /parafrase</span>
                        <span>Parafrase Kalimat Terpilih</span>
                    </span>
                    <span class="word-context-shortcut">AI</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="triggerContextAssistant('/puebi'); closeContextMenu()">
                    <span class="word-context-label">
                        <span class="word-context-copilot-badge">✍️ /puebi</span>
                        <span>Koreksi Ejaan & PUEBI</span>
                    </span>
                    <span class="word-context-shortcut">AI</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="triggerContextAssistant('/ringkas'); closeContextMenu()">
                    <span class="word-context-label">
                        <span class="word-context-copilot-badge">📋 /ringkas</span>
                        <span>Ringkas Teks Dokumen</span>
                    </span>
                    <span class="word-context-shortcut">AI</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="$wire.openDialog('execute_model'); closeContextMenu()">
                    <span class="word-context-label">
                        <span class="word-context-copilot-badge" style="background:#4f46e5; color:#ffffff;">⚡ Model</span>
                        <span>Eksekusi Model AI Sekarang</span>
                    </span>
                    <span class="word-context-shortcut">Alt+X</span>
                </button>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- FLOATING IN-CANVAS COPILOT BUBBLE (MICROSOFT WORD 365 COPILOT INLINE) -->
        <!-- ===================================================================== -->
        <div x-cloak
            x-show="showBlockCopilot && !showMiniToolbar"
            @click.outside="showBlockCopilot = false"
            class="word-in-canvas-copilot"
            :style="`top: ${blockCopilotTop}px; left: ${blockCopilotLeft}px;`"
            x-data="{ blockCopilotMenu: false }"
            title="Xishie Assistant AI Copilot">
            <div style="position:relative; display:inline-flex; align-items:center;">
                <button type="button" class="word-block-copilot-btn" @click="blockCopilotMenu = !blockCopilotMenu">
                    <span style="font-size:11px;">✨</span>
                    <span>Copilot ▾</span>
                </button>
                <div x-show="blockCopilotMenu" @click.outside="blockCopilotMenu = false" class="word-mini-dropdown" style="width: 220px; top: 28px; right: 0;">
                    <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/poles')" class="word-mini-dropdown-item">
                        <span>🪄 Poles Gaya Bahasa</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/parafrase')" class="word-mini-dropdown-item">
                        <span>🔄 Parafrase Akademik</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/kembangkan')" class="word-mini-dropdown-item">
                        <span>✍️ Kembangkan Paragraf</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/puebi')" class="word-mini-dropdown-item">
                        <span>🔤 Periksa PUEBI/KBBI</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/rumus')" class="word-mini-dropdown-item">
                        <span>π Rumus / Persamaan LaTeX</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/sitasi')" class="word-mini-dropdown-item">
                        <span>📚 Cari Sitasi / Referensi</span>
                    </button>
                    <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; showBlockCopilot = false; $wire.openDialog('execute_model')" class="word-mini-dropdown-item" style="color:#4f46e5; font-weight:700;">
                        <span>⚡ Eksekusi Model AI Sekarang</span>
                    </button>
                    <div style="border-top:1px solid #e1dfdd; margin:4px 0;"></div>
                    <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; showBlockCopilot = false; $wire.openAssistant()" class="word-mini-dropdown-item" style="color:#0f6cbd; font-weight:600;">
                        <span>💬 Buka Panel Chat Xishie →</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ================= BILAH AKSES CEPAT (TOPBAR) ================= --}}
        <header class="ns-topbar">
            <div class="ns-topbar__left">
                <button type="button" class="ns-btn ns-btn--primary" @click="saveDocument()" wire:loading.attr="disabled" wire:target="save" title="Simpan Dokumen (Ctrl+S)">
                    <span wire:loading.remove wire:target="save">&#128190; Simpan</span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </button>
                <button type="button" class="ns-btn ns-btn--icon" @click="undo()" title="Urungkan (Undo Ctrl+Z)">&#8630;</button>
                <button type="button" class="ns-btn ns-btn--icon" @click="redo()" title="Ulangi (Redo Ctrl+Y)">&#8631;</button>

                <input type="text" class="ns-topbar__title-input" wire:model="docTitle" wire:change="markDirty" placeholder="Judul Dokumen" aria-label="Judul Dokumen" />

                @if ($dirty)
                    <span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:.6rem; padding:.15rem .4rem; border-radius:3px; font-weight:700;">● Belum disimpan</span>
                @elseif ($lastSavedAt)
                    <span style="color:var(--rb-ink-muted); font-size:.62rem; font-family:var(--rb-font-mono);">&#10003; Tersimpan {{ $lastSavedAt }}</span>
                @endif

                @if ($copiedFormat)
                    <span style="background:#fef3c7; color:#b45309; font-size:.6rem; padding:.15rem .45rem; border-radius:3px; font-weight:700;">&#128396; Sapu Format Aktif</span>
                @endif
            </div>

            <div class="ns-topbar__actions">
                <a href="{{ route('studio.index', ['document' => $documentId]) }}" class="ns-btn ns-btn--primary" style="background:#0f6cbd; color:#ffffff; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;" title="Buka Mode Penuh Standalone Word &amp; LibreOffice Studio">&#128065; Buka Full Word Studio &rarr;</a>
                <button type="button" class="ns-btn" wire:click="openDialog('execute_model')" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #ffffff; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Eksekusi Model AI Sekarang (Alt+X)">⚡ Eksekusi Model</button>
                <button type="button" class="ns-btn" wire:click="openAssistant" style="background: linear-gradient(135deg, #eef2ff 0%, #f3e8ff 100%); border: 1px solid #c7d2fe; color: #4338ca; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Buka Xishie Assistant (AI Copilot Penulisan Ilmiah)">&#10024; Copilot</button>
                <button type="button" class="ns-btn" wire:click="openDialog('palette')" title="Pusat Perintah &amp; Fitur (Wordcraft HUD / Ctrl+K)">&#128269; Perintah (Ctrl+K)</button>
                <button type="button" class="ns-btn" wire:click="openDialog('properties')" title="Properti Dokumen &amp; Metadata">&#8505; Properti</button>
                <button type="button" class="ns-btn" wire:click="openDialog('shortcuts')" title="Tombol Pintas Papan Ketik">&#9000; Pintas</button>
                <button type="button" class="ns-btn @if($viewMode === 'read') ns-btn--active @endif" wire:click="setViewMode('{{ $viewMode === 'read' ? 'print' : 'read' }}')" title="Mode Fokus / Bebas Gangguan">&#128214; Fokus</button>
                <button type="button" class="ns-btn" wire:click="toggleDarkMode" title="Ganti Mode Gelap / Terang">&#9680; {{ $darkMode ? 'Terang' : 'Gelap' }}</button>
                <button type="button" class="ns-btn" x-on:click="window.print()" title="Cetak Dokumen">&#128424; Cetak</button>
                <button type="button" class="ns-btn" wire:click="openDialog('stats')" title="Statistik &amp; Keterbacaan">&#128202; Statistik</button>
            </div>
        </header>

        {{-- ================= RIBBON UTAMA ================= --}}
        <nav class="ns-ribbon" aria-label="Ribbon Naskah Studio">
            <div class="ns-ribbon__tabs">
                @foreach ($ribbonTabs as $key => $label)
                    <button type="button" class="ns-ribbon__tab @if($activeTab === $key) ns-ribbon__tab--active @endif" wire:click="$set('activeTab', '{{ $key }}')">{{ $label }}</button>
                @endforeach
            </div>

            <div class="ns-ribbon__body">
                {{-- TAB: BERKAS (FILE) --}}
                @if ($activeTab === 'file')
                    <div class="ns-group">
                        <span class="ns-group__label">Dokumen</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="newDocument" title="Dokumen baru">&#128196; Baru</button>
                            <button type="button" class="ns-btn ns-btn--primary" @click="saveDocument()" title="Simpan naskah">&#128190; Simpan</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Ekspor Format</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="export('docx')" title="Ekspor ke Microsoft Word (.docx)">&#128196; DOCX</button>
                            <button type="button" class="ns-btn" wire:click="export('odt')" title="Ekspor ke LibreOffice Writer (.odt)">&#128214; ODT</button>
                            <button type="button" class="ns-btn" wire:click="export('html')" title="Ekspor HTML / Print Web">&#127760; HTML</button>
                            <button type="button" class="ns-btn" wire:click="export('md')" title="Ekspor Markdown">&#9888; MD</button>
                            <button type="button" class="ns-btn" wire:click="export('rtf')" title="Ekspor Rich Text Format">&#128221; RTF</button>
                            <button type="button" class="ns-btn" wire:click="export('latex')" title="Ekspor LaTeX (.tex)">&#8734; LaTeX</button>
                            <button type="button" class="ns-btn" wire:click="export('txt')" title="Ekspor Teks Polos">TXT</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Pengaturan &amp; Cetak</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="openDialog('pagesetup')">&#9881; Tata Kertas</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('properties')">&#8505; Properti</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('snapshots')">&#128193; Versi ({{ count($snapshots) }})</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('stats')">&#128202; Info &amp; Statistik</button>
                            <button type="button" class="ns-btn" x-on:click="window.print()">&#128424; Cetak</button>
                        </div>
                    </div>

                {{-- TAB: HOME (BERANDA) --}}
                @elseif ($activeTab === 'home')
                    <div class="ns-group">
                        <span class="ns-group__label">Papan Klip</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" @mousedown.prevent="paste()" title="Tempel dari Clipboard (Ctrl+V)">📋 Tempel</button>
                            <button type="button" class="ns-btn ns-btn--icon" @mousedown.prevent="cut()" title="Potong Teks (Ctrl+X)">✂</button>
                            <button type="button" class="ns-btn ns-btn--icon" @mousedown.prevent="copy()" title="Salin Teks (Ctrl+C)">⎘</button>
                            <button type="button" class="ns-btn" @mousedown.prevent="copyCurrentFormat()" title="Sapu Format (Format Painter)">🖌 Format</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Huruf (Font)</span>
                        <div class="ns-group__row">
                            <select class="ns-btn" style="padding:.2rem .35rem;" @mousedown="saveSelection()" @change="applyFontFamily($event.target.value)" :value="currentFormat.fontFamily || '{{ $styleMeta['fontFamily'] ?? 'serif' }}'">
                                @foreach ($fontFamilies as $fKey => $fLabel)
                                    <option value="{{ $fKey }}" {{ ($styleMeta['fontFamily'] ?? '') === $fKey ? 'selected' : '' }}>{{ $fLabel }}</option>
                                @endforeach
                            </select>
                            <select class="ns-btn" style="padding:.2rem .35rem;" @mousedown="saveSelection()" @change="applyFontSize($event.target.value)" :value="currentFormat.fontSize || {{ $styleMeta['fontSize'] ?? 16 }}">
                                @foreach ($fontSizes as $fSize)
                                    <option value="{{ $fSize }}" {{ ($styleMeta['fontSize'] ?? 16) == $fSize ? 'selected' : '' }}>{{ $fSize }} pt</option>
                                @endforeach
                            </select>
                            <button type="button" class="ns-btn ns-btn--icon" @mousedown.prevent="adjustFontSize(1)" title="Perbesar Font (Ctrl+])">A&#9650;</button>
                            <button type="button" class="ns-btn ns-btn--icon" @mousedown.prevent="adjustFontSize(-1)" title="Perkecil Font (Ctrl+[)">A&#9660;</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Gaya Huruf</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.bold }" @mousedown.prevent="format('bold')" title="Tebal (Ctrl+B)"><strong>B</strong></button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.italic }" @mousedown.prevent="format('italic')" title="Miring (Ctrl+I)"><em>I</em></button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.underline }" @mousedown.prevent="format('underline')" title="Garis Bawah (Ctrl+U)"><span style="text-decoration:underline;">U</span></button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.strike }" @mousedown.prevent="format('strikeThrough')" title="Coret"><span style="text-decoration:line-through;">S</span></button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.subscript }" @mousedown.prevent="format('subscript')" title="Subscript">X<sub>2</sub></button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.superscript }" @mousedown.prevent="format('superscript')" title="Superscript">X<sup>2</sup></button>
                            <button type="button" class="ns-btn" @mousedown.prevent="clearCurrentFormat()" title="Hapus Format Huruf &amp; Paragraf (Ctrl+Space)">&#129529; Bersihkan</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Warna &amp; Sorotan</span>
                        <div class="ns-group__row">
                            @foreach ($colors as $color)
                                <button type="button" style="width:16px; height:16px; border-radius:2px; border:1px solid #cbd5e1; background:{{ $color }}; cursor:pointer;"
                                        @mousedown.prevent="format('foreColor', '{{ $color }}')" title="Warna teks {{ $color }}"></button>
                            @endforeach
                            <span style="border-left:1px solid #e2e8f0; height:16px; margin:0 .15rem;"></span>
                            @foreach ($highlights as $hl)
                                <button type="button" style="width:16px; height:16px; border-radius:2px; border:1px solid #cbd5e1; background:{{ $hl }}; cursor:pointer;"
                                        @mousedown.prevent="format('hiliteColor', '{{ $hl }}')" title="Sorot warna {{ $hl }}"></button>
                            @endforeach
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Paragraf</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.align === 'left' }" @mousedown.prevent="applyAlignment('left')" title="Rata Kiri (Ctrl+L)">&#9776;</button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.align === 'center' }" @mousedown.prevent="applyAlignment('center')" title="Rata Tengah (Ctrl+E)">&#9778;</button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.align === 'right' }" @mousedown.prevent="applyAlignment('right')" title="Rata Kanan (Ctrl+R)">&#9777;</button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.align === 'justify' }" @mousedown.prevent="applyAlignment('justify')" title="Rata Kanan-Kiri (Ctrl+J)">&#9779;</button>
                            <button type="button" class="ns-btn ns-btn--icon" @mousedown.prevent="applyIndent(-1)" title="Kurangi Indentasi (Shift+Tab)">&#8676;</button>
                            <button type="button" class="ns-btn ns-btn--icon" @mousedown.prevent="applyIndent(1)" title="Tambah Indentasi (Tab)">&#8677;</button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.isBullet }" @mousedown.prevent="format('insertUnorderedList')" title="Daftar Poin (Bullet)">• List</button>
                            <button type="button" class="ns-btn ns-btn--icon" :class="{ 'ns-btn--active': currentFormat.isNumbered }" @mousedown.prevent="format('insertOrderedList')" title="Daftar Nomor">1. List</button>
                            <div style="position:relative; display:inline-block;" x-data="{ openSpacing: false }">
                                <button type="button" class="ns-btn ns-btn--icon" @click="openSpacing = !openSpacing" title="Spasi Baris (1.0, 1.15, 1.5, 2.0)">↕ ▾</button>
                                <div x-show="openSpacing" @click.outside="openSpacing = false" class="word-mini-dropdown" style="width: 140px; top: 32px; left: 0;">
                                    <button type="button" @click="openSpacing = false" @mousedown.prevent="applyLineSpacing('1.0')" class="word-mini-dropdown-item">1.0 Tunggal</button>
                                    <button type="button" @click="openSpacing = false" @mousedown.prevent="applyLineSpacing('1.15')" class="word-mini-dropdown-item">1.15 Normal</button>
                                    <button type="button" @click="openSpacing = false" @mousedown.prevent="applyLineSpacing('1.5')" class="word-mini-dropdown-item">1.5 Akademik</button>
                                    <button type="button" @click="openSpacing = false" @mousedown.prevent="applyLineSpacing('2.0')" class="word-mini-dropdown-item">2.0 Ganda</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Galeri Gaya</span>
                        <div class="ns-group__row">
                            @foreach ($stylesGallery as $styleKey => $styleLabel)
                                <button type="button" class="ns-btn" :class="{ 'ns-btn--active': currentFormat.style === '{{ $styleKey }}' }" @mousedown.prevent="applyBlockStyle('{{ $styleKey }}')" title="Terapkan {{ $styleLabel }}">{{ $styleLabel }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Sunting</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="openDialog('find')">&#128269; Cari &amp; Ganti</button>
                            <button type="button" class="ns-btn" wire:click="runAutoCorrect" title="Auto-Correct Tipografi (—, →, ©, ®, ™, ½, …)">&#10024; Auto-Correct</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Model AI Aktif</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="openDialog('execute_model')" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #ffffff; font-weight: 700;" title="Eksekusi model AI saat ini ke naskah">⚡ Eksekusi Model</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('polish')" title="Poles ragam bahasa akademik pada halaman saat ini">✨ Poles Cepat</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('expand')" title="Kembangkan argumen ilmiah">📖 Kembangkan</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('novelty')" title="Formulasi kebaruan riset (novelty)">💡 Kebaruan</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('abstract')" title="Susun draf abstrak akademik">📝 Abstrak</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('conclusion')" title="Formulasi kesimpulan & saran">🏁 Kesimpulan</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Copilot</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="openAssistant" style="background: linear-gradient(135deg, #eef2ff 0%, #f3e8ff 100%); border: 1px solid #c7d2fe; color: #4338ca; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Buka Xishie Assistant (AI Copilot Penulisan Ilmiah)">&#10024; Copilot</button>
                        </div>
                    </div>

                {{-- TAB: SISIPKAN (INSERT) --}}
                @elseif ($activeTab === 'insert')
                    <div class="ns-group">
                        <span class="ns-group__label">Blok Teks</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="addBlock('heading')">+ Judul</button>
                            <button type="button" class="ns-btn" wire:click="addBlock('paragraph')">+ Paragraf</button>
                            <button type="button" class="ns-btn" wire:click="addBlock('quote')">&ldquo; Kutipan</button>
                            <button type="button" class="ns-btn" wire:click="insertCallout('info')">&#128161; Catatan</button>
                            <button type="button" class="ns-btn" wire:click="addBlock('bullet')">+ Poin</button>
                            <button type="button" class="ns-btn" wire:click="addBlock('numbered')">+ Nomor</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Tabel &amp; Gambar</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" @mousedown.prevent="insertTable(3, 3)" title="Sisipkan Tabel 3x3">&#8862; Sisipkan Tabel</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('image')">&#128444; Sisipkan Gambar</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Simbol &amp; Rumus</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="openDialog('equation')">&#960; Rumus (LaTeX)</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('symbols')">&#937; Simbol Khusus</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('footnote')">&#128278; Catatan Kaki</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('link')">&#128279; Tautan</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Pemisah &amp; Bidang</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" @mousedown.prevent="insertPageBreak()" title="Pemisah Halaman (Ctrl+Enter)">&#9986; Pemisah Halaman</button>
                            <button type="button" class="ns-btn" @mousedown.prevent="insertHorizontalRule()" title="Garis Pembatas (---)">&#8213; Garis Pembatas</button>
                            <button type="button" class="ns-btn" wire:click="insertDate">&#128197; Tanggal</button>
                            <button type="button" class="ns-btn" wire:click="insertPageNumberField"># Nomor Halaman</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Generasi Model AI</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="openDialog('execute_model')" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #ffffff; font-weight: 700;" title="Eksekusi model AI saat ini ke naskah">⚡ Eksekusi Model</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('table')" title="Sintesis matriks tabel data akademik menggunakan model aktif">📊 Tabel AI</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('equation')" title="Formulasikan rumus LaTeX menggunakan model aktif">∑ Rumus AI</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Otomatisasi</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="insertTableOfContents">&#9776; Daftar Isi</button>
                            <button type="button" class="ns-btn" wire:click="insertBibliography">&#128218; Daftar Pustaka</button>
                        </div>
                    </div>

                {{-- TAB: DESAIN (DESIGN) --}}
                @elseif ($activeTab === 'design')
                    <div class="ns-group">
                        <span class="ns-group__label">Tema Dokumen</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn @if(($styleMeta['theme'] ?? '') === 'academic') ns-btn--active @endif" wire:click="applyTheme('academic')">Akademik</button>
                            <button type="button" class="ns-btn @if(($styleMeta['theme'] ?? '') === 'modern') ns-btn--active @endif" wire:click="applyTheme('modern')">Modern</button>
                            <button type="button" class="ns-btn @if(($styleMeta['theme'] ?? '') === 'compact') ns-btn--active @endif" wire:click="applyTheme('compact')">Kompak</button>
                            <button type="button" class="ns-btn @if(($styleMeta['theme'] ?? '') === 'code') ns-btn--active @endif" wire:click="applyTheme('code')">Teknis</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Warna Kertas</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('pageColor', '#ffffff')">Putih</button>
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('pageColor', '#fdfbf7')">Gading</button>
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('pageColor', '#fcf6e9')">Sepia</button>
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('pageColor', '#f8fafc')">Abu Lembut</button>
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('pageBorder', {{ !($pageSetup['pageBorder'] ?? false) ? 'true' : 'false' }})">
                                Bingkai: {{ !empty($pageSetup['pageBorder']) ? 'Aktif' : 'Nonaktif' }}
                            </button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Tanda Air (Watermark)</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('watermark', 'DRAF')">DRAF</button>
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('watermark', 'RAHASIA')">RAHASIA</button>
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('watermark', 'CONFIDENTIAL')">CONFIDENTIAL</button>
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('watermark', '')">Hapus Tanda Air</button>
                        </div>
                    </div>

                {{-- TAB: TATA LETAK (LAYOUT) --}}
                @elseif ($activeTab === 'layout')
                    <div class="ns-group">
                        <span class="ns-group__label">Margin</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="setMarginsPreset('normal')">Normal (2.5 cm)</button>
                            <button type="button" class="ns-btn" wire:click="setMarginsPreset('narrow')">Sempit (1.2 cm)</button>
                            <button type="button" class="ns-btn" wire:click="setMarginsPreset('wide')">Lebar (3.8 cm)</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('pagesetup')">Kustom…</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Orientasi &amp; Ukuran</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn @if(($pageSetup['orientation'] ?? 'portrait') === 'portrait') ns-btn--active @endif" wire:click="setOrientation('portrait')">Potret</button>
                            <button type="button" class="ns-btn @if(($pageSetup['orientation'] ?? '') === 'landscape') ns-btn--active @endif" wire:click="setOrientation('landscape')">Lanskap</button>
                            <select class="ns-btn" style="padding:.2rem .35rem;" wire:change="setPaperSize($event.target.value)">
                                <option value="A4" @selected(($pageSetup['size'] ?? '') === 'A4')>A4 (210×297 mm)</option>
                                <option value="Letter" @selected(($pageSetup['size'] ?? '') === 'Letter')>Letter (8.5×11 in)</option>
                                <option value="Legal" @selected(($pageSetup['size'] ?? '') === 'Legal')>Legal (8.5×14 in)</option>
                                <option value="B5" @selected(($pageSetup['size'] ?? '') === 'B5')>B5 (Academic Book)</option>
                                <option value="A5" @selected(($pageSetup['size'] ?? '') === 'A5')>A5</option>
                            </select>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Kolom &amp; Baris</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn @if(($pageSetup['columns'] ?? 1) == 1) ns-btn--active @endif" wire:click="updatePageSetup('columns', 1)">1 Kolom</button>
                            <button type="button" class="ns-btn @if(($pageSetup['columns'] ?? 1) == 2) ns-btn--active @endif" wire:click="updatePageSetup('columns', 2)">2 Kolom</button>
                            <button type="button" class="ns-btn" wire:click="updatePageSetup('lineNumbers', {{ !($pageSetup['lineNumbers'] ?? false) ? 'true' : 'false' }})">
                                Nomor Baris: {{ !empty($pageSetup['lineNumbers']) ? 'Aktif' : 'Nonaktif' }}
                            </button>
                        </div>
                    </div>

                {{-- TAB: REFERENSI (REFERENCES) --}}
                @elseif ($activeTab === 'references')
                    <div class="ns-group">
                        <span class="ns-group__label">Daftar Isi &amp; Catatan Kaki</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="insertTableOfContents">&#9776; Buat/Perbarui Daftar Isi</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('footnote')">&#128278; Sisipkan Catatan Kaki</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Bibliografi Proyek</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="insertBibliography">&#128218; Sisipkan Daftar Pustaka</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Asisten Riset</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="runTool('/sitasi')">&#128279; Sitasi</button>
                            <button type="button" class="ns-btn" wire:click="runTool('/cari-referensi')">&#128269; Cari Referensi</button>
                            <button type="button" class="ns-btn" wire:click="runTool('/matriks-literatur')">&#128202; Matriks Literatur</button>
                        </div>
                    </div>

                {{-- TAB: PENINJAUAN (REVIEW) --}}
                @elseif ($activeTab === 'review')
                    <div class="ns-group">
                        <span class="ns-group__label">Pemeriksaan Kata</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="runSpellCheck">&#10003; Cek Ejaan</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('thesaurus')">&#128214; Tesaurus</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('stats')">&#128202; Skor Keterbacaan</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Komentar</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="openDialog('comment')">+ Komentar</button>
                            <button type="button" class="ns-btn @if($showComments) ns-btn--active @endif" wire:click="$toggle('showComments')">&#128172; Panel Komentar ({{ count($comments) }})</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Lacak Perubahan (Track Changes)</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn @if($trackChanges) ns-btn--active @endif" wire:click="$toggle('trackChanges')">
                                &#128065; Lacak: {{ $trackChanges ? 'AKTIF' : 'NONAKTIF' }}
                            </button>
                            <button type="button" class="ns-btn @if($showRevisions) ns-btn--active @endif" wire:click="$toggle('showRevisions')">Panel Revisi ({{ $pendingRevisionCount }})</button>
                            <button type="button" class="ns-btn" wire:click="acceptAllRevisions" title="Terima semua perubahan">&#10004; Terima Semua</button>
                            <button type="button" class="ns-btn" wire:click="rejectAllRevisions" title="Tolak semua perubahan">&#10008; Tolak Semua</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Audit Asisten</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="runTool('/cek-pedoman')">&#128269; Cek Pedoman</button>
                            <button type="button" class="ns-btn" wire:click="runTool('/cek-gap')">&#128300; Cek Gap Riset</button>
                            <button type="button" class="ns-btn" wire:click="runTool('/ringkas')">&#128221; Ringkas Naskah</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Model AI Aktif</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="openDialog('execute_model')" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #ffffff; font-weight: 700;" title="Eksekusi model AI saat ini ke naskah">⚡ Eksekusi Model</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('polish')" title="Poles ragam bahasa akademik pada halaman saat ini">✨ Poles Cepat</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('expand')" title="Kembangkan argumen ilmiah">📖 Kembangkan</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('novelty')" title="Formulasi kebaruan riset (novelty)">💡 Kebaruan</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('abstract')" title="Susun draf abstrak akademik">📝 Abstrak</button>
                            <button type="button" class="ns-btn" wire:click="executeModelPreset('conclusion')" title="Formulasi kesimpulan & saran">🏁 Kesimpulan</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Copilot</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="openAssistant" style="background: linear-gradient(135deg, #eef2ff 0%, #f3e8ff 100%); border: 1px solid #c7d2fe; color: #4338ca; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Buka Xishie Assistant (AI Copilot Penulisan Ilmiah)">&#10024; Copilot</button>
                        </div>
                    </div>

                {{-- TAB: TAMPILAN (VIEW) --}}
                @elseif ($activeTab === 'view')
                    <div class="ns-group">
                        <span class="ns-group__label">Mode Tampilan</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn @if($viewMode === 'print') ns-btn--active @endif" wire:click="setViewMode('print')">Tata Cetak</button>
                            <button type="button" class="ns-btn @if($viewMode === 'web') ns-btn--active @endif" wire:click="setViewMode('web')">Web Kontinu</button>
                            <button type="button" class="ns-btn @if($viewMode === 'read') ns-btn--active @endif" wire:click="setViewMode('read')">Mode Baca/Fokus</button>
                            <button type="button" class="ns-btn @if($viewMode === 'outline') ns-btn--active @endif" wire:click="setViewMode('outline')">Garis Besar</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Tampilkan/Sembunyikan</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn @if($showRuler) ns-btn--active @endif" wire:click="$toggle('showRuler')">&#128207; Penggaris</button>
                            <button type="button" class="ns-btn @if($showNavigation) ns-btn--active @endif" wire:click="$toggle('showNavigation')">&#9776; Navigasi</button>
                            <button type="button" class="ns-btn @if($showGridlines) ns-btn--active @endif" wire:click="$toggle('showGridlines')">&#8862; Kisi Kertas</button>
                            <button type="button" class="ns-btn @if($showComments) ns-btn--active @endif" wire:click="$toggle('showComments')">&#128172; Komentar</button>
                            <button type="button" class="ns-btn @if($showRevisions) ns-btn--active @endif" wire:click="$toggle('showRevisions')">&#128065; Revisi</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Pembesaran (Zoom)</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--icon" wire:click="zoomOut">-</button>
                            @foreach (['75', '100', '125', '150'] as $z)
                                <button type="button" class="ns-btn @if($zoom === $z) ns-btn--active @endif" wire:click="setZoom('{{ $z }}')">{{ $z }}%</button>
                            @endforeach
                            <button type="button" class="ns-btn ns-btn--icon" wire:click="zoomIn">+</button>
                        </div>
                    </div>

                {{-- TAB: SURAT MENYURAT (MAILINGS) --}}
                @elseif ($activeTab === 'mailings')
                    <div class="ns-group">
                        <span class="ns-group__label">Sumber Data CSV</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="openDialog('merge')">&#128196; Buka Data &amp; Template Mail Merge</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Pratinjau &amp; Gabung</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="previewMerge">&#128065; Pratinjau Merge</button>
                            <button type="button" class="ns-btn" wire:click="insertMerge" @disabled(empty($mergePreviews)) title="Terapkan rekaman merge ke naskah">&#9889; Terapkan ke Naskah</button>
                        </div>
                    </div>

                {{-- TAB: ALAT TABEL (TABLE) --}}
                @elseif ($activeTab === 'table')
                    <div class="ns-group">
                        <span class="ns-group__label">Sisipkan Tabel</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" @mousedown.prevent="insertTable(3, 3)" title="Sisipkan Tabel 3×3">&#8862; Tabel 3×3</button>
                            <button type="button" class="ns-btn" wire:click="openDialog('table')" title="Konfigurasi Ukuran Grid Tabel">&#9638; Ukuran Kustom</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Baris &amp; Kolom</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" @mousedown.prevent="insertTableRow(false)" wire:click="tableInsertRow({{ $activeBlock }}, 0)" title="Sisipkan Baris di Atas">+ Baris Atas</button>
                            <button type="button" class="ns-btn" @mousedown.prevent="insertTableRow(true)" wire:click="tableInsertRow({{ $activeBlock }}, 1)" title="Sisipkan Baris di Bawah">+ Baris Bawah</button>
                            <button type="button" class="ns-btn" @mousedown.prevent="deleteTableRow()" wire:click="tableDeleteRow({{ $activeBlock }}, 0)" title="Hapus Baris Terpilih">- Hapus Baris</button>
                            <button type="button" class="ns-btn" @mousedown.prevent="insertTableColumn(false)" wire:click="tableInsertColumn({{ $activeBlock }}, 0)" title="Sisipkan Kolom di Kiri">+ Kolom Kiri</button>
                            <button type="button" class="ns-btn" @mousedown.prevent="insertTableColumn(true)" wire:click="tableInsertColumn({{ $activeBlock }}, 1)" title="Sisipkan Kolom di Kanan">+ Kolom Kanan</button>
                            <button type="button" class="ns-btn" @mousedown.prevent="deleteTableColumn()" wire:click="tableDeleteColumn({{ $activeBlock }}, 0)" title="Hapus Kolom Terpilih">- Hapus Kolom</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Kalkulasi Tabel</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="tableSumColumn({{ $activeBlock }}, 0)" title="Jumlahkan Angka Kolom (LibreOffice Table Formula)">&#8721; Jumlahkan Kolom</button>
                            <button type="button" class="ns-btn" wire:click="tableAverageColumn({{ $activeBlock }}, 0)" title="Hitung Rata-rata Kolom">x̄ Rata-rata</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Struktur Tabel</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-btn" wire:click="tableToggleHeader({{ $activeBlock }})">Toggle Baris Header</button>
                            <button type="button" class="ns-btn" style="color:#b91c1c;" @mousedown.prevent="deleteTable()" wire:click="removeBlock({{ $activeBlock }})" title="Hapus Seluruh Tabel">&#128465; Hapus Tabel</button>
                        </div>
                    </div>
                @endif
            </div>
        </nav>

        {{-- ================= SUMBER DOKUMEN & INTEGRASI PROYEK ================= --}}
        <div class="ns-source" style="border:1px solid var(--rb-border-color, #cbd5e1); background:var(--rb-surface, #ffffff); border-radius:4px; padding:.65rem .85rem; box-shadow:0 1px 2px rgba(15,23,42,.04);">
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:.75rem;">
                <div>
                    <h4 style="font-family:var(--rb-font-mono); font-size:.62rem; font-weight:700; text-transform:uppercase; color:#1e40af; margin:0 0 .35rem;">&#128196; Dari Dokumen Lokal</h4>
                    <input type="file" wire:model="upload" accept=".docx,.pdf,.txt,.md,.markdown" class="ns-btn" style="width:100%; font-size:.65rem;" />
                    <div wire:loading wire:target="upload" style="font-family:var(--rb-font-mono); font-size:.6rem; color:var(--rb-ink-muted); margin-top:.2rem;">Mengekstrak teks dokumen…</div>
                </div>

                <div>
                    <h4 style="font-family:var(--rb-font-mono); font-size:.62rem; font-weight:700; text-transform:uppercase; color:#1e40af; margin:0 0 .35rem;">&#128218; Dari Proyek Xishie</h4>
                    <div style="display:flex; gap:.3rem; flex-wrap:wrap;">
                        <select class="ns-btn" style="flex:1; min-width:140px; font-size:.65rem;" wire:model="sourceProjectId">
                            <option value="">— Pilih proyek —</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->title }}</option>
                            @endforeach
                        </select>
                        <select class="ns-btn" style="width:auto; font-size:.65rem;" wire:model="sourceProjectStage">
                            @foreach ($stageOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="ns-btn ns-btn--primary" style="font-size:.62rem;" wire:click="loadFromProject" wire:loading.attr="disabled" wire:target="loadFromProject">Muat Naskah</button>
                    </div>
                </div>

                @if ($documents->isNotEmpty())
                    <div>
                        <h4 style="font-family:var(--rb-font-mono); font-size:.62rem; font-weight:700; text-transform:uppercase; color:#1e40af; margin:0 0 .35rem;">&#128193; Naskah Tersimpan</h4>
                        <div style="display:flex; gap:.3rem; flex-wrap:wrap; max-height:45px; overflow-y:auto;">
                            @foreach ($documents as $doc)
                                <button type="button" class="ns-btn" style="font-size:.62rem; padding:.2rem .4rem;" wire:click="openDocument({{ $doc->id }})" title="{{ $doc->title }} ({{ $doc->word_count }} kata)">
                                    {{ Str::limit($doc->title, 24) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ================= PENGGARIS HORISONTAL (RULER) ================= --}}
        @if ($showRuler)
            <div class="ns-ruler">
                <div class="ns-ruler__margin-left"></div>
                <div class="ns-ruler__track">
                    <span class="ns-ruler__marker" style="left:25px;" title="Indentasi Kiri"></span>
                    @for ($cm = 1; $cm <= 16; $cm++)
                        <div class="ns-ruler__unit"><span>{{ $cm }}</span></div>
                    @endfor
                    <span class="ns-ruler__marker" style="right:25px;" title="Indentasi Kanan"></span>
                </div>
                <div class="ns-ruler__margin-right"></div>
            </div>
        @endif

        {{-- ================= WORKSPACE ================= --}}
        <div class="ns-workspace" style="@if(!$showNavigation) grid-template-columns: minmax(0,1fr) @if($showComments || $showRevisions || $assistantOpen) auto @endif; @endif">

            {{-- 1. LEFT SIDEBAR: NAVIGATOR --}}
            @if ($showNavigation)
                <aside class="ns-nav" aria-label="Navigator Dokumen">
                    <div class="ns-nav__header">
                        <div class="ns-nav__tab ns-nav__tab--active">&#9776; Judul ({{ $elements['headings'] ?? 0 }})</div>
                    </div>
                    <div class="ns-nav__body">
                        @forelse ($outline as $node)
                            <button type="button" class="ns-nav__item @if($node['level'] === 2) ns-nav__item--l2 @elseif($node['level'] >= 3) ns-nav__item--l3 @endif"
                                    x-on:click="(document.getElementById('heading-{{ $node['index'] }}') || document.getElementById('word-block-{{ $node['index'] }}'))?.scrollIntoView({behavior:'smooth', block:'center'})">
                                <span>{{ $node['text'] }}</span>
                                <span style="font-size:.55rem; color:var(--rb-ink-muted);">H{{ $node['level'] }}</span>
                            </button>
                        @empty
                            <div style="padding:.5rem; font-family:var(--rb-font-mono); font-size:.62rem; color:var(--rb-ink-muted);">
                                Belum ada judul. Sisipkan blok Heading untuk membuat navigasi.
                            </div>
                        @endforelse

                        <div style="border-top:1px solid #e2e8f0; margin-top:.5rem; padding-top:.4rem; font-family:var(--rb-font-mono); font-size:.58rem; color:var(--rb-ink-muted);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.25rem;">
                                <strong style="color:#1e40af;">📁 Versi / Snapshot ({{ count($snapshots) }})</strong>
                                <button type="button" class="ns-block__btn" wire:click="openDialog('snapshots')">+ Versi</button>
                            </div>
                            @foreach (array_slice($snapshots, -3) as $sIdx => $snap)
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.2rem;">
                                    <span>{{ Str::limit($snap['label'], 16) }}</span>
                                    <button type="button" class="ns-block__btn" style="padding:0 .25rem;" wire:click="restoreSnapshot({{ $sIdx }})" title="Pulihkan versi ini">&#8630;</button>
                                </div>
                            @endforeach
                            <div style="margin-top:.4rem;">&#8862; Tabel: {{ $elements['tables'] ?? 0 }}</div>
                            <div>&#128444; Gambar: {{ $elements['images'] ?? 0 }}</div>
                            <div>&#128278; Catatan Kaki: {{ $elements['footnotes'] ?? 0 }}</div>
                            <div>&#128172; Komentar: {{ $elements['comments'] ?? 0 }}</div>
                            <div>&#128065; Revisi: {{ $elements['revisions'] ?? 0 }}</div>
                        </div>
                    </div>
                </aside>
            @endif

            {{-- 2. CENTER: KANVAS HALAMAN (Microsoft Word & LibreOffice Multi-Page Sheets) --}}
            <main class="ns-canvas @if($viewMode === 'web') ns-canvas--web @elseif($viewMode === 'read') ns-canvas--read @endif @if($showGridlines) ns-canvas--gridlines @endif"
                  @contextmenu="openContextMenu($event)">
                <div id="word-manuscript-editor"
                     class="word-manuscript-container"
                     wire:key="manuscript-canvas-{{ $pageCount }}"
                     style="width: 100%; display: flex; flex-direction: column; align-items: center;">

                    {{-- Review Hasil Generasi Model Bar --}}
                    @if ($isReviewingModelOutput)
                        <div class="ns-model-review-banner" style="width: 100%; max-width: 820px; margin: 0 auto 14px auto; background: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%); border: 2px solid #6366f1; border-radius: 8px; box-shadow: 0 4px 14px rgba(99,102,241,0.2); padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; position: sticky; top: 10px; z-index: 50;">
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">
                                <div style="width: 32px; height: 32px; border-radius: 8px; background: #4f46e5; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
                                    ✨
                                </div>
                                <div style="min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <strong style="font-size: .78rem; color: #1e1b4b;">Tinjau Hasil Generasi: {{ $currentAssistantModel }}</strong>
                                        <span style="font-size: .6rem; background: #e0e7ff; color: #4338ca; padding: 1px 5px; border-radius: 3px; font-weight: 700;">Versi #{{ $modelGenerationCount }}</span>
                                        <span style="font-size: .65rem; color: #6366f1; font-weight: 600;">({{ count($pendingReviewBlockIndices) }} blok baru)</span>
                                    </div>
                                    <div style="font-size: .65rem; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 480px; margin-top: 2px;">
                                        Instruksi: &ldquo;{{ $lastExecutedModelPrompt }}&rdquo;
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                <button type="button" class="ns-btn ns-btn--primary" style="background: #16a34a; color: #ffffff; border-color: #15803d; font-weight: 700; font-size: .65rem; padding: .25rem .55rem;" wire:click="acceptModelOutput" title="Terima hasil ini dan simpan ke naskah">
                                    ✓ Terima &amp; Terapkan
                                </button>
                                <button type="button" class="ns-btn" style="background: #ffffff; color: #4338ca; border-color: #a5b4fc; font-weight: 700; font-size: .65rem; padding: .25rem .55rem;" wire:click="regenerateCurrentModel" wire:loading.attr="disabled" wire:target="regenerateCurrentModel" title="Generate ulang draf alternatif jika belum sesuai">
                                    🔄 Generate Ulang
                                </button>
                                <button type="button" class="ns-btn" style="background: #ffffff; color: #dc2626; border-color: #fca5a5; font-size: .65rem; padding: .25rem .5rem;" wire:click="discardModelOutput" title="Tolak hasil generasi ini">
                                    ✕ Tolak
                                </button>
                            </div>
                        </div>
                    @endif

                    @foreach ($paginatedPages as $pageData)
                        @php
                            $pageNumber = $pageData['pageNumber'];
                            $isFirstPage = $pageData['isFirstPage'];
                            $pageBlocks = $pageData['blocks'];
                            $inBulletList = false;
                            $inNumberedList = false;
                            $inBibliographySection = false;
                        @endphp

                        <div class="ns-page ns-page--{{ $fontFamily }} @if($viewMode === 'web') ns-page--web @elseif($viewMode === 'read') ns-page--read @endif @if(!empty($pageSetup['pageBorder'])) ns-page--border @endif @if(($pageSetup['orientation'] ?? 'portrait') === 'landscape') ns-page--landscape @endif"
                             id="word-page-{{ $pageNumber }}"
                             data-page-number="{{ $pageNumber }}"
                             style="font-size:{{ $fontSize }}px;
                                    transform-origin: top center;
                                    zoom: {{ (int) $zoom / 100 }};
                                    padding: {{ max(10, (int)($pageSetup['marginTop'] ?? 25)) }}mm {{ max(10, (int)($pageSetup['marginRight'] ?? 25)) }}mm {{ max(10, (int)($pageSetup['marginBottom'] ?? 25)) }}mm {{ max(10, (int)($pageSetup['marginLeft'] ?? 25)) }}mm;
                                    background: {{ $pageSetup['pageColor'] ?? '#ffffff' }};
                                    margin-bottom: 24px;
                                    @if(($pageSetup['columns'] ?? 1) > 1) column-count: {{ (int)$pageSetup['columns'] }}; column-gap: 2rem; @endif"
                             wire:key="ns-page-{{ $documentId ?? 'new' }}-p{{ $pageNumber }}-v{{ $editorRevision }}">

                            {{-- Watermark --}}
                            @if (!empty($pageSetup['watermark']))
                                <div class="ns-watermark">{{ $pageSetup['watermark'] }}</div>
                            @endif

                            {{-- Header Halaman (Sesuai Konsep Microsoft Word & Bebas Tabrakan) --}}
                            <div class="ns-sheet-header" contenteditable="false" style="display:flex; justify-content:space-between; align-items:center; min-height:28px; font-size:11px; color:#64748b; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-bottom:22px; user-select:none; position:relative; z-index:15;">
                                <!-- Sisi Kiri: Judul Dokumen -->
                                <div style="display:flex; align-items:center; gap:8px; min-width:0; flex:1;">
                                    <span style="font-weight:600; color:#475569; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:280px;" title="{{ $docTitle }}">
                                        {{ $docTitle }}
                                    </span>
                                </div>

                                <!-- Sisi Kanan: Aksi Halaman & Nomor Halaman -->
                                <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;" x-data="{ pageMenuOpen: false }" @click.outside="pageMenuOpen = false">
                                    <!-- Tombol Eksekusi Model Halaman -->
                                    <button type="button"
                                        wire:click="executeModelToPage({{ $pageNumber }})"
                                        class="ns-btn"
                                        style="font-size:10.5px; padding:2px 8px; height:22px; background:linear-gradient(135deg, rgba(99,102,241,0.12) 0%, rgba(168,85,247,0.12) 100%); color:#4f46e5; border:1px solid #c7d2fe; border-radius:4px; font-weight:700; display:inline-flex; align-items:center; gap:4px;"
                                        title="Eksekusi model AI saat ini ke Halaman {{ $pageNumber }}">
                                        <span>⚡</span> Model Hal. {{ $pageNumber }}
                                    </button>

                                    <!-- Dropdown Opsi & Layout Halaman -->
                                    <div style="position:relative;">
                                        <button type="button"
                                            @click="pageMenuOpen = !pageMenuOpen"
                                            class="ns-btn"
                                            style="font-size:10.5px; padding:2px 8px; height:22px; background:#f8fafc; color:#334155; border:1px solid #cbd5e1; border-radius:4px; font-weight:600; display:inline-flex; align-items:center; gap:4px;"
                                            title="Opsi tata letak dan paragraf Halaman {{ $pageNumber }}">
                                            <span>⚙️</span> Opsi Hal. {{ $pageNumber }} <span style="font-size:8px;">▼</span>
                                        </button>

                                        <!-- Floating Dropdown Menu -->
                                        <div x-show="pageMenuOpen"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 scale-100"
                                             x-transition:leave-end="opacity-0 scale-95"
                                             style="position:absolute; right:0; top:26px; z-index:100; min-width:220px; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.1); padding:5px; display:flex; flex-direction:column; gap:2px;"
                                             contenteditable="false">
                                            <div style="font-size:9.5px; font-weight:700; color:#64748b; padding:4px 8px; border-bottom:1px solid #f1f5f9; text-transform:uppercase; letter-spacing:0.5px;">
                                                Alat Halaman {{ $pageNumber }}
                                            </div>
                                            <button type="button"
                                                wire:click="openParagraphCustomizer({{ $pageNumber }}, 0); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>⚙️</span> Kustomisasi Paragraf
                                            </button>
                                            <button type="button"
                                                wire:click="insertCustomParagraphOnPage({{ $pageNumber }}, 'Paragraf baru pada halaman {{ $pageNumber }}.'); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>➕</span> Tambah Paragraf Baru
                                            </button>
                                            <div style="height:1px; background:#f1f5f9; margin:2px 0;"></div>
                                            <button type="button"
                                                wire:click="applyParagraphPresetToPage({{ $pageNumber }}, 'academic_standard'); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>⚡</span> Format APA 1.5 (Scopus)
                                            </button>
                                            <button type="button"
                                                wire:click="applyParagraphPresetToPage({{ $pageNumber }}, 'journal_ieee'); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>⚡</span> Format IEEE / ACM (1.0)
                                            </button>
                                            <button type="button"
                                                wire:click="cleanPageParagraphSpaces({{ $pageNumber }}); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>🧹</span> Rapikan Spasi Berlebih
                                            </button>
                                            <div style="height:1px; background:#f1f5f9; margin:2px 0;"></div>
                                            <button type="button"
                                                wire:click="setPageParagraphColumns({{ $pageNumber }}, {{ ($pageOverrides[$pageNumber]['columns'] ?? 1) === 1 ? 2 : 1 }}); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>📰</span> {{ ($pageOverrides[$pageNumber]['columns'] ?? 1) === 2 ? 'Ubah ke 1 Kolom' : 'Ubah ke 2 Kolom' }}
                                            </button>
                                            <button type="button"
                                                wire:click="togglePageParagraphLineNumbers({{ $pageNumber }}); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>🔢</span> {{ !empty($pageOverrides[$pageNumber]['lineNumbers']) ? 'Sembunyikan No. Baris' : 'Tampilkan No. Baris' }}
                                            </button>
                                            <button type="button"
                                                wire:click="setPageParagraphAlignment({{ $pageNumber }}, 'justify'); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>≡</span> Rata Penuh (Justify)
                                            </button>
                                            <button type="button"
                                                wire:click="setPageParagraphAlignment({{ $pageNumber }}, 'left'); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>=</span> Rata Kiri (Left)
                                            </button>
                                            <button type="button"
                                                wire:click="setPageParagraphLineSpacing({{ $pageNumber }}, 1.5); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>↕</span> Atur Spasi 1.5
                                            </button>
                                            <button type="button"
                                                wire:click="setPageParagraphLineSpacing({{ $pageNumber }}, 1.0); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>↕</span> Atur Spasi 1.0
                                            </button>
                                            <button type="button"
                                                wire:click="batchIndentPageParagraphs({{ $pageNumber }}, 'first-line'); pageMenuOpen = false;"
                                                class="ns-btn"
                                                style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px; width:100%;">
                                                <span>📐</span> Indent Baris Pertama (1.27cm)
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Label Halaman -->
                                    <span style="font-size:10px; color:#64748b; font-weight:600; white-space:nowrap; padding-left:4px;">
                                        Halaman {{ $pageNumber }} dari {{ $pageCount }}
                                    </span>
                                </div>
                            </div>

                            {{-- Kanvas Lembar Naskah WYSIWYG (Microsoft Word 365 & LibreOffice Writer) --}}
                            @php
                                $pageCols = (int) ($pageOverrides[$pageNumber]['columns'] ?? $pageSetup['columns'] ?? 1);
                                $colStyle = $pageCols > 1 ? "column-count: {$pageCols}; column-gap: 1.5rem;" : '';
                                $pageLineSpacing = (float) ($pageOverrides[$pageNumber]['lineSpacing'] ?? $pageSetup['lineSpacing'] ?? 1.5);
                            @endphp
                            <div class="word-sheet-content"
                                 id="word-page-content-{{ $pageNumber }}"
                                 data-page-number="{{ $pageNumber }}"
                                 wire:key="filament-content-{{ $documentId ?? 'new' }}-p{{ $pageNumber }}-v{{ $editorRevision }}"
                                 wire:ignore
                                 style="outline: none; min-height: 720px; line-height: {{ $pageLineSpacing }}; {{ $colStyle }}"
                                 contenteditable="true"
                                 spellcheck="false"
                                 @keydown="handleContentKeydown($event)"
                                 @keydown.ctrl.enter.prevent="insertPageBreak()"
                                 @keydown.meta.enter.prevent="insertPageBreak()"
                                 @paste="handlePaste($event)"
                                 @dragover.prevent
                                 @drop="handleDrop($event)"
                                 @click="updateActiveBlock($event)"
                                 @focusin="updateActiveBlock($event)"
                                 @keyup="updateActiveBlock($event)"
                                 @input="handleContentInput($event)"
                                 @blur="syncAllPages()">

                                @forelse ($pageBlocks as $item)
                                    @php
                                        $index = $item['index'];
                                        $block = $item['block'];
                                        $type = (string) ($block['type'] ?? 'paragraph');
                                        $level = (int) ($block['level'] ?? 1);
                                        $headingId = 'heading-' . $index;
                                        $text = (string) ($block['text'] ?? '');
                                        $align = (string) ($block['align'] ?? 'left');
                                    @endphp

                                    @if ($type === 'bullet')
                                        @if (!$inBulletList)
                                            <ul class="word-bullet-list">
                                            @php $inBulletList = true; @endphp
                                        @endif
                                        <li id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="text-align: {{ $align }};">{!! $text !!}</li>
                                    @else
                                        @if ($inBulletList)
                                            </ul>
                                            @php $inBulletList = false; @endphp
                                        @endif

                                        @if ($type === 'numbered')
                                            @if (!$inNumberedList)
                                                <ol class="word-numbered-list">
                                                @php $inNumberedList = true; @endphp
                                            @endif
                                            <li id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="text-align: {{ $align }};">{!! $text !!}</li>
                                        @else
                                            @if ($inNumberedList)
                                                </ol>
                                                @php $inNumberedList = false; @endphp
                                            @endif

                                            @if ($type === 'title')
                                                <h1 class="word-title-text" id="doc-title" data-block-index="{{ $index }}" style="text-align: {{ $align }};">{!! $text ?: 'Judul Naskah' !!}</h1>
                                            @elseif ($type === 'subtitle')
                                                <p class="word-subtitle-text" id="doc-subtitle" data-block-index="{{ $index }}" style="text-align: {{ $align }};">{!! $text !!}</p>
                                            @elseif ($type === 'heading')
                                                @php
                                                    if (stripos($text, 'daftar pustaka') !== false || stripos($text, 'referensi') !== false || stripos($text, 'bibliography') !== false) {
                                                        $inBibliographySection = true;
                                                    } else {
                                                        $inBibliographySection = false;
                                                    }
                                                @endphp
                                                @if ($level === 1)
                                                    <h2 class="word-heading-1" id="{{ $headingId }}" data-block-index="{{ $index }}" style="text-align: {{ $align }};">{!! $text !!}</h2>
                                                @elseif ($level === 2)
                                                    <h3 class="word-heading-2" id="{{ $headingId }}" data-block-index="{{ $index }}" style="text-align: {{ $align }};">{!! $text !!}</h3>
                                                @else
                                                    <h4 class="word-heading-3" id="{{ $headingId }}" data-block-index="{{ $index }}" style="text-align: {{ $align }};">{!! $text !!}</h4>
                                                @endif
                                            @elseif ($type === 'quote')
                                                <blockquote class="word-quote-block" id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="text-align: {{ $align }};">
                                                    <p class="word-quote-text">{!! $text !!}</p>
                                                </blockquote>
                                            @elseif ($type === 'toc')
                                                <div class="ns-toc-block word-toc-block" id="word-block-{{ $index }}" data-block-index="{{ $index }}" contenteditable="false" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:4px; padding:.75rem 1rem; margin:.75rem 0;">
                                                    <div style="font-weight:700; font-size:1.05rem; color:#1e40af; margin-bottom:.6rem; display:flex; align-items:center; justify-content:space-between;">
                                                        <span>📑 Daftar Isi Otomatis</span>
                                                        <button type="button" class="ns-block__btn" wire:click.stop="insertTableOfContents" title="Perbarui Daftar Isi">🔄 Perbarui</button>
                                                    </div>
                                                    <div style="display:flex; flex-direction:column; gap:.35rem;">
                                                        @php $tocRows = $block['table']['rows'] ?? []; @endphp
                                                        @foreach ($tocRows as $tIdx => $tRow)
                                                            @if ($tIdx > 0)
                                                                <div style="display:flex; align-items:baseline; font-size:.85rem; color:#334155;">
                                                                    <span style="white-space:nowrap;">{{ $tRow[0]['text'] ?? '' }}</span>
                                                                    <span style="flex:1; border-bottom:1px dotted #94a3b8; margin:0 .5rem; transform:translateY(-2px);"></span>
                                                                    <span style="font-size:.78rem; color:#64748b;">{{ $tRow[1]['text'] === '…' ? (1 + floor($tIdx / 4)) : ($tRow[1]['text'] ?? '1') }}</span>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @elseif ($type === 'table')
                                                <div class="word-table-wrapper" contenteditable="true" style="margin:12px 0; position:relative;">
                                                    <div contenteditable="false" style="position:absolute; right:6px; top:-10px; z-index:10; display:flex; gap:4px;">
                                                        <button type="button" class="ns-btn" style="padding:1px 6px; font-size:10px; height:20px; color:#4f46e5; font-weight:700; background:#eef2ff; border:1px solid #c7d2fe;" wire:click="executeModelOnTable({{ $index }})" title="Eksekusi model AI saat ini untuk menyintesis data tabel">⚡ Model Tabel</button>
                                                    </div>
                                                    <table class="word-table ns-table" style="width:100%; border-collapse:collapse; margin:4px 0;" data-block-index="{{ $index }}">
                                                        <tbody contenteditable="true">
                                                            @php
                                                                $rows = (array) ($block['table']['rows'] ?? []);
                                                                $hasHeader = (bool) ($block['table']['header'] ?? true);
                                                            @endphp
                                                            @foreach ($rows as $r => $row)
                                                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                                                    @foreach ((array) $row as $c => $cell)
                                                                        @php $cellTag = ($hasHeader && $r === 0) ? 'th' : 'td'; @endphp
                                                                        <{{ $cellTag }} style="padding:6px 10px; border-right:1px solid #e2e8f0; text-align:left; @if($hasHeader && $r === 0) background:#f8fafc; font-weight:700; @endif">
                                                                            {!! (string) ($cell['text'] ?? '') ?: '<br>' !!}
                                                                        </{{ $cellTag }}>
                                                                    @endforeach
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @elseif ($type === 'image')
                                                <figure class="word-image-figure" id="word-block-{{ $index }}" data-block-index="{{ $index }}" contenteditable="false" style="text-align:{{ $align }}; margin:16px 0;">
                                                    @if (!empty($block['image']['src']))
                                                        <img src="{{ $block['image']['src'] }}" alt="{{ $block['image']['alt'] ?? '' }}" style="max-width:100%; width:{{ $block['image']['width'] ?? 320 }}px; display:inline-block; border-radius:4px; box-shadow:0 1px 4px rgba(0,0,0,0.08);" />
                                                        @if (!empty($block['image']['alt']))
                                                            <figcaption style="font-size:.78rem; color:#64748b; font-style:italic; margin-top:.4rem;">{{ $block['image']['alt'] }}</figcaption>
                                                        @endif
                                                    @endif
                                                </figure>
                                            @elseif ($type === 'equation')
                                                @php
                                                    $latex = (string) ($block['equation']['latex'] ?? $block['text'] ?? 'E = mc^2');
                                                    $caption = (string) ($block['equation']['caption'] ?? '');
                                                @endphp
                                                <div class="word-equation-block latex-math-render"
                                                     id="word-block-{{ $index }}"
                                                     data-block-index="{{ $index }}"
                                                     data-latex="{{ $latex }}"
                                                     data-caption="{{ $caption }}"
                                                     contenteditable="false"
                                                     wire:click="openDialog('equation')"
                                                     title="Klik untuk menyunting rumus LaTeX"
                                                     style="cursor:pointer; padding:12px; margin:12px 0; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; text-align:center; position:relative;">
                                                    <button type="button" class="ns-btn" style="position:absolute; right:8px; top:8px; font-size:10px; height:20px; padding:0 6px; color:#4f46e5; font-weight:700; background:#ffffff; border:1px solid #cbd5e1;" wire:click.stop="executeModelOnEquation({{ $index }})" title="Eksekusi model AI saat ini untuk menjabarkan rumus ini">⚡ Jabarkan Model</button>
                                                    <span class="latex-math-formula">${{ $latex }}$</span>
                                                    @if ($caption !== '')
                                                        <div style="font-size:.75rem; color:#64748b; text-align:right; margin-top:.3rem;">{{ $caption }}</div>
                                                    @endif
                                                </div>
                                            @elseif ($type === 'callout')
                                                @php $cType = (string) ($block['calloutType'] ?? 'info'); @endphp
                                                <div class="ns-callout ns-callout--{{ $cType }}" id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="margin:12px 0; padding:10px 14px; border-left:4px solid #3b82f6; background:#eff6ff; border-radius:4px;">
                                                    <p class="word-paragraph-text" style="margin:0;">{!! $text ?: '<br>' !!}</p>
                                                </div>
                                            @else
                                                @if ($inBibliographySection)
                                                    <p class="word-bib-entry" id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="{{ $this->blockInlineStyle($block) }}">
                                                        {!! $text ?: '<br>' !!}
                                                    </p>
                                                @else
                                                    @php
                                                        $pIdxOnPage = 0;
                                                        foreach ($this->getPageParagraphs($pageNumber) as $pItem) {
                                                            if ($pItem['globalIndex'] === $index) {
                                                                $pIdxOnPage = $pItem['pageIndex'];
                                                                break;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="word-para-item-wrapper" style="position:relative;" x-data="{ hovered: false }" @mouseenter="hovered = true" @mouseleave="hovered = false">
                                                        <p class="word-paragraph-text {{ !empty($block['dropCap']) ? 'word-drop-cap' : '' }}" id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="{{ $this->blockInlineStyle($block) }}">
                                                            {!! $text ?: '<br>' !!}
                                                        </p>
                                                        <div x-show="hovered" contenteditable="false" style="position:absolute; right:4px; top:-12px; z-index:20; display:flex; gap:3px; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:2px 4px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="openParagraphCustomizer({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Kustomisasi paragraf ini">⚙️</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="togglePageParagraphFirstLineIndent({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Indentasi Baris Pertama (1.27cm)">📐</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="highlightPageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Sorot Warna Kuning">🖍️</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="togglePageParagraphDropCap({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Huruf kapital awal membesar (Drop Cap)">🔤</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="movePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }}, -1)" title="Geser naik">⬆️</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="movePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }}, 1)" title="Geser turun">⬇️</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="duplicatePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Duplikat paragraf">📄</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="splitPageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Pecah paragraf">✂️</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="paraphrasePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Parafrase kalimat ragam akademik">🔄</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="polishPageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Poles bahasa akademik">✨</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px; color:#4f46e5; font-weight:bold;" wire:click="executeModelOnParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Eksekusi model AI saat ini pada paragraf ini">⚡</button>
                                                            <button type="button" class="ns-btn" style="padding:1px 5px; font-size:10px; height:18px; color:#d13438;" wire:click="deletePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Hapus paragraf">🗑️</button>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif
                                        @endif
                                    @endif
                                @empty
                                    <p class="word-paragraph-text" data-block-index="0" style="margin-bottom:.5rem;"><br></p>
                                @endforelse

                                @if ($inBulletList)
                                    </ul>
                                @endif
                                @if ($inNumberedList)
                                    </ol>
                                @endif
                            </div>

                            {{-- Footer Halaman (Sesuai Konsep Microsoft Word & LibreOffice Writer) --}}
                            <div class="ns-sheet-footer" contenteditable="false" style="display:flex; justify-content:center; align-items:center; font-size:10px; color:#94a3b8; border-top:1px dotted #cbd5e1; padding-top:8px; margin-top:24px; user-select:none;">
                                <span>&mdash; {{ $pageNumber }} &mdash;</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </main>

            {{-- 3. RIGHT SIDEBAR: XISHIE ASSISTANT, KOMENTAR & LACAK REVISI --}}
            @if ($assistantOpen || $showComments || $showRevisions)
                <aside class="ns-drawer" aria-label="Panel Samping Tugas dan Asisten">
                    {{-- 1. XISHIE ASSISTANT DOCKED TASK PANE --}}
                    @if ($assistantOpen)
                        <div class="ns-drawer__header" style="background: linear-gradient(135deg, #1e40af 0%, #312e81 100%); color:#ffffff;">
                            <div style="display:flex; align-items:center; gap:.4rem;">
                                <span>✨</span>
                                <span style="font-weight:700;">XISHIE ASSISTANT</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:.35rem;">
                                @if (!empty($assistantLog))
                                    <button type="button" class="ns-btn" style="font-size:.58rem; padding:.1rem .35rem; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); color:#fff;" wire:click="clearAssistantLog" title="Bersihkan riwayat chat">🗑️</button>
                                @endif
                                <button type="button" style="background:transparent; border:none; color:#ffffff; cursor:pointer; font-size:1.1rem; line-height:1;" wire:click="closeAssistant" title="Tutup asisten">&times;</button>
                            </div>
                        </div>

                        {{-- Sub-header Context Bar --}}
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:.35rem .6rem; background:#eff6ff; border-bottom:1px solid #dbeafe; font-size:.62rem; color:#1e40af;">
                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; font-weight:600;">
                                📄 {{ Str::limit($docTitle, 24) }}
                            </span>
                            <span style="background:#dbeafe; color:#1e40af; font-weight:700; padding:1px 6px; border-radius:999px;">
                                Blok #{{ $activeBlock + 1 }}
                            </span>
                        </div>

                        {{-- Model Selector Bar --}}
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:.3rem .6rem; background:#f1f5f9; border-bottom:1px solid #e2e8f0; font-size:.62rem; gap:.3rem;">
                            <div style="display:flex; align-items:center; gap:.25rem; flex:1; min-width:0;">
                                <span style="font-weight:700; color:#475569; font-size:.58rem; text-transform:uppercase;">Model:</span>
                                <select wire:model.live="currentAssistantModel" class="ns-btn" style="font-size:.6rem; padding:.1rem .25rem; flex:1; height:22px; background:#ffffff; border-color:#cbd5e1; font-weight:600;" title="Pilih model AI saat ini">
                                    @foreach ($this->getAvailableAssistantModels() as $availModel)
                                        <option value="{{ $availModel['id'] }}">
                                            {{ $availModel['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <span style="font-size:.55rem; color:#0f766e; background:#ccfbf1; font-weight:700; padding:1px 5px; border-radius:999px; white-space:nowrap;">● Aktif</span>
                        </div>

                        {{-- Quick Model Execution Box --}}
                        <div style="padding:.4rem .6rem; background:linear-gradient(135deg, #eef2ff 0%, #faf5ff 100%); border-bottom:1px solid #c7d2fe;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.25rem;">
                                <span style="font-weight:700; font-size:.62rem; color:#4338ca; display:inline-flex; align-items:center; gap:.2rem;">
                                    <span>⚡</span> Eksekusi Model Sekarang
                                </span>
                                <select wire:model="executeModelTarget" style="font-size:.55rem; height:18px; padding:0 .2rem; border-radius:3px; border:1px solid #a5b4fc; background:#ffffff; color:#312e81;">
                                    <option value="current_page">Halaman Ini</option>
                                    <option value="active_block">Blok Aktif</option>
                                    <option value="append">Akhir Naskah</option>
                                </select>
                            </div>
                            <div style="display:flex; gap:.25rem;">
                                <input type="text" wire:model="executeModelPrompt" wire:keydown.enter="submitExecuteModel" placeholder="Instruksi ke model sekarang..." class="ns-btn" style="flex:1; font-size:.62rem; padding:.15rem .35rem; background:#ffffff; height:24px; text-align:left;" />
                                <button type="button" class="ns-btn ns-btn--primary" style="font-size:.6rem; padding:.15rem .45rem; background:#4338ca; color:#ffffff; height:24px;" wire:click="submitExecuteModel" wire:loading.attr="disabled" wire:target="submitExecuteModel,executeCurrentModel" title="Eksekusi model sekarang dan terapkan langsung ke kanvas naskah">
                                    Eksekusi
                                </button>
                            </div>
                        </div>

                        {{-- Mode Review di Drawer Asisten --}}
                        @if ($isReviewingModelOutput)
                            <div style="padding:.4rem .6rem; background:#f0fdf4; border-bottom:1px solid #bbf7d0; font-size:.62rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.3rem;">
                                    <span style="font-weight:700; color:#15803d; display:inline-flex; align-items:center; gap:.25rem;">
                                        <span>🔍</span> Tinjauan Hasil Model
                                    </span>
                                    <span style="font-size:.55rem; background:#dcfce7; color:#166534; padding:1px 5px; border-radius:3px; font-weight:700;">
                                        Versi #{{ $modelGenerationCount }}
                                    </span>
                                </div>
                                <div style="font-size:.58rem; color:#475569; margin-bottom:.35rem; line-height:1.35;">
                                    Draf &ldquo;{{ Str::limit($lastExecutedModelPrompt, 35) }}&rdquo; sedang aktif ditinjau pada naskah.
                                </div>
                                <div style="display:flex; gap:.25rem;">
                                    <button type="button" class="ns-btn ns-btn--primary" style="flex:1; font-size:.58rem; padding:.15rem .3rem; background:#16a34a; border-color:#15803d;" wire:click="acceptModelOutput" title="Terima hasil ini">
                                        ✓ Terima
                                    </button>
                                    <button type="button" class="ns-btn" style="flex:1.2; font-size:.58rem; padding:.15rem .3rem; background:#ffffff; color:#4338ca; border-color:#c7d2fe;" wire:click="regenerateCurrentModel" wire:loading.attr="disabled" wire:target="regenerateCurrentModel" title="Generate ulang jika belum sesuai">
                                        🔄 Generate Ulang
                                    </button>
                                    <button type="button" class="ns-btn" style="font-size:.58rem; padding:.15rem .3rem; background:#ffffff; color:#dc2626; border-color:#fecaca;" wire:click="discardModelOutput" title="Tolak hasil ini">
                                        ✕ Tolak
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Quick Academic Slash Command Chips --}}
                        <div style="display:flex; flex-wrap:wrap; gap:.25rem; padding:.45rem .6rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; max-height:85px; overflow-y:auto;">
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/poles')" title="Poles tata bahasa akademik">✍️ /poles</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/parafrase')" title="Parafrase akademik kalimat aktif">🔄 /parafrase</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/cek-pedoman')" title="Audit kepatuhan pedoman &amp; kaidah ilmiah">🎯 /cek-pedoman</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/sitasi')" title="Cari rekomendasi sitasi valid">📚 /sitasi</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/matriks-literatur')" title="Bangun tabel matriks literatur (SOTA)">📊 /matriks-literatur</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/cek-gap')" title="Audit gap riset &amp; kebaruan">🔬 /cek-gap</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/kembangkan')" title="Elaborasi dan kembangkan poin">⚡ /kembangkan</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/rumus')" title="Rekomendasi formula LaTeX">π /rumus</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/abstrak')" title="Susun draf abstrak IMRAD">📜 /abstrak</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/metodologi')" title="Rancang struktur metodologi">📐 /metode</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/tabel-analisis')" title="Buat tabel komparasi analisis">📊 /tabel-analisis</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/puebi')" title="Periksa ejaan dan PUEBI baku">🔍 /puebi</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/terjemahkan')" title="Terjemahan akademik formal">🌐 /terjemahkan</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/pendahuluan')" title="Rancang Bab Pendahuluan">📖 /pendahuluan</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/pembahasan')" title="Sintesis Bab Pembahasan">💬 /pembahasan</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/kesimpulan')" title="Rumuskan Bab Kesimpulan">🏁 /kesimpulan</button>
                            <button type="button" class="ns-btn" style="font-size:.58rem; padding:.12rem .35rem; background:#ffffff; border-color:#cbd5e1;" wire:click="runTool('/tinjauan-pustaka')" title="Tinjauan Pustaka &amp; Teori">📚 /tinjauan-pustaka</button>
                        </div>

                        {{-- Assistant Body / Chat Stream --}}
                        <div class="ns-drawer__body" style="flex:1; overflow-y:auto; padding:.6rem; display:flex; flex-direction:column; gap:.5rem;"
                             x-init="$watch('$el.children.length', () => $nextTick(() => $el.scrollTop = $el.scrollHeight)); $nextTick(() => $el.scrollTop = $el.scrollHeight);">
                            @if (empty($assistantLog))
                                <div style="text-align:center; padding:1.2rem .5rem; display:flex; flex-direction:column; align-items:center; gap:.5rem;">
                                    <div style="width:42px; height:42px; border-radius:10px; background:linear-gradient(135deg, #eef2ff 0%, #ede9fe 100%); display:flex; align-items:center; justify-content:center; font-size:20px; box-shadow:0 2px 8px rgba(99,102,241,0.15);">
                                        ✨
                                    </div>
                                    <div style="font-weight:700; font-size:.85rem; color:#1e293b;">
                                        Xishie Assistant Siap Membantu
                                    </div>
                                    <p style="font-size:.65rem; color:#64748b; line-height:1.45; margin:0;">
                                        Pilih pintasan perintah di atas atau ketik pertanyaan langsung untuk memoles paragraf, memeriksa kepatuhan pedoman, sitasi, atau menyusun draf bab.
                                    </p>
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:.35rem; width:100%; margin-top:.3rem; text-align:left;">
                                        <div class="ns-tool-card" wire:click="runTool('/poles')" style="cursor:pointer; padding:.4rem .5rem;">
                                            <span style="font-size:.85rem;">🪄</span>
                                            <div>
                                                <div style="font-weight:700; font-size:.65rem; color:#4338ca;">Poles Teks</div>
                                                <div style="font-size:.55rem; color:#64748b;">Perbaiki gaya akademik</div>
                                            </div>
                                        </div>
                                        <div class="ns-tool-card" wire:click="runTool('/parafrase')" style="cursor:pointer; padding:.4rem .5rem;">
                                            <span style="font-size:.85rem;">🔄</span>
                                            <div>
                                                <div style="font-weight:700; font-size:.65rem; color:#4338ca;">Parafrase</div>
                                                <div style="font-size:.55rem; color:#64748b;">Bebas plagiasi</div>
                                            </div>
                                        </div>
                                        <div class="ns-tool-card" wire:click="runTool('/cek-pedoman')" style="cursor:pointer; padding:.4rem .5rem;">
                                            <span style="font-size:.85rem;">🎯</span>
                                            <div>
                                                <div style="font-weight:700; font-size:.65rem; color:#4338ca;">Cek Pedoman</div>
                                                <div style="font-size:.55rem; color:#64748b;">Audit kaidah naskah</div>
                                            </div>
                                        </div>
                                        <div class="ns-tool-card" wire:click="runTool('/sitasi')" style="cursor:pointer; padding:.4rem .5rem;">
                                            <span style="font-size:.85rem;">📚</span>
                                            <div>
                                                <div style="font-weight:700; font-size:.65rem; color:#4338ca;">Cari Sitasi</div>
                                                <div style="font-size:.55rem; color:#64748b;">Rujukan ilmiah valid</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                @foreach ($assistantLog as $logIndex => $msg)
                                    <div class="ns-msg ns-msg--{{ $msg['role'] === 'user' ? 'user' : 'assistant' }}" style="padding:.5rem .65rem; border-radius:6px; font-size:.7rem; @if($msg['role'] === 'user') background:#eff6ff; border:1px solid #bfdbfe; margin-left:1.5rem; @else background:#ffffff; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.04); margin-right:.5rem; @endif">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.25rem; font-size:.6rem; font-weight:700; color:{{ $msg['role'] === 'user' ? '#1d4ed8' : '#4338ca' }};">
                                            <div style="display:flex; align-items:center; gap:.25rem;">
                                                <span>{{ $msg['role'] === 'user' ? '👤 Anda' : '✨ Xishie Assistant' }}</span>
                                                @if(!empty($msg['model']))
                                                    <span style="font-size:.52rem; background:#ede9fe; color:#6d28d9; padding:0 4px; border-radius:3px; font-family:var(--rb-font-mono); font-weight:600;">{{ $msg['model'] }}</span>
                                                @endif
                                            </div>
                                            @if(!empty($msg['command']))
                                                <span style="background:#ede9fe; color:#5b21b6; padding:1px 5px; border-radius:4px; font-family:var(--rb-font-mono);">{{ $msg['command'] }}</span>
                                            @endif
                                        </div>
                                        <div style="line-height:1.55; color:#1e293b;" class="word-markdown-content">
                                            {!! \Illuminate\Support\Str::markdown($msg['content'] ?? '') !!}
                                        </div>
                                        @if (($msg['role'] ?? '') === 'assistant')
                                            @if ($isReviewingModelOutput && ($msg['applied'] ?? false))
                                                <div style="margin-top:.4rem; padding:.35rem .5rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; font-size:.62rem;">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.3rem;">
                                                        <span style="font-weight:700; color:#15803d; display:inline-flex; align-items:center; gap:.25rem;">
                                                            <span>✨</span> Diterapkan ke Kanvas Naskah
                                                        </span>
                                                        <span style="font-size:.55rem; background:#dcfce7; color:#166534; padding:1px 5px; border-radius:3px; font-weight:700;">
                                                            Versi #{{ $modelGenerationCount }} · Review Aktif
                                                        </span>
                                                    </div>
                                                    <div style="display:flex; gap:.25rem;">
                                                        <button type="button" class="ns-btn ns-btn--primary" style="flex:1; font-size:.58rem; padding:.15rem .35rem; background:#16a34a; border-color:#15803d; color:#ffffff;" wire:click="acceptModelOutput" title="Terima hasil ini dan terapkan permanen ke naskah">
                                                            ✓ Terima &amp; Simpan
                                                        </button>
                                                        <button type="button" class="ns-btn" style="flex:1.2; font-size:.58rem; padding:.15rem .35rem; background:#ffffff; color:#4338ca; border-color:#a5b4fc; font-weight:700;" wire:click="regenerateCurrentModel" wire:loading.attr="disabled" wire:target="regenerateCurrentModel" title="Generate ulang variasi baru jika belum sesuai">
                                                            🔄 Generate Ulang
                                                        </button>
                                                        <button type="button" class="ns-btn" style="font-size:.58rem; padding:.15rem .35rem; background:#ffffff; color:#dc2626; border-color:#fecaca;" wire:click="discardModelOutput" title="Tolak dan kembalikan naskah ke kondisi awal">
                                                            ✕ Tolak
                                                        </button>
                                                    </div>
                                                </div>
                                            @else
                                                <div style="display:flex; gap:.3rem; margin-top:.4rem; border-top:1px dashed #cbd5e1; padding-top:.35rem; flex-wrap:wrap; align-items:center;">
                                                    <button type="button" class="ns-btn ns-btn--primary" style="font-size:.62rem; padding:.2rem .45rem; font-weight:700; background:#2563eb; color:#ffffff;" wire:click="applyAssistantToCurrentPage({{ $logIndex }})" title="Eksekusi dan terapkan rekomendasi model ke halaman naskah saat ini">
                                                        ⚡ Terapkan ke Halaman Ini
                                                    </button>
                                                    <button type="button" class="ns-btn ns-btn--primary" style="font-size:.58rem; padding:.15rem .4rem;" wire:click="insertAssistantResponse({{ $logIndex }})" title="Sisipkan rekomendasi ini ke naskah">
                                                        ➕ Sisipkan ke Naskah
                                                    </button>
                                                    <button type="button" class="ns-btn" style="font-size:.58rem; padding:.15rem .4rem;" wire:click="replaceActiveBlockWithAssistant({{ $logIndex }})" title="Ganti blok aktif dengan rekomendasi ini">
                                                        🔄 Ganti Blok Aktif
                                                    </button>
                                                    <button type="button" class="ns-btn" style="font-size:.58rem; padding:.15rem .4rem;" x-data="{ copied: false }" @click="navigator.clipboard?.writeText(@js($msg['content'])); copied = true; setTimeout(() => copied = false, 2000)" title="Salin Jawaban">
                                                        📋 <span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                                                    </button>
                                                </div>
                                            @endif
                                            <div style="display:flex; flex-wrap:wrap; gap:.25rem; margin-top:.3rem;">
                                                <button type="button" class="ns-btn" style="font-size:.55rem; padding:.1rem .3rem; background:#f8fafc;" wire:click="sendAssistantWithText('Jelaskan rekomendasi ini secara lebih mendalam dengan rincian operasional.')">💡 Perjelas Operasional</button>
                                                <button type="button" class="ns-btn" style="font-size:.55rem; padding:.1rem .3rem; background:#f8fafc;" wire:click="sendAssistantWithText('Buatkan alternatif formulasi lain untuk kalimat/bagian ini.')">🔄 Alternatif Lain</button>
                                                <button type="button" class="ns-btn" style="font-size:.55rem; padding:.1rem .3rem; background:#f8fafc;" wire:click="sendAssistantWithText('Buat formulasi kalimat yang lebih padat dan ringkas.')">✂️ Padatkan Versi</button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @endif

                            @if ($assistantBusy)
                                <div style="padding:.5rem .65rem; border-radius:6px; background:#f5f3ff; border:1px solid #ddd6fe; font-size:.68rem; color:#5b21b6; display:flex; align-items:center; gap:.4rem;">
                                    <span style="animation: spin 1s linear infinite;">⏳</span> Sedang merumuskan rekomendasi penulisan ilmiah…
                                </div>
                            @endif
                        </div>

                        {{-- Assistant Input Bar --}}
                        <div style="padding:.5rem .6rem; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; gap:.35rem; align-items:center;">
                            <input type="text" class="ns-btn" style="flex:1; font-size:.7rem; background:#ffffff; text-align:left;"
                                   placeholder="Tanya Xishie (mis. /poles, /parafrase, atau konsultasi)..."
                                   wire:model="assistantInput" wire:keydown.enter="sendAssistant" />
                            <button type="button" class="ns-btn ns-btn--primary" style="font-size:.7rem; padding:.3rem .6rem;"
                                    wire:click="sendAssistant" wire:loading.attr="disabled" wire:target="sendAssistant,runTool,sendAssistantWithText,submitExecuteModel,executeCurrentModel">
                                Kirim
                            </button>
                        </div>
                    @endif

                    {{-- 2. PANEL KOMENTAR --}}
                    @if ($showComments)
                        <div class="ns-drawer__header" style="@if($assistantOpen) border-top:2px solid #cbd5e1; @endif">
                            <span>&#128172; Komentar ({{ count($comments) }})</span>
                            <button type="button" wire:click="$toggle('showComments')" style="background:none; border:none; cursor:pointer;">&times;</button>
                        </div>
                        <div class="ns-drawer__body">
                            {{-- Tambah komentar pada blok aktif --}}
                            <div style="display:flex; gap:.3rem;">
                                <input type="text" class="ns-btn" style="flex:1;" placeholder="Komentar untuk blok #{{ $activeBlock + 1 }}..." wire:model="commentDraft" wire:keydown.enter="addComment" />
                                <button type="button" class="ns-btn ns-btn--primary" wire:click="addComment">+</button>
                            </div>

                            @forelse ($comments as $cIdx => $c)
                                <div class="ns-comment-card @if(!empty($c['resolved'])) ns-comment-card--resolved @endif">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:.2rem;">
                                        <strong>{{ $c['author'] ?? 'Penulis' }}</strong>
                                        <span style="font-size:.55rem; color:#64748b;">Blok #{{ ((int)($c['blockIndex'] ?? 0)) + 1 }}</span>
                                    </div>
                                    <p style="margin:0 0 .3rem;">{{ $c['body'] ?? '' }}</p>
                                    <div style="display:flex; justify-content:flex-end; gap:.3rem; font-size:.6rem;">
                                        <button type="button" class="ns-block__btn" wire:click="resolveComment({{ $cIdx }})">
                                            {{ !empty($c['resolved']) ? 'Buka Kembali' : 'Selesai' }}
                                        </button>
                                        <button type="button" class="ns-block__btn" style="color:#b91c1c;" wire:click="deleteComment({{ $cIdx }})">Hapus</button>
                                    </div>
                                </div>
                            @empty
                                <div style="font-family:var(--rb-font-mono); font-size:.6rem; color:var(--rb-ink-muted);">Belum ada komentar.</div>
                            @endforelse
                        </div>
                    @endif

                    {{-- 3. PANEL LACAK REVISI --}}
                    @if ($showRevisions)
                        <div class="ns-drawer__header" style="border-top:1px solid #e2e8f0;">
                            <span>&#128065; Revisi ({{ $pendingRevisionCount }} pending)</span>
                            <button type="button" wire:click="$toggle('showRevisions')" style="background:none; border:none; cursor:pointer;">&times;</button>
                        </div>
                        <div class="ns-drawer__body">
                            <div style="display:flex; gap:.3rem; margin-bottom:.3rem;">
                                <button type="button" class="ns-btn ns-btn--primary" style="flex:1; font-size:.6rem;" wire:click="acceptAllRevisions">Terima Semua</button>
                                <button type="button" class="ns-btn" style="flex:1; font-size:.6rem;" wire:click="rejectAllRevisions">Tolak Semua</button>
                            </div>

                            @forelse ($revisions as $rIdx => $rev)
                                @if (($rev['status'] ?? '') === 'pending')
                                    <div class="ns-rev-card">
                                        <div style="font-size:.58rem; color:#64748b; margin-bottom:.2rem;">
                                            {{ $rev['author'] ?? 'Penulis' }} · Blok #{{ ((int)($rev['blockIndex'] ?? 0)) + 1 }}
                                        </div>
                                        <div style="margin-bottom:.3rem;">
                                            <span class="ns-rev-diff-del">{{ Str::limit((string)($rev['before'] ?? ''), 60) }}</span>
                                            <span class="ns-rev-diff-ins">{{ Str::limit((string)($rev['after'] ?? ''), 60) }}</span>
                                        </div>
                                        <div style="display:flex; justify-content:flex-end; gap:.3rem;">
                                            <button type="button" class="ns-block__btn" style="color:#16a34a; font-weight:700;" wire:click="acceptRevision({{ $rIdx }})">&#10003; Terima</button>
                                            <button type="button" class="ns-block__btn" style="color:#dc2626;" wire:click="rejectRevision({{ $rIdx }})">&#10007; Tolak</button>
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <div style="font-family:var(--rb-font-mono); font-size:.6rem; color:var(--rb-ink-muted);">Tidak ada revisi pending.</div>
                            @endforelse
                        </div>
                    @endif
                </aside>
            @endif
        </div>

        {{-- ================= BILAH STATUS (STATUS BAR) ================= --}}
        <footer class="ns-statusbar">
            <span>Halaman 1 dari 1</span>
            <span>{{ $blockCount }} blok</span>
            <span x-text="(liveWordCount || {{ $wordCount }}) + ' kata'">{{ number_format($wordCount) }} kata</span>
            <span x-text="(liveCharCount || {{ $charCount }}) + ' karakter'">{{ number_format($charCount) }} karakter</span>
            <span>{{ $proofingLanguage ?? 'Indonesia (PUEBI)' }}</span>
            <span style="display:inline-flex; align-items:center; gap:3px;">
                <span x-show="savingState === 'saving'" style="color:#b45309;">● Menyimpan…</span>
                <span x-show="savingState === 'saved'" style="color:#15803d;">✓ Tersimpan</span>
            </span>
            @if (!empty($statistics['readingTimeMinutes']))
                <span>&#9201; ~{{ $statistics['readingTimeMinutes'] }} menit baca</span>
            @endif
            @if ($trackChanges)
                <span style="color:#2563eb; font-weight:700;">● Lacak Perubahan AKTIF</span>
            @endif

            <span class="ns-statusbar__spacer"></span>

            <span>Sumber: {{ $sourceType === 'project' ? 'Proyek' : ($sourceFilename ?? 'Lokal') }}</span>
            <div style="display:flex; align-items:center; gap:.25rem;">
                <button type="button" class="ns-btn ns-btn--icon" style="padding:.1rem .3rem;" wire:click="zoomOut">-</button>
                <span>{{ $zoom }}%</span>
                <button type="button" class="ns-btn ns-btn--icon" style="padding:.1rem .3rem;" wire:click="zoomIn">+</button>
            </div>
        </footer>

        {{-- ================= MODAL DIALOGS ================= --}}
        @if ($dialog !== '')
            <div class="ns-dialog-backdrop" x-on:click.self="$wire.closeDialog()">
                <div class="ns-dialog">

                    {{-- Dialog Eksekusi Model AI Sekarang --}}
                    @if ($dialog === 'execute_model')
                        <div class="ns-dialog__header" style="background:linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color:#ffffff; padding:.6rem .8rem; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight:700; font-size:.85rem; display:inline-flex; align-items:center; gap:6px;">⚡ Eksekusi Model AI ke Naskah Studio</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer; font-size:1.1rem;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body" style="padding:1rem; display:flex; flex-direction:column; gap:.8rem;">
                            <div style="font-size:.75rem; color:#475569; line-height:1.45;">
                                Jalankan instruksi menggunakan model AI aktif dan terapkan hasilnya secara langsung ke kanvas naskah.
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.8rem;">
                                <div>
                                    <label style="display:block; font-size:.7rem; font-weight:700; color:#334155; margin-bottom:4px;">Model AI Aktif:</label>
                                    <select wire:model="currentAssistantModel" style="width:100%; font-size:.72rem; padding:.3rem .45rem; border:1px solid #cbd5e1; border-radius:4px; background:#ffffff;">
                                        @foreach ($this->getAvailableAssistantModels() as $availModel)
                                            <option value="{{ $availModel['id'] }}">
                                                {{ $availModel['name'] }} ({{ $availModel['provider'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; font-size:.7rem; font-weight:700; color:#334155; margin-bottom:4px;">Target Penempatan:</label>
                                    <select wire:model="executeModelTarget" style="width:100%; font-size:.72rem; padding:.3rem .45rem; border:1px solid #cbd5e1; border-radius:4px; background:#ffffff;">
                                        <option value="current_page">Halaman Saat Ini (Hal. {{ $this->getCurrentPageNumber() }})</option>
                                        <option value="active_block">Ganti Blok Aktif (#{{ $activeBlock + 1 }})</option>
                                        <option value="new_page">Halaman Baru (+ Pemisah Halaman)</option>
                                        <option value="append">Akhir Dokumen (Append)</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label style="display:block; font-size:.7rem; font-weight:700; color:#334155; margin-bottom:4px;">Instruksi Pembuatan Konten (Prompt):</label>
                                <textarea wire:model="executeModelPrompt" rows="3" placeholder="Misalnya: Buatkan daftar pustaka, kembangkan bab metodologi, atau buatkan tabel..." style="width:100%; font-size:.75rem; padding:.45rem; border:1px solid #cbd5e1; border-radius:4px; resize:vertical; font-family:inherit;"></textarea>
                                <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:5px;">
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:2px 6px; background:#f8fafc;" wire:click="$set('executeModelPrompt', 'Buatkan daftar pustaka')">📚 Buatkan Daftar Pustaka</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:2px 6px; background:#f8fafc;" wire:click="$set('executeModelPrompt', 'Buatkan tabel sintesis literatur')">📊 Buatkan Tabel</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:2px 6px; background:#f8fafc;" wire:click="$set('executeModelPrompt', 'Buatkan rumus regresi linear LaTeX')">π Buatkan Rumus</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:2px 6px; background:#f8fafc;" wire:click="$set('executeModelPrompt', 'Buatkan draf abstrak penelitian')">📝 Buatkan Abstrak</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:2px 6px; background:#f8fafc;" wire:click="$set('executeModelPrompt', 'Buatkan bab kesimpulan dan saran')">🏁 Buatkan Kesimpulan</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:2px 6px; background:#f8fafc;" wire:click="$set('executeModelPrompt', 'Rancang bab metodologi penelitian')">🔬 Metodologi</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:2px 6px; background:#f8fafc;" wire:click="$set('executeModelPrompt', 'Susun bab pendahuluan dan latar belakang')">📑 Pendahuluan</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:2px 6px; background:#f8fafc;" wire:click="$set('executeModelPrompt', 'Susun sintesis bab pembahasan kritis')">💬 Pembahasan</button>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:flex-end; gap:.5rem;">
                                <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                                <button type="button" class="ns-btn ns-btn--primary" style="background:#4f46e5; color:#ffffff; font-weight:700;" wire:click="submitExecuteModel" wire:loading.attr="disabled" wire:target="submitExecuteModel,executeCurrentModel">
                                    <span wire:loading.remove wire:target="submitExecuteModel,executeCurrentModel">⚡ Eksekusi &amp; Terapkan</span>
                                    <span wire:loading wire:target="submitExecuteModel,executeCurrentModel">Mengeksekusi…</span>
                                </button>
                            </div>
                        </div>

                    {{-- Dialog Cari & Ganti --}}
                    @elseif ($dialog === 'find')
                        <div class="ns-dialog__header">
                            <span>&#128269; Cari dan Ganti Teks</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:.2rem;">Kata / Kalimat yang dicari:</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="findQuery" placeholder="Cari..." wire:keydown.enter="findText" />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:.2rem;">Ganti dengan:</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="replaceQuery" placeholder="Ganti..." />
                            </div>
                            @if ($findResult)
                                <div style="background:#eff6ff; color:#1e40af; padding:.4rem .6rem; border-radius:3px; font-family:var(--rb-font-mono); font-size:.7rem;">
                                    {{ $findResult }}
                                </div>
                            @endif
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="findText">Cari</button>
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="replaceAll">Ganti Semua</button>
                        </div>

                    {{-- Dialog Info Statistik & Keterbacaan --}}
                    @elseif ($dialog === 'stats')
                        <div class="ns-dialog__header">
                            <span>&#128202; Statistik Dokumen &amp; Skor Keterbacaan</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem;">
                                <div style="border:1px solid #e2e8f0; padding:.5rem; border-radius:4px; background:#f8fafc;">
                                    <div style="font-size:.65rem; color:#64748b; font-family:var(--rb-font-mono);">JUMLAH KATA</div>
                                    <div style="font-size:1.3rem; font-weight:800; color:#1e40af;">{{ number_format($statistics['words'] ?? 0) }}</div>
                                </div>
                                <div style="border:1px solid #e2e8f0; padding:.5rem; border-radius:4px; background:#f8fafc;">
                                    <div style="font-size:.65rem; color:#64748b; font-family:var(--rb-font-mono);">KARAKTER (DENGAN SPASI)</div>
                                    <div style="font-size:1.3rem; font-weight:800; color:#1e40af;">{{ number_format($statistics['characters'] ?? 0) }}</div>
                                </div>
                                <div style="border:1px solid #e2e8f0; padding:.5rem; border-radius:4px; background:#f8fafc;">
                                    <div style="font-size:.65rem; color:#64748b; font-family:var(--rb-font-mono);">FLESCH READING EASE</div>
                                    <div style="font-size:1.3rem; font-weight:800; color:#16a34a;">{{ $statistics['readingEase'] ?? 0 }} / 100</div>
                                    <div style="font-size:.6rem; color:#64748b;">(Semakin tinggi semakin mudah dibaca)</div>
                                </div>
                                <div style="border:1px solid #e2e8f0; padding:.5rem; border-radius:4px; background:#f8fafc;">
                                    <div style="font-size:.65rem; color:#64748b; font-family:var(--rb-font-mono);">TINGKAT KELAS (GRADE LEVEL)</div>
                                    <div style="font-size:1.3rem; font-weight:800; color:#d97706;">Kelas {{ $statistics['gradeLevel'] ?? 0 }}</div>
                                </div>
                            </div>
                            <div style="font-family:var(--rb-font-mono); font-size:.68rem; line-height:1.6; border-top:1px solid #e2e8f0; padding-top:.5rem;">
                                <div>• Kalimat: {{ $statistics['sentences'] ?? 0 }} kalimat</div>
                                <div>• Paragraf: {{ $statistics['paragraphs'] ?? 0 }} paragraf</div>
                                <div>• Suku Kata: ~{{ $statistics['syllables'] ?? 0 }} suku kata</div>
                                <div>• Estimasi Waktu Baca: ~{{ $statistics['readingTimeMinutes'] ?? 1 }} menit</div>
                                <div>• Estimasi Waktu Bicara / Presentasi: ~{{ $statistics['speakingTimeMinutes'] ?? 1 }} menit</div>
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="closeDialog">Tutup</button>
                        </div>

                    {{-- Dialog Kustomisasi Paragraf Per Halaman --}}
                    @elseif ($dialog === 'paragraphCustomizer')
                        <div class="ns-dialog__header">
                            <span>&#9881; Kustomisasi Paragraf Halaman {{ $customParaPage }}</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body" style="display:flex; flex-direction:column; gap:12px; max-height:75vh; overflow-y:auto;">
                            <!-- Preset Cepat Kaidah Akademik -->
                            <div style="background:rgba(15,108,189,0.04); border:1px solid rgba(15,108,189,0.15); border-radius:4px; padding:8px 10px;">
                                <label style="display:block; font-weight:700; font-size:.68rem; color:#0f6cbd; margin-bottom:6px;">⚡ Terapkan Preset Kaidah Akademik:</label>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="ns-btn" wire:click="loadPresetIntoCustomizer('academic_standard')" style="padding:2px 8px; font-size:.65rem;">APA 1.5</button>
                                    <button type="button" class="ns-btn" wire:click="loadPresetIntoCustomizer('journal_ieee')" style="padding:2px 8px; font-size:.65rem;">IEEE / ACM 1.0</button>
                                    <button type="button" class="ns-btn" wire:click="loadPresetIntoCustomizer('thesis_dikti')" style="padding:2px 8px; font-size:.65rem;">Skripsi DIKTI 2.0</button>
                                    <button type="button" class="ns-btn" wire:click="loadPresetIntoCustomizer('abstract_block')" style="padding:2px 8px; font-size:.65rem;">Abstrak Block</button>
                                    <button type="button" class="ns-btn" wire:click="loadPresetIntoCustomizer('block_quote')" style="padding:2px 8px; font-size:.65rem;">Kutipan Langsung</button>
                                </div>
                            </div>

                            @php
                                $pageParas = $this->getPageParagraphs($customParaPage);
                            @endphp
                            <div>
                                <label style="font-weight:600; font-size:.7rem; display:block; margin-bottom:4px;">Pilih Paragraf di Halaman {{ $customParaPage }}:</label>
                                <select wire:change="selectPageParagraphForCustomizer($event.target.value)" class="ns-btn" style="width:100%; height:32px;">
                                    @forelse ($pageParas as $p)
                                        <option value="{{ $p['pageIndex'] }}" {{ $customParaIndex === $p['pageIndex'] ? 'selected' : '' }}>
                                            Paragraf #{{ $p['pageIndex'] + 1 }} ({{ ucfirst($p['block']['type'] ?? 'paragraph') }}): {{ Str::limit(strip_tags($p['block']['text'] ?? ''), 55) }}
                                        </option>
                                    @empty
                                        <option value="0">(Belum ada paragraf di halaman ini)</option>
                                    @endforelse
                                </select>
                            </div>

                            <div>
                                <label style="font-weight:600; font-size:.7rem; display:block; margin-bottom:4px;">Sunting Isi Teks Paragraf:</label>
                                <textarea wire:model="customParaText" class="ns-btn" rows="3" style="width:100%; font-size:.75rem; line-height:1.4; padding:6px; border:1px solid #cbd5e1; border-radius:3px;"></textarea>
                            </div>

                            <div>
                                <label style="font-weight:600; font-size:.7rem; display:block; margin-bottom:4px;">Perataan Teks (Alignment):</label>
                                <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:6px;">
                                    <button type="button" class="ns-btn {{ $customParaAlign === 'left' ? 'ns-btn--primary' : '' }}" wire:click="$set('customParaAlign', 'left')" style="text-align:center;">⬅️ Kiri</button>
                                    <button type="button" class="ns-btn {{ $customParaAlign === 'center' ? 'ns-btn--primary' : '' }}" wire:click="$set('customParaAlign', 'center')" style="text-align:center;">↔️ Tengah</button>
                                    <button type="button" class="ns-btn {{ $customParaAlign === 'right' ? 'ns-btn--primary' : '' }}" wire:click="$set('customParaAlign', 'right')" style="text-align:center;">➡️ Kanan</button>
                                    <button type="button" class="ns-btn {{ $customParaAlign === 'justify' ? 'ns-btn--primary' : '' }}" wire:click="$set('customParaAlign', 'justify')" style="text-align:center;">📶 Justify</button>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                                <div>
                                    <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:4px;">Jarak Baris:</label>
                                    <select wire:model="customParaLineSpacing" class="ns-btn" style="width:100%; height:30px;">
                                        <option value="1.0">1.0 (Single)</option>
                                        <option value="1.15">1.15</option>
                                        <option value="1.5">1.5 (Academic 1.5)</option>
                                        <option value="2.0">2.0 (Double)</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:4px;">Spasi Sebelum (pt):</label>
                                    <input type="number" min="0" max="72" step="2" wire:model="customParaSpacingBefore" class="ns-btn" style="width:100%; height:30px;">
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:4px;">Spasi Sesudah (pt):</label>
                                    <input type="number" min="0" max="72" step="2" wire:model="customParaSpacingAfter" class="ns-btn" style="width:100%; height:30px;">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                <div>
                                    <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:4px;">Indentasi Kiri (Level):</label>
                                    <input type="number" min="0" max="8" wire:model="customParaIndent" class="ns-btn" style="width:100%; height:30px;">
                                </div>
                                <div style="display:flex; align-items:center; margin-top:16px;">
                                    <label style="display:flex; align-items:center; gap:6px; font-size:.7rem; font-weight:600; cursor:pointer;">
                                        <input type="checkbox" wire:model="customParaFirstLineIndent">
                                        Paragraf Menjorok (1.27cm)
                                    </label>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                <div style="display:flex; align-items:center;">
                                    <label style="display:flex; align-items:center; gap:6px; font-size:.7rem; font-weight:600; cursor:pointer;">
                                        <input type="checkbox" wire:model="customParaDropCap">
                                        Gaya Drop Cap (Huruf Besar)
                                    </label>
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:4px;">Tipe Blok:</label>
                                    <select wire:model="customParaType" class="ns-btn" style="width:100%; height:30px;">
                                        <option value="paragraph">Paragraf Standar</option>
                                        <option value="quote">Kutipan (Blockquote)</option>
                                        <option value="callout">Kotak Catatan (Callout)</option>
                                        <option value="bullet">Daftar Poin (Bullet)</option>
                                        <option value="numbered">Daftar Bernomor (Numbered)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Warna Latar & Garis Tepi -->
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                                <div>
                                    <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:4px;">Warna Latar (Shading):</label>
                                    <select wire:model="customParaBgColor" class="ns-btn" style="width:100%; height:30px;">
                                        <option value="">(Transparan)</option>
                                        <option value="#f8fafc">Abu-abu Lembut (#f8fafc)</option>
                                        <option value="#eff6ff">Biru Muda (#eff6ff)</option>
                                        <option value="#f0fdf4">Hijau Muda (#f0fdf4)</option>
                                        <option value="#fefce8">Kuning Lembut (#fefce8)</option>
                                        <option value="#faf5ff">Ungu Muda (#faf5ff)</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:4px;">Aksen Garis (Border):</label>
                                    <select wire:model="customParaBorderColor" class="ns-btn" style="width:100%; height:30px;">
                                        <option value="">(Tanpa Garis)</option>
                                        <option value="#0f6cbd">Biru Royal (#0f6cbd)</option>
                                        <option value="#16a34a">Hijau Zamrud (#16a34a)</option>
                                        <option value="#d97706">Emas / Amber (#d97706)</option>
                                        <option value="#dc2626">Merah (#dc2626)</option>
                                        <option value="#64748b">Abu-abu (#64748b)</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:4px;">Indentasi Kanan (Level):</label>
                                    <input type="number" min="0" max="8" wire:model="customParaRightIndent" class="ns-btn" style="width:100%; height:30px;">
                                </div>
                            </div>

                            <!-- Cari & Ganti Khusus Halaman -->
                            <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:8px 10px;">
                                <label style="display:block; font-weight:700; font-size:.68rem; color:#334155; margin-bottom:6px;">🔍 Cari &amp; Ganti Teks Khusus Halaman {{ $customParaPage }}:</label>
                                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:6px; align-items:center;">
                                    <input type="text" wire:model="pageSearchQuery" placeholder="Kata dicari..." class="ns-btn" style="height:28px; font-size:.65rem; background:#fff;">
                                    <input type="text" wire:model="pageReplaceQuery" placeholder="Pengganti..." class="ns-btn" style="height:28px; font-size:.65rem; background:#fff;">
                                    <button type="button" class="ns-btn ns-btn--primary" wire:click="findAndReplaceOnPage({{ $customParaPage }})" style="padding:4px 10px; height:28px; font-size:.65rem;">Ganti</button>
                                </div>
                            </div>

                            <!-- Alat Manipulasi Paragraf -->
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:8px 10px; display:flex; flex-direction:column; gap:6px;">
                                <label style="display:block; font-weight:700; font-size:.68rem; color:#475569;">🛠️ Alat Manipulasi Paragraf:</label>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="ns-btn" wire:click="movePageParagraph({{ $customParaPage }}, {{ $customParaIndex }}, -1)" style="padding:2px 8px; font-size:.65rem;" title="Geser naik posisi paragraf pada halaman ini">⬆️ Geser Naik</button>
                                    <button type="button" class="ns-btn" wire:click="movePageParagraph({{ $customParaPage }}, {{ $customParaIndex }}, 1)" style="padding:2px 8px; font-size:.65rem;" title="Geser turun posisi paragraf pada halaman ini">⬇️ Geser Turun</button>
                                    <button type="button" class="ns-btn" wire:click="duplicatePageParagraph({{ $customParaPage }}, {{ $customParaIndex }})" style="padding:2px 8px; font-size:.65rem;" title="Duplikasi paragraf ini">📄 Duplikat</button>
                                    <button type="button" class="ns-btn" wire:click="splitPageParagraph({{ $customParaPage }}, {{ $customParaIndex }})" style="padding:2px 8px; font-size:.65rem;" title="Pecah paragraf panjang menjadi dua alinea">✂️ Pecah Paragraf</button>
                                    <button type="button" class="ns-btn" wire:click="mergePageParagraphs({{ $customParaPage }}, {{ $customParaIndex }})" style="padding:2px 8px; font-size:.65rem;" title="Gabungkan dengan paragraf berikutnya">🔗 Gabung Berikutnya</button>
                                    <button type="button" class="ns-btn" wire:click="cleanPageParagraphSpaces({{ $customParaPage }})" style="padding:2px 8px; font-size:.65rem;" title="Rapikan spasi berlebih pada halaman ini">🧹 Rapikan Spasi</button>
                                    <button type="button" class="ns-btn" wire:click="transformPageParagraphCase({{ $customParaPage }}, 'sentence')" style="padding:2px 8px; font-size:.65rem;" title="Ubah ke Sentence Case">🔤 Sentence Case</button>
                                    <button type="button" class="ns-btn" wire:click="deletePageParagraph({{ $customParaPage }}, {{ $customParaIndex }})" style="padding:2px 8px; font-size:.65rem; color:#d13438;" wire:confirm="Hapus paragraf ini dari halaman?" title="Hapus paragraf ini">🗑️ Hapus</button>
                                </div>
                            </div>

                            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:10px; border-top:1px solid #e2e8f0; padding-top:10px; flex-wrap:wrap;">
                                <button type="button" class="ns-btn" wire:click="closeDialog" style="padding:6px 12px;">Batal</button>
                                <button type="button" class="ns-btn" wire:click="applyParagraphCustomizer(true)" style="padding:6px 12px; font-weight:600;" title="Terapkan perataan, line spacing, dan indentasi ke seluruh paragraf di halaman ini">
                                    Terapkan ke Semua Paragraf Hal. {{ $customParaPage }}
                                </button>
                                <button type="button" class="ns-btn ns-btn--primary" wire:click="applyParagraphCustomizer(false)" style="padding:6px 14px; font-weight:700;">
                                    Simpan Paragraf Ini
                                </button>
                            </div>
                        </div>

                    {{-- Dialog Tata Letak Kertas (Page Setup) --}}
                    @elseif ($dialog === 'pagesetup')
                        <div class="ns-dialog__header">
                            <span>&#9881; Tata Letak Kertas &amp; Margin</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem;">
                                <div>
                                    <label style="font-weight:600; font-size:.68rem;">Margin Atas (mm):</label>
                                    <input type="number" class="ns-btn" style="width:100%;" wire:model="pageSetup.marginTop" />
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem;">Margin Bawah (mm):</label>
                                    <input type="number" class="ns-btn" style="width:100%;" wire:model="pageSetup.marginBottom" />
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem;">Margin Kiri (mm):</label>
                                    <input type="number" class="ns-btn" style="width:100%;" wire:model="pageSetup.marginLeft" />
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem;">Margin Kanan (mm):</label>
                                    <input type="number" class="ns-btn" style="width:100%;" wire:model="pageSetup.marginRight" />
                                </div>
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Teks Tanda Air (Watermark):</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="pageSetup.watermark" placeholder="Mis. DRAF / RAHASIA..." />
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="applyPageSetup">Terapkan</button>
                        </div>

                    {{-- Dialog Cek Ejaan (Spelling) --}}
                    @elseif ($dialog === 'spelling')
                        <div class="ns-dialog__header">
                            <span>&#10003; Hasil Pemeriksaan Ejaan</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <p>{{ $spellResult }}</p>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="closeDialog">Tutup</button>
                        </div>

                    {{-- Dialog Tesaurus --}}
                    @elseif ($dialog === 'thesaurus')
                        <div class="ns-dialog__header">
                            <span>&#128214; Tesaurus Kata Akademik</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div style="display:flex; gap:.3rem;">
                                <input type="text" class="ns-btn" style="flex:1;" wire:model="thesaurusWord" placeholder="Masukkan kata (mis. penelitian, hasil, masalah)..." wire:keydown.enter="runThesaurus" />
                                <button type="button" class="ns-btn ns-btn--primary" wire:click="runThesaurus">Cari</button>
                            </div>
                            @if ($thesaurusResult)
                                <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:.5rem; border-radius:4px;">
                                    {{ $thesaurusResult }}
                                </div>
                            @endif
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                        </div>

                    {{-- Dialog Sisipkan Tabel --}}
                    @elseif ($dialog === 'table')
                        <div class="ns-dialog__header">
                            <span>&#8862; Sisipkan Tabel Baru</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem;">
                                <div>
                                    <label style="font-weight:600; font-size:.68rem;">Jumlah Baris:</label>
                                    <input type="number" min="1" max="20" class="ns-btn" style="width:100%;" wire:model="tableRows" />
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem;">Jumlah Kolom:</label>
                                    <input type="number" min="1" max="10" class="ns-btn" style="width:100%;" wire:model="tableCols" />
                                </div>
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                            <button type="button" class="ns-btn ns-btn--primary" @click="insertTable($wire.tableRows || 3, $wire.tableCols || 3); $wire.closeDialog()">Sisipkan Tabel</button>
                        </div>

                    {{-- Dialog Editor Rumus Matematika (LaTeX) --}}
                    @elseif ($dialog === 'equation')
                        <div class="ns-dialog__header">
                            <span>&#960; {{ $editingEquationIndex !== null ? 'Edit Rumus LaTeX' : 'Sisipkan Rumus Matematika (LaTeX)' }}</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body" style="display:flex; flex-direction:column; gap:.65rem;">
                            <div>
                                <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:.25rem;">Pilih Templat Cepat (Presets):</label>
                                <div style="display:flex; flex-wrap:wrap; gap:.25rem;">
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('quadratic')">Rumus ABC</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('pythagoras')">Pythagoras</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('gaussian')">Distribusi Normal</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('integral')">Integral Tentu</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('matrix')">Matriks 2x2</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('euler')">Identitas Euler</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('regression')">Regresi Berganda</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('einstein')">Massa-Energi (E=mc²)</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('taylor')">Deret Taylor</button>
                                    <button type="button" class="ns-btn" style="font-size:.6rem; padding:.15rem .35rem;" wire:click="setEquationPreset('fourier')">Fourier</button>
                                </div>
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem; display:block; margin-bottom:.2rem;">Sintaks Rumus LaTeX:</label>
                                <textarea rows="3" class="ns-btn" style="font-family:ui-monospace, monospace; font-size:.75rem; width:100%; resize:vertical; text-align:left;" wire:model.live.debounce.250ms="equationLatex" placeholder="Contoh: x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}"></textarea>
                            </div>
                            <!-- Palet Simbol Cepat -->
                            <div>
                                <label style="font-weight:600; font-size:.62rem; color:#64748b; display:block; margin-bottom:.2rem;">Simbol &amp; Operator Cepat:</label>
                                <div style="display:flex; flex-wrap:wrap; gap:.2rem; background:#f8fafc; padding:.3rem; border:1px solid #e2e8f0; border-radius:3px;">
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\pm')">±</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\times')">×</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\div')">÷</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\neq')">≠</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\approx')">≈</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\le')">≤</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\ge')">≥</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\alpha')">α</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\beta')">β</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\gamma')">γ</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\sigma')">σ</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\mu')">μ</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\pi')">π</button>
                                    <button type="button" class="ns-btn" style="padding:.1rem .3rem; font-size:.65rem;" wire:click="appendEquationSymbol('\\infty')">∞</button>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem;">
                                <div>
                                    <label style="font-weight:600; font-size:.68rem;">Penomoran (Keterangan):</label>
                                    <input type="text" class="ns-btn" style="width:100%;" wire:model="equationCaption" placeholder="Contoh: (1) atau (2.1)" />
                                </div>
                                <div>
                                    <label style="font-weight:600; font-size:.68rem;">Tampilan:</label>
                                    <select class="ns-btn" style="width:100%;" wire:model="equationDisplay">
                                        <option value="block">Blok Mandiri (Tengah)</option>
                                        <option value="inline">Sebaris (Inline)</option>
                                    </select>
                                </div>
                            </div>
                            <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:.4rem; text-align:center;">
                                <span style="font-size:.58rem; color:#64748b; font-weight:700; display:block; margin-bottom:.2rem;">Pratinjau KaTeX:</span>
                                <div style="font-family:'Cambria Math', KaTeX_Math, serif; font-size:1.15rem; min-height:28px; display:flex; align-items:center; justify-content:center;">
                                    <span class="latex-math-render" data-latex="{{ $equationLatex }}">{{ $equationLatex }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="ns-dialog__footer" style="display:flex; justify-content:space-between;">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                            <div style="display:flex; gap:.3rem;">
                                <button type="button" class="ns-btn" wire:click="insertInlineEquation" title="Sisipkan sebaris ($)">Inline ($)</button>
                                <button type="button" class="ns-btn ns-btn--primary" wire:click="insertEquation">{{ $editingEquationIndex !== null ? 'Perbarui Rumus' : 'Sisipkan Blok Persamaan' }}</button>
                            </div>
                        </div>

                    {{-- Dialog Sisipkan Gambar --}}
                    @elseif ($dialog === 'image')
                        <div class="ns-dialog__header">
                            <span>&#128444; Sisipkan Gambar</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Pilih Berkas Gambar (PNG, JPG, SVG, WebP):</label>
                                <input type="file" wire:model="imageUpload" accept="image/*" class="ns-btn" style="width:100%; margin-top:.2rem;" />
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Teks Keterangan / Alt:</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="imageAlt" placeholder="Keterangan gambar..." />
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Lebar Tampilan (piksel):</label>
                                <input type="number" class="ns-btn" style="width:100%;" wire:model="imageWidth" value="320" />
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                        </div>

                    {{-- Dialog Sisipkan Tautan --}}
                    @elseif ($dialog === 'link')
                        <div class="ns-dialog__header">
                            <span>&#128279; Sisipkan Tautan (Hyperlink)</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">URL Tujuan (https://...):</label>
                                <input type="url" class="ns-btn" style="width:100%;" wire:model="linkUrl" placeholder="https://example.com" />
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Teks Tampilan:</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="linkText" placeholder="Teks yang dapat diklik..." />
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="insertLink">Sisipkan Tautan</button>
                        </div>

                    {{-- Dialog Catatan Kaki --}}
                    @elseif ($dialog === 'footnote')
                        <div class="ns-dialog__header">
                            <span>&#128278; Sisipkan Catatan Kaki (Footnote)</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Isi Catatan Kaki:</label>
                                <textarea class="ns-btn" style="width:100%; height:75px;" wire:model="footnoteText" placeholder="Ketik keterangan catatan kaki..."></textarea>
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="insertFootnote">Sisipkan</button>
                        </div>

                    {{-- Dialog Simbol Khusus --}}
                    @elseif ($dialog === 'symbols')
                        <div class="ns-dialog__header">
                            <span>&#937; Simbol &amp; Karakter Khusus</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div style="display:grid; grid-template-columns:repeat(8, 1fr); gap:.35rem;">
                                @foreach (['©', '®', '™', '§', '¶', '±', '×', '÷', '≠', '≤', '≥', '≈', '∞', '°', 'µ', 'α', 'β', 'γ', 'π', '∑', '√', '—', '–', '•', '€', '£', '¥', '→'] as $sym)
                                    <button type="button" class="ns-btn" style="font-size:1.1rem; padding:.3rem; justify-content:center;" wire:click="insertSymbol('{{ $sym }}')">
                                        {{ $sym }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                        </div>

                    {{-- Dialog Mail Merge --}}
                    @elseif ($dialog === 'merge')
                        <div class="ns-dialog__header">
                            <span>&#128231; Konfigurasi Mail Merge CSV</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Data CSV (baris 1 = header nama kolom):</label>
                                <textarea class="ns-btn" style="width:100%; height:80px; font-family:var(--rb-font-mono); font-size:.68rem;" wire:model="mergeCsv" placeholder="nama,instansi,kota&#10;Budi,Universitas Indonesia,Jakarta&#10;Siti,ITB,Bandung"></textarea>
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Template Dokumen (gunakan &#123;&#123;kolom&#125;&#125;):</label>
                                <textarea class="ns-btn" style="width:100%; height:60px;" wire:model="mergeTemplate" placeholder="Yth. {{nama}} di {{instansi}}..."></textarea>
                            </div>
                            @if (!empty($mergePreviews))
                                <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:.4rem; border-radius:4px; font-size:.7rem;">
                                    <strong>Pratinjau Rekaman 1:</strong>
                                    <div>{{ $mergePreviews[0]['text'] ?? '' }}</div>
                                </div>
                            @endif
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="previewMerge">Pratinjau</button>
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="insertMerge">Sisipkan ke Naskah</button>
                        </div>

                    {{-- Dialog Tambah Komentar --}}
                    @elseif ($dialog === 'comment')
                        <div class="ns-dialog__header">
                            <span>&#128172; Tambah Komentar pada Blok #{{ $activeBlock + 1 }}</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <textarea class="ns-btn" style="width:100%; height:75px;" wire:model="commentDraft" placeholder="Tulis komentar..."></textarea>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="postCommentAndClose">Simpan Komentar</button>
                        </div>

                    {{-- Dialog Pusat Perintah (Wordcraft Command Palette / HUD) --}}
                    @elseif ($dialog === 'palette')
                        <div class="ns-dialog__header">
                            <span>&#128269; Pusat Perintah Naskah Studio (Wordcraft HUD)</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <input type="text" class="ns-btn" style="width:100%; font-size:.8rem; padding:.45rem .65rem;"
                                   placeholder="Ketik perintah (mis. simpan, tabel, ekspor, margin, ai, autocorrect)..."
                                   wire:model.live="commandSearch" autofocus />
                            <div style="max-height:280px; overflow-y:auto; display:flex; flex-direction:column; gap:.25rem; margin-top:.35rem;">
                                @php
                                    $q = strtolower(trim($commandSearch));
                                    $filtered = array_filter($commands, static fn(array $c): bool => $q === '' || str_contains(strtolower($c['label']), $q) || str_contains(strtolower($c['category']), $q) || str_contains(strtolower($c['id']), $q));
                                @endphp
                                @forelse ($filtered as $cmd)
                                    <button type="button" class="ns-nav__item" style="border:1px solid #e2e8f0; border-radius:3px; padding:.35rem .6rem;"
                                            wire:click="executeCommand('{{ $cmd['id'] }}')">
                                        <span style="display:flex; align-items:center; gap:.4rem;">
                                            <span>{{ $cmd['icon'] }}</span>
                                            <strong>{{ $cmd['label'] }}</strong>
                                            <span style="font-size:.58rem; color:#64748b; background:#f1f5f9; padding:.1rem .3rem; border-radius:2px;">{{ $cmd['category'] }}</span>
                                        </span>
                                        @if (!empty($cmd['shortcut']))
                                            <span style="font-family:var(--rb-font-mono); font-size:.6rem; color:#64748b; border:1px solid #cbd5e1; padding:.1rem .3rem; border-radius:3px;">{{ $cmd['shortcut'] }}</span>
                                        @endif
                                    </button>
                                @empty
                                    <div style="font-family:var(--rb-font-mono); font-size:.65rem; color:#64748b; padding:.5rem;">Tidak ada perintah yang cocok.</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                        </div>

                    {{-- Dialog Properti Dokumen & Metadata (LibreOffice File Properties) --}}
                    @elseif ($dialog === 'properties')
                        <div class="ns-dialog__header">
                            <span>&#8505; Properti Dokumen &amp; Metadata</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Judul Dokumen:</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="docTitle" wire:change="markDirty" />
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Penulis (Author):</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="metadata.author" wire:change="markDirty" placeholder="Nama penulis..." />
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Subjek / Topik:</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="metadata.subject" wire:change="markDirty" placeholder="Topik bahasan..." />
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:.68rem;">Kata Kunci (Keywords):</label>
                                <input type="text" class="ns-btn" style="width:100%;" wire:model="metadata.keywords" wire:change="markDirty" placeholder="Kata kunci naskah..." />
                            </div>
                            <div style="font-family:var(--rb-font-mono); font-size:.62rem; color:#64748b; border-top:1px solid #e2e8f0; padding-top:.4rem; line-height:1.6;">
                                <div>• ID Dokumen: #{{ $documentId ?? 'Baru (Belum Disimpan)' }}</div>
                                <div>• Jumlah Kata: {{ number_format($wordCount) }} kata</div>
                                <div>• Jumlah Karakter: {{ number_format($charCount) }} karakter</div>
                                <div>• Total Blok: {{ $blockCount }} blok</div>
                                <div>• Terakhir Disimpan: {{ $lastSavedAt ?? 'Belum disimpan' }}</div>
                                <div>• Riwayat Versi: {{ count($snapshots) }} snapshot</div>
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="saveProperties">Simpan Properti</button>
                        </div>

                    {{-- Dialog Riwayat Versi & Snapshot (LibreOffice Versions) --}}
                    @elseif ($dialog === 'snapshots')
                        <div class="ns-dialog__header">
                            <span>&#128193; Riwayat Versi &amp; Snapshot Naskah</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <div style="display:flex; gap:.3rem;">
                                <input type="text" class="ns-btn" style="flex:1;" placeholder="Label versi baru (mis. Draf Awal, Sebelum Seminar)..." wire:model="snapshotLabel" />
                                <button type="button" class="ns-btn ns-btn--primary" wire:click="createSnapshot">+ Simpan Versi</button>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:.3rem; margin-top:.4rem; max-height:260px; overflow-y:auto;">
                                @forelse ($snapshots as $sIdx => $snap)
                                    <div style="border:1px solid #cbd5e1; background:#f8fafc; padding:.45rem .6rem; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">
                                        <div>
                                            <strong>{{ $snap['label'] }}</strong>
                                            <div style="font-size:.58rem; color:#64748b; font-family:var(--rb-font-mono);">
                                                {{ $snap['at'] }} · {{ $snap['count'] }} blok
                                            </div>
                                        </div>
                                        <div style="display:flex; gap:.3rem;">
                                            <button type="button" class="ns-block__btn" wire:click="restoreSnapshot({{ $sIdx }})" title="Kembalikan naskah ke versi ini">&#8630; Pulihkan</button>
                                            <button type="button" class="ns-block__btn" style="color:#b91c1c;" wire:click="deleteSnapshot({{ $sIdx }})" title="Hapus snapshot ini">&#128465;</button>
                                        </div>
                                    </div>
                                @empty
                                    <div style="font-family:var(--rb-font-mono); font-size:.65rem; color:#64748b; padding:.5rem;">Belum ada snapshot versi tersimpan.</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                        </div>

                    {{-- Dialog Tombol Pintas Papan Ketik (Keyboard Shortcuts) --}}
                    @elseif ($dialog === 'shortcuts')
                        <div class="ns-dialog__header">
                            <span>&#9000; Tombol Pintas Papan Ketik (Keyboard Shortcuts)</span>
                            <button type="button" style="background:none; border:none; color:#fff; cursor:pointer;" wire:click="closeDialog">&times;</button>
                        </div>
                        <div class="ns-dialog__body">
                            <table class="ns-table" style="font-size:.7rem;">
                                <thead><tr><th>Tombol</th><th>Fungsi</th><th>Kategori</th></tr></thead>
                                <tbody>
                                    <tr><td><code>Alt + I</code></td><td>Buka / Tutup Xishie Assistant (AI Copilot)</td><td>Kecerdasan Buatan</td></tr>
                                    <tr><td><code>Ctrl + S</code></td><td>Simpan Naskah</td><td>Berkas</td></tr>
                                    <tr><td><code>Ctrl + Z</code></td><td>Urungkan Perubahan (Undo)</td><td>Sunting</td></tr>
                                    <tr><td><code>Ctrl + Y</code></td><td>Ulangi Perubahan (Redo)</td><td>Sunting</td></tr>
                                    <tr><td><code>Ctrl + K</code></td><td>Pusat Perintah &amp; Fitur (Command Palette)</td><td>Alat</td></tr>
                                    <tr><td><code>Ctrl + F</code></td><td>Cari dan Ganti Teks</td><td>Sunting</td></tr>
                                    <tr><td><code>Ctrl + Enter</code></td><td>Pemisah Halaman (Page Break)</td><td>Sisipkan</td></tr>
                                    <tr><td><code>Ctrl + B</code></td><td>Cetak Tebal (Bold)</td><td>Format</td></tr>
                                    <tr><td><code>Ctrl + I</code></td><td>Cetak Miring (Italic)</td><td>Format</td></tr>
                                    <tr><td><code>Ctrl + U</code></td><td>Garis Bawah (Underline)</td><td>Format</td></tr>
                                    <tr><td><code>Ctrl + [ / ]</code></td><td>Kecilkan / Besarkan Ukuran Huruf (Font Size)</td><td>Format</td></tr>
                                    <tr><td><code>Ctrl + Shift + C / V</code></td><td>Salin / Tempel Format (Format Painter)</td><td>Format</td></tr>
                                    <tr><td><code>Ctrl + Alt + 1 / 2 / 3</code></td><td>Terapkan Gaya Judul 1 / 2 / 3 (Heading)</td><td>Format Gaya</td></tr>
                                    <tr><td><code>Ctrl + Alt + 0</code></td><td>Terapkan Gaya Paragraf Normal</td><td>Format Gaya</td></tr>
                                    <tr><td><code>Ctrl + P</code></td><td>Cetak Dokumen</td><td>Berkas</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="ns-dialog__footer">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="closeDialog">Tutup</button>
                        </div>
                    @endif

                </div>
            </div>
        @endif

        {{-- FAB Floating Assistant Button --}}
        @if (!$assistantOpen)
            <button type="button" class="ns-fab" wire:click="openAssistant" aria-label="Buka Xishie Assistant" title="Buka Xishie Assistant (Alt + I)">
                <span class="ns-fab__dot"></span>
                <span>✨ Xishie Assistant</span>
            </button>
        @endif

        {{-- Modern Interactive Glassmorphic Notification Toast System --}}
        @include('components.studio-toast')

    </div>

    <script>
        function renderFilamentLatex() {
            if (typeof katex === 'undefined') return;
            document.querySelectorAll('.latex-math-render').forEach(el => {
                const tex = el.getAttribute('data-latex') || el.textContent || '';
                if (tex.trim() !== '') {
                    if (el.dataset.renderedTex === tex) return;
                    try {
                        katex.render(tex, el, { throwOnError: false, displayMode: true });
                        el.dataset.renderedTex = tex;
                    } catch (e) {}
                }
            });
        }
        document.addEventListener('DOMContentLoaded', () => renderFilamentLatex());
        document.addEventListener('livewire:initialized', () => {
            renderFilamentLatex();
            Livewire.hook('morph.updated', () => setTimeout(renderFilamentLatex, 60));
            Livewire.hook('commit', () => setTimeout(renderFilamentLatex, 60));
        });
    </script>
</x-filament-panels::page>
