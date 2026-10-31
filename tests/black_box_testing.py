#!/usr/bin/env python3
"""
Comprehensive Black-Box Testing Suite for Xishie, Naskah Studio & Xishie Assistant.
Tests live HTTP endpoints, REST API, session authentication, and Ribbon/Assistant UI components.
"""

import sys
import json
import subprocess
import urllib.request
import urllib.parse

BASE_URL = "http://localhost:8208"
OMNIGENSI_URL = "http://localhost:8123"
MODEL_PROXY_URL = "http://localhost:20128/v1"

passed = 0
failed = 0

def test(name, condition, detail=""):
    global passed, failed
    if condition:
        print(f"[PASS] {name}" + (f" -> {detail}" if detail else ""))
        passed += 1
    else:
        print(f"[FAIL] {name}" + (f" -> {detail}" if detail else ""))
        failed += 1

def http_get(path, headers=None, timeout=20):
    url = f"{BASE_URL}{path}" if path.startswith("/") else path
    req = urllib.request.Request(url, headers=headers or {"User-Agent": "Mozilla/5.0"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as res:
            return res.status, res.read().decode("utf-8", errors="replace"), res.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", errors="replace"), e.headers
    except Exception as e:
        return 500, str(e), {}

def http_post(path, data, headers=None, timeout=60):
    url = f"{BASE_URL}{path}" if path.startswith("/") else path
    default_headers = {"User-Agent": "Mozilla/5.0"}
    if isinstance(data, dict):
        post_data = json.dumps(data).encode("utf-8")
        default_headers["Content-Type"] = "application/json"
    elif isinstance(data, str):
        post_data = data.encode("utf-8")
    else:
        post_data = data
    if headers:
        default_headers.update(headers)
    req = urllib.request.Request(url, data=post_data, headers=default_headers)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as res:
            return res.status, res.read().decode("utf-8", errors="replace"), res.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", errors="replace"), e.headers
    except Exception as e:
        return 500, str(e), {}

print("=====================================================================")
print("          XISHIE BLACK-BOX END-TO-END VERIFICATION SUITE             ")
print("=====================================================================")

# 1. Infrastructure & Service Health Checks
print("\n--- 1. Infrastructure & Service Health Checks ---")
status, body, _ = http_get(f"{OMNIGENSI_URL}/health")
test("Omnigensi Health Probe", status == 200 and "status" in body, f"status={status}")

status, body, _ = http_get(f"{MODEL_PROXY_URL}/models")
test("Upstream Agnes Model Gateway Probe", status == 200 and "hv/agnes-3.0-flash" in body, f"status={status}")

status, body, _ = http_get("/")
test("Xishie Web Landing Page", status == 200 and "Xishie" in body, f"status={status}")

status, body, _ = http_get("/studio")
test("Standalone Naskah Studio Route (/studio)", status == 200 and "Naskah Studio" in body, f"status={status}")

# 2. REST API Black-Box Tests
print("\n--- 2. Naskah Studio REST API Endpoints ---")
status, body, _ = http_get("/api/v1/naskah/models")
data = json.loads(body)
models = data.get("data", [])
model_ids = [m.get("id") for m in models]
test("GET /api/v1/naskah/models", status == 200 and len(models) >= 5, f"total_models={len(models)}")
test("Agnes 3.0 Flash Available in REST Models", "hv/agnes-3.0-flash" in model_ids, "model=hv/agnes-3.0-flash")

status, body, _ = http_get("/api/v1/naskah/documents")
doc_data = json.loads(body)
docs = doc_data.get("data", [])
test("GET /api/v1/naskah/documents", status == 200, f"documents_count={len(docs)}")

doc_id = docs[0]["id"] if docs else 1

status, body, _ = http_get(f"/api/v1/naskah/documents/{doc_id}")
test(f"GET /api/v1/naskah/documents/{doc_id}", status == 200 and "blocks" in body)

status, body, _ = http_post(f"/api/v1/naskah/documents/{doc_id}/autocorrect", {})
test(f"POST /api/v1/naskah/documents/{doc_id}/autocorrect", status == 200 and ("status" in body or "corrections_count" in body))

status, body, _ = http_post(f"/api/v1/naskah/documents/{doc_id}/format-paragraph", {"page": 1, "preset": "academic_standard"})
test(f"POST /api/v1/naskah/documents/{doc_id}/format-paragraph", status == 200 and "academic_standard" in body)

status, body, _ = http_get(f"/api/v1/naskah/documents/{doc_id}/readability-page?page=1")
test(f"GET /api/v1/naskah/documents/{doc_id}/readability-page", status == 200 and "reading_time_seconds" in body)

status, body, _ = http_post(f"/api/v1/naskah/documents/{doc_id}/audit-page", {"page": 1})
test(f"POST /api/v1/naskah/documents/{doc_id}/audit-page", status == 200 and "score" in body)

status, body, _ = http_post(f"/api/v1/naskah/documents/{doc_id}/clean-page", {"page": 1})
test(f"POST /api/v1/naskah/documents/{doc_id}/clean-page", status == 200 and "cleaned_count" in body)

status, body, _ = http_post(f"/api/v1/naskah/documents/{doc_id}/align-page", {"page": 1, "align": "justify"})
test(f"POST /api/v1/naskah/documents/{doc_id}/align-page", status == 200 and "aligned_count" in body)

status, body, _ = http_get(f"/api/v1/naskah/documents/{doc_id}/export?format=json")
test(f"GET /api/v1/naskah/documents/{doc_id}/export?format=json", status == 200 and "word_count" in body)

status, body, _ = http_get(f"/api/v1/naskah/documents/{doc_id}/statistics")
test(f"GET /api/v1/naskah/documents/{doc_id}/statistics", status == 200 and "statistics" in body)

status, body, _ = http_get(f"/api/v1/naskah/documents/{doc_id}/export?format=csv")
test(f"GET /api/v1/naskah/documents/{doc_id}/export?format=csv", status == 200)

status, body, _ = http_get(f"/api/v1/naskah/documents/{doc_id}/export-page?page=1&format=md")
test(f"GET /api/v1/naskah/documents/{doc_id}/export-page?page=1", status == 200 and len(body) > 0)

status, body, _ = http_post(f"/api/v1/naskah/documents/{doc_id}/execute-model", {"preset": "table", "page": 1, "max_tokens": 100}, timeout=90)
if status != 200:
    time.sleep(1)
    status, body, _ = http_post(f"/api/v1/naskah/documents/{doc_id}/execute-model", {"preset": "table", "page": 1, "max_tokens": 100}, timeout=90)
test(f"POST /api/v1/naskah/documents/{doc_id}/execute-model", status == 200 and ("blocks_inserted" in body or "word_count" in body))

cli_stats = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'naskah:manage', 'stats', str(doc_id)], capture_output=True, text=True)
test("CLI naskah:manage stats Execution", cli_stats.returncode == 0 and "Kategori Keterbacaan" in cli_stats.stdout)
test("CLI naskah:manage Flesch Reading Ease Present", "Flesch Reading Ease" in cli_stats.stdout)

cli_md = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'naskah:manage', 'export', str(doc_id), '--format=md'], capture_output=True, text=True)
test("CLI naskah:manage export md Execution", cli_md.returncode == 0 and "naskah-export-" in cli_md.stdout)

cli_tex = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'naskah:manage', 'export', str(doc_id), '--format=latex'], capture_output=True, text=True)
test("CLI naskah:manage export latex Execution", cli_tex.returncode == 0 and "naskah-export-" in cli_tex.stdout)

cli_csv = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'naskah:manage', 'export', str(doc_id), '--format=csv'], capture_output=True, text=True)
test("CLI naskah:manage export csv Execution", cli_csv.returncode == 0 and "naskah-export-" in cli_csv.stdout)

tex_content = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'cat', f'/var/www/html/storage/app/naskah-export-{doc_id}.latex'], capture_output=True, text=True)
test("CLI naskah:manage LaTeX includes amsmath and amssymb", "amsmath,amssymb" in tex_content.stdout)
test("CLI naskah:manage LaTeX includes hyperref package", "usepackage{hyperref}" in tex_content.stdout)

cli_rtf = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'naskah:manage', 'export', str(doc_id), '--format=rtf'], capture_output=True, text=True)
test("CLI naskah:manage export rtf Execution", cli_rtf.returncode == 0 and "naskah-export-" in cli_rtf.stdout)

rtf_content = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'cat', f'/var/www/html/storage/app/naskah-export-{doc_id}.rtf'], capture_output=True, text=True)
test("CLI naskah:manage RTF includes valid rich text header", "{\\rtf1" in rtf_content.stdout)

cli_odt = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'naskah:manage', 'export', str(doc_id), '--format=odt'], capture_output=True, text=True)
test("CLI naskah:manage export odt Execution", cli_odt.returncode == 0 and "naskah-export-" in cli_odt.stdout)

cli_docx = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'naskah:manage', 'export', str(doc_id), '--format=docx'], capture_output=True, text=True)
test("CLI naskah:manage export docx Execution", cli_docx.returncode == 0 and "naskah-export-" in cli_docx.stdout)

docx_inspect = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'unzip', '-l', f'/var/www/html/storage/app/naskah-export-{doc_id}.docx'], capture_output=True, text=True)
test("CLI naskah:manage DOCX includes word/numbering.xml", "word/numbering.xml" in docx_inspect.stdout)

odt_inspect = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'unzip', '-p', f'/var/www/html/storage/app/naskah-export-{doc_id}.odt', 'content.xml'], capture_output=True, text=True)
test("CLI naskah:manage ODT includes valid OpenDocument content", "office:document-content" in odt_inspect.stdout)

assistant_test = subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'tinker', '--execute', r"""
$svc = app(\App\Domain\Assistant\XishieAssistantService::class);
$p = \App\Models\XishieProject::first();
$k = $svc->handle($p, '/kebaruan');
$m = $svc->handle($p, '/metodologi');
$fn = $svc->handle($p, '/catatan-kaki Rujukan Bukti Primer');
echo json_encode([
    'k_callout' => !empty($k['data']['callout_block']),
    'm_blocks' => !empty($m['data']['blocks']),
    'fn_block' => !empty($fn['data']['footnote_block']),
]);
"""], capture_output=True, text=True)
try:
    asst_json = json.loads(assistant_test.stdout.strip().splitlines()[-1])
    test("Assistant /kebaruan returns structured callout_block", asst_json.get("k_callout") is True)
    test("Assistant /metodologi returns structured blocks", asst_json.get("m_blocks") is True)
    test("Assistant /catatan-kaki returns structured footnote_block", asst_json.get("fn_block") is True)
except Exception as e:
    test("Assistant structured payloads verification", False, str(e))

# 3. Filament Admin Authentication & Xishie Assistant Page
print("\n--- 3. Filament Admin Authentication & Xishie Assistant Page ---")
# Authenticate session in Laravel database
setup_cmd = r"""
$sessId = '0bDz0VcTpzWmaZttxHOWzxsZH0NORwJT9quTvCRz';
$user = App\Models\User::first();
$guardName = 'login_web_' . sha1('Illuminate\Auth\SessionGuard');
$data = [
    '_token' => 'bb_test_token_xishie',
    $guardName => $user->id,
    '_previous' => ['url' => 'http://localhost:8208/admin'],
    '_flash' => ['old' => [], 'new' => []],
];
Illuminate\Support\Facades\DB::table('sessions')->where('id', $sessId)->update([
    'user_id' => $user->id,
    'payload' => base64_encode(json_encode($data)),
    'last_activity' => time()
]);
"""
subprocess.run(['docker', 'exec', 'xishie-php-fpm', 'php', 'artisan', 'tinker', f'--execute={setup_cmd}'], check=True)

auth_cookie = 'agen-xishie-session=eyJpdiI6IlErempGdkVwK2tMcUtrK2VRWnpxYnc9PSIsInZhbHVlIjoiRTJOSlN4cm05NjBmb253SGlUWXBibmRkazR5bHdPK2NNMVp4dWJTNDRYYzhWNitQWFhnY1RWYmxyNWFqU2lHWjY2MUIxWXFXNCtxMnRYTXB0UytUdHhJdGtiUlJ1SDZ2UW5Ma2xlWXp4WGU1ai9EYnlTN2toazNNajV5U2NFNnoiLCJtYWMiOiI5OWU1ZjUwMTgwMmI2NzJlMGU5OTU3NWEwZjJiODdmZDEyOWMwN2MwY2ZlNGJjY2MyMzQ3ZDc2NGRlYjk1OGEzIiwidGFnIjoiIn0%3D'
auth_headers = {
    'User-Agent': 'Mozilla/5.0',
    'Cookie': auth_cookie
}

status, assistant_html, _ = http_get("/admin/xishie-assistant-page", headers=auth_headers)
test("Visit /admin/xishie-assistant-page (Authenticated)", status == 200, f"status={status}")

# Verify Maximized Assistant Elements in HTML
test("Assistant Window Titlebar Present", "XISHIE ASSISTANT" in assistant_html)
test("Model Selector in Assistant Topbar", "MODEL AI ASSISTANT" in assistant_html)
test("Academic Persona Bar Present (PERSONA AKADEMIK)", "PERSONA AKADEMIK" in assistant_html)
test("Academic Persona: Akademisi Senior", "Akademisi Senior" in assistant_html)
test("Academic Persona: Reviewer Kritis", "Reviewer Kritis" in assistant_html)
test("Academic Persona: Proofreader PUEBI", "Proofreader PUEBI" in assistant_html)
test("Academic Persona: Metodolog Riset", "Metodolog Riset" in assistant_html)
test("Academic Persona: Spesialis Kebaruan", "Spesialis Kebaruan" in assistant_html)
test("Per-Message Regenerate Action Present", "regenerateMessage" in assistant_html or "Regenerate" in assistant_html)
test("Command Category: Semua Perintah", "Semua Perintah" in assistant_html)
test("Command Category: Struktur & Bab", "Struktur &amp; Bab" in assistant_html or "Struktur & Bab" in assistant_html)
test("Command Category: Sitasi & Literatur", "Sitasi &amp; Literatur" in assistant_html or "Sitasi & Literatur" in assistant_html)
test("Command Category: Audit & Validasi", "Audit &amp; Validasi" in assistant_html or "Audit & Validasi" in assistant_html)
test("Command Category: Gaya & Format", "Gaya &amp; Format" in assistant_html or "Gaya & Format" in assistant_html)
test("Academic Slash Commands Present (/abstrak, /pendahuluan, /sitasi, /kebaruan)",
     "/abstrak" in assistant_html and "/pendahuluan" in assistant_html and "/sitasi" in assistant_html and "/kebaruan" in assistant_html)
test("Assistant Academic Commands: Daftar Isi & Pustaka",
     "/daftar-isi" in assistant_html and "/daftar-pustaka" in assistant_html)
test("Command Search Input Field Present", "commandSearch" in assistant_html or "Cari..." in assistant_html)
test("HTML Transcript Export Option Present", "HTML" in assistant_html)
test("LaTeX Transcript Export Option Present", "LaTeX" in assistant_html)
test("CSV Transcript Export Option Present", "CSV" in assistant_html)
test("Markdown Transcript Export Option Present", "Markdown" in assistant_html or "exportTranscript('markdown')" in assistant_html or "MD" in assistant_html)
test("Assistant Focus Scope Selector Present", "FOKUS:" in assistant_html)
test("Assistant Focus Scope Options (project, manuscript, literature)",
     "setScope('project')" in assistant_html and "setScope('manuscript')" in assistant_html and "setScope('literature')" in assistant_html)
test("Assistant Session Telemetry Bar Present (SESI:)", "SESI:" in assistant_html)
test("Assistant Session Generated Words Telemetry (GENERASI:)", "GENERASI:" in assistant_html)
test("Assistant Transcript Search Field Present", "Cari transkrip..." in assistant_html)
test("Assistant Starred Filter Button Present", "toggleOnlyStarred" in assistant_html)
test("Assistant Stage-Aware Contextual Prompts Bar Present", "TAHAP " in assistant_html)
test("Assistant Snapshot Archiving Button Present", "Snapshot" in assistant_html)
test("Assistant Studio Export Action Present", "exportTranscriptToDocument" in assistant_html or "Buat Naskah Studio" in assistant_html)
test("Assistant Context Document Selector Present", "KONTEKS NASKAH" in assistant_html or "selectContextDocument" in assistant_html)
test("Assistant Academic Prompt Templates Bar Present", "KATALOG TEMPLAT PROMPT RISET" in assistant_html or "togglePromptTemplates" in assistant_html)
test("Assistant Academic Prompt Templates Actions Supported", "applyPromptTemplate" in assistant_html or "togglePromptTemplates" in assistant_html)
test("Assistant Reference Search Drawer Present", "PUSTAKA REFERENSI PROYEK" in assistant_html or "toggleReferenceDrawer" in assistant_html)
test("Assistant Reference Drawer Citation Insertion Action Present", "insertCitationKey" in assistant_html)
test("Assistant Session Summary Action Present", "generateSessionSummary" in assistant_html or "Rangkum Sesi" in assistant_html)
test("Assistant Prompt History Support Present", "promptHistory" in assistant_html)
test("Assistant Message Copy-to-Clipboard Action Present", "clipboard.writeText" in assistant_html or "Salin" in assistant_html)
test("Assistant Direct Execution Audit Sitasi Chip Present", "⚡ Audit Sitasi" in assistant_html)
test("Assistant Direct Execution Tesaurus Chip Present", "⚡ Tesaurus" in assistant_html)
test("Assistant Context Document Studio Quick Link Present", "Buka Studio" in assistant_html or "selectContextDocument" in assistant_html)
test("Direct Execution Quick Chips (⚡ Cek Kebaruan)", "⚡ Cek Kebaruan" in assistant_html)
test("Quick Prompt Suggestion Chips Present (PROMPT CEPAT)", "PROMPT CEPAT:" in assistant_html)
test("Studio Execute Button (⚡ Eksekusi ke Studio)", "Eksekusi ke Studio" in assistant_html)

# 4. Naskah Studio Filament Page & Ribbon UI
print("\n--- 4. Naskah Studio Ribbon & Studio App ---")
status, studio_html, _ = http_get("/admin/naskah-studio", headers=auth_headers)
test("Visit /admin/naskah-studio (Authenticated)", status == 200, f"status={status}")
test("Studio Ribbon UI Loaded", "ns-ribbon" in studio_html or "Naskah Studio" in studio_html)
test("Topbar Active Model Badge Present", "🤖" in studio_html)
test("Statusbar Page Count and Format Present", "ns-statusbar" in studio_html and "Halaman" in studio_html)
test("Ribbon Preset: Poles Cepat", "Poles Cepat" in studio_html)
test("Ribbon Preset: Kembangkan", "Kembangkan" in studio_html)
test("Ribbon Preset: Kebaruan", "Kebaruan" in studio_html)
test("Ribbon Preset: Abstrak", "Abstrak" in studio_html)
test("Ribbon Preset: Kesimpulan", "Kesimpulan" in studio_html)
test("Ribbon Preset: Metodologi", "Metodologi" in studio_html)
test("Ribbon Preset: Pembahasan", "Pembahasan" in studio_html)
test("Ribbon Preset: Pustaka", "Pustaka" in studio_html)
test("Ribbon Preset: Keterbacaan", "Keterbacaan" in studio_html)
test("Ribbon Button: Eksekusi Model AI", "Eksekusi Model" in studio_html)
test("Ribbon Navigation Multi-tab: Judul, Versi & Pustaka",
     "Judul" in studio_html and "Versi" in studio_html and "Pustaka" in studio_html)
test("Filament Studio: Navigator Scratchpad Tab Present", "Catatan" in studio_html)
test("Navigator Tab Switching: setNavSidebarTab", "setNavSidebarTab" in studio_html)
test("Filament Studio: Draw & Help Ribbon Tabs in Navigation",
     "Gambar" in studio_html and "Bantuan" in studio_html)
test("Filament Studio: Outline Heading Section Reordering & Hierarchy Controls",
     "moveSection" in studio_html and "promoteHeading" in studio_html)
test("Filament Studio: Zen Focus Mode Toggle & Statusbar Action",
     "toggleZenMode" in studio_html and "Mode Fokus" in studio_html)

status, studio_ref_html, _ = http_get("/admin/naskah-studio?tab=references", headers=auth_headers)
test("Filament Studio: References Ribbon Citation Audit Present",
     "auditCitations" in studio_ref_html)
test("Filament Studio: References Ribbon Table of Contents Action",
     "insertTableOfContents" in studio_ref_html or "Daftar Isi" in studio_ref_html)
test("Filament Studio: References Ribbon Renumber Footnotes Action",
     "renumberFootnotes" in studio_ref_html)
test("Filament Studio: References Ribbon Renumber Endnotes Action",
     "renumberEndnotes" in studio_ref_html)

status, studio_layout_auth_html, _ = http_get("/admin/naskah-studio?tab=layout", headers=auth_headers)
test("Filament Studio: Layout Ribbon Line Numbers Toggle Present",
     "toggleLineNumbers" in studio_layout_auth_html or "Nomor Baris" in studio_layout_auth_html)
test("Filament Studio: Layout Two-Column & Multi-Column Support",
     "columns" in studio_layout_auth_html or "Kolom" in studio_layout_auth_html)

status, studio_review_auth_html, _ = http_get("/admin/naskah-studio?tab=review", headers=auth_headers)
test("Filament Studio: Review Ribbon Citation Audit Present",
     "auditCitations" in studio_review_auth_html)

status, studio_view_auth_html, _ = http_get("/admin/naskah-studio?tab=view", headers=auth_headers)
test("Filament Studio: View Ribbon Line Numbers and Zen Mode Present",
     ("toggleLineNumbers" in studio_view_auth_html or "No. Baris" in studio_view_auth_html) and ("toggleZenMode" in studio_view_auth_html or "Mode Fokus" in studio_view_auth_html))
test("Filament Studio: Insert Citation Action Present in References Navigator", "insertReferenceCitation" in studio_html or "insertCitation" in studio_html)
test("Filament Studio: Home Ribbon Wordcraft AI Block Toolbar",
     "aiParaphraseBlock" in studio_html and "aiTranslateBlock" in studio_html and "aiFixTone" in studio_html)

# Test Studio with Copilot Drawer Opened via ?assistant=1
status, studio_drawer_html, _ = http_get("/admin/naskah-studio?assistant=1", headers=auth_headers)
test("Visit /admin/naskah-studio?assistant=1 (Copilot Drawer Open)", status == 200)
test("Docked Copilot Drawer: Category Pills (drawerCategories)", "setDrawerCommandCategory" in studio_drawer_html)
test("Docked Copilot Drawer: Search Input (drawerCommandSearch)", "drawerCommandSearch" in studio_drawer_html)
test("Docked Copilot Drawer: Message Deletion Action (deleteAssistantMessage)", "deleteAssistantMessage" in studio_drawer_html)

status, standalone_drawer_html, _ = http_get("/studio?assistant=1")
test("Standalone Studio: Copilot Drawer Opened (?assistant=1)", status == 200)
test("Standalone Studio: Copilot Drawer Message Deletion (deleteAssistantMessage)", "deleteAssistantMessage" in standalone_drawer_html)

# Test Standalone Studio Route (/studio)
status, studio_standalone_html, _ = http_get("/studio")
test("Visit Standalone /studio (Public Access)", status == 200, f"status={status}")
test("Standalone Studio: Multi-tab Navigator (Pustaka & Versi)", "Pustaka" in studio_standalone_html and "Versi" in studio_standalone_html)
test("Standalone Studio: Navigator Scratchpad Tab Present", "Catatan" in studio_standalone_html)
test("Standalone Studio: Outline Heading Section Reordering & Hierarchy Controls",
     "moveSection" in studio_standalone_html and "promoteHeading" in studio_standalone_html)
test("Standalone Studio: Zen Focus Mode Toggle Present", "toggleZenMode" in studio_standalone_html)

status, studio_file_html, _ = http_get("/studio?tab=file")
test("Standalone Studio: File Ribbon HTML & JSON Export Formats (?tab=file)", "HTML Web" in studio_file_html and "JSON Data" in studio_file_html)
test("Standalone Studio: File Ribbon BibTeX Export Format (?tab=file)", "BibTeX (.bib)" in studio_file_html)
test("Standalone Studio: File Ribbon CSV Export Format (?tab=file)", "CSV Data" in studio_file_html or "export('csv')" in studio_file_html)
test("Standalone Studio: File Ribbon Duplicate Action (?tab=file)", "duplicateDocument" in studio_file_html or "Duplicate" in studio_file_html)

status, studio_file_auth_html, _ = http_get("/admin/naskah-studio?tab=file", headers=auth_headers)
test("Filament Studio: File Ribbon BibTeX Export Format (?tab=file)", "BibTeX" in studio_file_auth_html)
test("Filament Studio: File Ribbon CSV Export Format (?tab=file)", "CSV" in studio_file_auth_html and "export('csv')" in studio_file_auth_html)

test("Standalone Studio: Ribbon Layout DIKTI Preset Present", "DIKTI 4-4-3-3" in studio_standalone_html)
test("Standalone Studio: Target Word Count Indicator in Statusbar", "Target Word Count" in studio_standalone_html)
test("Standalone Studio: Home Ribbon Case Transform Aa Dropdown", "transformActiveBlockCase" in studio_standalone_html or "Change Case" in studio_standalone_html)

status, studio_stats_html, _ = http_get("/studio?dialog=stats")
test("Standalone Studio: Target Word Count Supported in Stats Dialog (?dialog=stats)", "setTargetWordCount" in studio_stats_html)
test("Standalone Studio: Proofing Language Indicator in Statusbar", "Indonesia (PUEBI)" in studio_standalone_html)

status, studio_snap_html, _ = http_get("/studio?dialog=snapshots")
test("Standalone Studio: Snapshot Dialog Supported (?dialog=snapshots)", status == 200 and ("compareSnapshot" in studio_snap_html or "restoreSnapshot" in studio_snap_html or "Snapshot" in studio_snap_html))

status, studio_find_html, _ = http_get("/studio?dialog=find")
test("Standalone Studio: Find Dialog with Bidirectional Navigation (?dialog=find)", "findPrevious" in studio_find_html and "findNext" in studio_find_html)
test("Standalone Studio: Find Dialog Replace and Replace All Actions (?dialog=find)", "replaceCurrent" in studio_find_html and "replaceAll" in studio_find_html)
test("Standalone Studio: Find Dialog Match Case and Whole Word Options (?dialog=find)", "findMatchCase" in studio_find_html and "findWholeWord" in studio_find_html)

status, studio_thesaurus_html, _ = http_get("/studio?dialog=thesaurus")
test("Standalone Studio: Thesaurus Dialog Supported (?dialog=thesaurus)", "runThesaurus" in studio_thesaurus_html)

status, studio_find_auth_html, _ = http_get("/admin/naskah-studio?dialog=find", headers=auth_headers)
test("Filament Studio: Find Dialog with Bidirectional Navigation (?dialog=find)", "findPrevious" in studio_find_auth_html and "findNext" in studio_find_auth_html)
test("Filament Studio: Find Dialog Replace and Replace All Actions (?dialog=find)", "replaceCurrent" in studio_find_auth_html and "replaceAll" in studio_find_auth_html)
test("Filament Studio: Find Dialog Match Case and Whole Word Options (?dialog=find)", "findMatchCase" in studio_find_auth_html and "findWholeWord" in studio_find_auth_html)

status, studio_merge_auth_html, _ = http_get("/admin/naskah-studio?dialog=merge", headers=auth_headers)
test("Filament Studio: Mail Merge Dialog Supported (?dialog=merge)", status == 200 and "Mail Merge" in studio_merge_auth_html)

status, studio_snap_auth_html, _ = http_get("/admin/naskah-studio?dialog=snapshots", headers=auth_headers)
test("Filament Studio: Snapshots Dialog Supported (?dialog=snapshots)", status == 200 and "createSnapshot" in studio_snap_auth_html)

status, studio_thesaurus_auth_html, _ = http_get("/admin/naskah-studio?dialog=thesaurus", headers=auth_headers)
test("Filament Studio: Thesaurus Dialog Supported (?dialog=thesaurus)", status == 200 and "runThesaurus" in studio_thesaurus_auth_html)

status, studio_spelling_auth_html, _ = http_get("/admin/naskah-studio?dialog=spelling", headers=auth_headers)
test("Filament Studio: Spelling Proofing Dialog Supported (?dialog=spelling)", status == 200 and ("addCustomWordToDictionary" in studio_spelling_auth_html or "Kamus" in studio_spelling_auth_html or "Kamus ringkas" in studio_spelling_auth_html or "spelling" in studio_spelling_auth_html))

status, studio_merge_html, _ = http_get("/studio?dialog=merge")
test("Standalone Studio: Mail Merge Dialog Supported (?dialog=merge)", "insertMerge" in studio_merge_html or "Mail Merge" in studio_merge_html)

status, studio_draw_html, _ = http_get("/studio?tab=draw")
test("Standalone Studio: Draw Tab Sketch Callout Supported (?tab=draw)", "insertCallout('sketch')" in studio_draw_html or "sketch" in studio_draw_html or "Sketsa" in studio_draw_html)
test("Standalone Studio: Ribbon Table APA Format Present", "Format APA" in studio_standalone_html)
test("Standalone Studio: Home Ribbon Generasi AI Group", "Generasi AI" in studio_standalone_html and "Model AI" in studio_standalone_html)
test("Standalone Studio: Home Ribbon AI Presets (Poles, Kembangkan, Kebaruan, Abstrak)",
     "Poles" in studio_standalone_html and "Kembangkan" in studio_standalone_html and "Kebaruan" in studio_standalone_html and "Abstrak" in studio_standalone_html)
test("Standalone Studio: Home Ribbon Wordcraft AI Actions (Paraphrase & Translate)",
     "aiParaphraseBlock" in studio_standalone_html and "aiTranslateBlock" in studio_standalone_html)
test("Standalone Studio: Home Ribbon AI Presets (Metode, Bahas, Simpulan, Baca)",
     "Metode" in studio_standalone_html and "Bahas" in studio_standalone_html and "Simpulan" in studio_standalone_html and "Baca" in studio_standalone_html)

# Test Ribbon Tab Switching via ?tab=insert and ?tab=table
status, studio_insert_html, _ = http_get("/studio?tab=insert")
test("Standalone Studio: Ribbon AI Presets (?tab=insert)", "Matriks AI" in studio_insert_html and "Kebaruan AI" in studio_insert_html)
test("Standalone Studio: Ribbon Insert Cover Page Present (?tab=insert)", "insertCoverPage" in studio_insert_html or "Cover Page" in studio_insert_html)
test("Standalone Studio: Ribbon Insert Callout Options (?tab=insert)", "insertCallout" in studio_insert_html and ("Important" in studio_insert_html or "important" in studio_insert_html))

status, studio_layout_html, _ = http_get("/studio?tab=layout")
test("Standalone Studio: Ribbon Layout Watermark Present (?tab=layout)", "setWatermark" in studio_layout_html or "Watermark" in studio_layout_html)
test("Standalone Studio: Ribbon Layout Line Numbers Toggle Present (?tab=layout)", "toggleLineNumbers" in studio_layout_html or "Line Numbers" in studio_layout_html)

status, studio_table_html, _ = http_get("/studio?tab=table")
test("Standalone Studio: Table Calculation Formulas (?tab=table)", "Min" in studio_table_html and "Max" in studio_table_html and "Median" in studio_table_html)
test("Standalone Studio: Table StdDev Formula (?tab=table)", "stddev" in studio_table_html or "StdDev" in studio_table_html)
test("Standalone Studio: Table Styles (?tab=table)", "APA" in studio_table_html and "Grid" in studio_table_html and "Zebra" in studio_table_html)
test("Standalone Studio: Table Sort & Transpose Actions (?tab=table)", "tableSortColumn" in studio_table_html and "tableTranspose" in studio_table_html)
test("Standalone Studio: Table Alignment & Clear Data Actions (?tab=table)", "tableSetColumnAlign" in studio_table_html and "tableClearDataRows" in studio_table_html)

status, studio_table_auth_html, _ = http_get("/admin/naskah-studio?tab=table", headers=auth_headers)
test("Filament Studio: Table Calculation Formulas (?tab=table)", "tableCalculateColumn" in studio_table_auth_html and ("median" in studio_table_auth_html or "Median" in studio_table_auth_html))
test("Filament Studio: Table StdDev Formula (?tab=table)", "tableCalculateColumn" in studio_table_auth_html and ("stddev" in studio_table_auth_html or "StdDev" in studio_table_auth_html))
test("Filament Studio: Table Sort & Transpose Actions (?tab=table)", "tableSortColumn" in studio_table_auth_html and "tableTranspose" in studio_table_auth_html)
test("Filament Studio: Table Alignment & Clear Data Actions (?tab=table)", "tableSetColumnAlign" in studio_table_auth_html and "tableClearDataRows" in studio_table_auth_html)

test("Filament Studio: References Navigator Search Field", "referenceSearchQuery" in studio_table_auth_html or "referenceSearchQuery" in studio_html)

status, studio_crossref_auth_html, _ = http_get("/admin/naskah-studio?dialog=crossref", headers=auth_headers)
test("Filament Studio: Cross-Reference Dialog with Headings (?dialog=crossref)",
     "Rujukan Silang" in studio_crossref_auth_html and "Headings" in studio_crossref_auth_html)

status, standalone_crossref_html, _ = http_get("/studio?dialog=crossref")
test("Standalone Studio: Cross-Reference Dialog with Headings (?dialog=crossref)",
     "Cross-Reference" in standalone_crossref_html and "Headings" in standalone_crossref_html)

status, studio_endnote_html, _ = http_get("/studio?dialog=endnote")
test("Standalone Studio: Endnote Dialog Supported (?dialog=endnote)", status == 200 and ("Insert Endnote" in studio_endnote_html or "Catatan Akhir" in studio_endnote_html))

status, studio_fn_html, _ = http_get("/studio?dialog=footnote")
test("Standalone Studio: Footnote Dialog Supported (?dialog=footnote)",
     status == 200 and ("Insert Footnote" in studio_fn_html or "Footnote" in studio_fn_html))

status, filament_fn_html, _ = http_get("/admin/naskah-studio?dialog=footnote", headers=auth_headers)
test("Filament Studio: Footnote Dialog Supported (?dialog=footnote)",
     status == 200 and ("Catatan Kaki" in filament_fn_html or "Footnote" in filament_fn_html))

status, standalone_ref_html, _ = http_get("/studio?tab=references")
test("Standalone Studio: References Ribbon Renumber Footnotes Action",
     "renumberFootnotes" in standalone_ref_html)
test("Standalone Studio: References Ribbon Renumber Endnotes Action",
     "renumberEndnotes" in standalone_ref_html)
test("Standalone Studio: References Ribbon Vancouver Style Option",
     "Vancouver" in standalone_ref_html)

status, studio_merge_html, _ = http_get("/studio?dialog=merge")
test("Standalone Studio: Mail Merge Dialog Supported (?dialog=merge)", status == 200 and "Mail Merge" in studio_merge_html)
test("Standalone Studio: Mail Merge Dialog Presets & Options (?dialog=merge)",
     "setMergeTemplatePreset" in studio_merge_html and "mergeSeparatePages" in studio_merge_html)

status, filament_merge_html, _ = http_get("/admin/naskah-studio?dialog=merge", headers=auth_headers)
test("Filament Studio: Mail Merge Dialog Presets & Options (?dialog=merge)",
     "setMergeTemplatePreset" in filament_merge_html and "mergeSeparatePages" in filament_merge_html)

status, studio_mailings_html, _ = http_get("/studio?tab=mailings")
test("Standalone Studio: Mailings Ribbon Tab (?tab=mailings)",
     status == 200 and ("Start Merge" in studio_mailings_html or "Mail Merge" in studio_mailings_html))

status, filament_mailings_html, _ = http_get("/admin/naskah-studio?tab=mailings", headers=auth_headers)
test("Filament Studio: Mailings Ribbon Tab (?tab=mailings)",
     status == 200 and ("Mail Merge" in filament_mailings_html or "Pratinjau Merge" in filament_mailings_html))

status, studio_equation_html, _ = http_get("/studio?dialog=equation")
test("Standalone Studio: Equation Dialog Bayes and F1 Presets (?dialog=equation)",
     "setEquationPreset('bayes')" in studio_equation_html and "setEquationPreset('f1')" in studio_equation_html)

status, filament_equation_html, _ = http_get("/admin/naskah-studio?dialog=equation", headers=auth_headers)
test("Filament Studio: Equation Dialog Bayes and F1 Presets (?dialog=equation)",
     "setEquationPreset('bayes')" in filament_equation_html and "setEquationPreset('f1')" in filament_equation_html)

status, studio_link_html, _ = http_get("/studio?dialog=link")
test("Standalone Studio: Link Dialog Academic Presets (?dialog=link)",
     status == 200 and "setLinkPreset('doi')" in studio_link_html and "setLinkPreset('arxiv')" in studio_link_html)

status, filament_link_html, _ = http_get("/admin/naskah-studio?dialog=link", headers=auth_headers)
test("Filament Studio: Link Dialog Academic Presets (?dialog=link)",
     status == 200 and "setLinkPreset('doi')" in filament_link_html and "setLinkPreset('arxiv')" in filament_link_html)

status, studio_tautan_html, _ = http_get("/studio?dialog=tautan")
test("Standalone Studio: Link Dialog Alias (?dialog=tautan)",
     status == 200 and "setLinkPreset('doi')" in studio_tautan_html)

status, filament_tautan_html, _ = http_get("/admin/naskah-studio?dialog=tautan", headers=auth_headers)
test("Filament Studio: Link Dialog Alias (?dialog=tautan)",
     status == 200 and "setLinkPreset('doi')" in filament_tautan_html)

test("Standalone Studio: Home Ribbon Key Points Action", "aiKeyPointsBlock" in studio_standalone_html)
test("Filament Studio: Home Ribbon Key Points Action", "aiKeyPointsBlock" in studio_html)

status, studio_references_html, _ = http_get("/studio?tab=references")
test("Standalone Studio: References Ribbon Tab (?tab=references)", "Pustaka Riset" in studio_references_html and "Insert Citation" in studio_references_html)
test("Standalone Studio: References Ribbon Update Bibliography Present (?tab=references)", "updateBibliography" in studio_references_html or "Update Bib" in studio_references_html)
test("Standalone Studio: References Ribbon Citation Audit Present (?tab=references)", "auditCitations" in studio_references_html or "Audit Citations" in studio_references_html)
test("Standalone Studio: References Ribbon Insert SOTA Matrix Present (?tab=references)", "insertLiteratureMatrixTable" in studio_references_html or "SOTA Matrix" in studio_references_html)

status, filament_references_html, _ = http_get("/admin/naskah-studio?tab=references", headers=auth_headers)
test("Filament Studio: References Ribbon Matriks SOTA Present (?tab=references)", "insertLiteratureMatrixTable" in filament_references_html or "Matriks SOTA" in filament_references_html)
test("Filament Studio: References Ribbon Vancouver Style Option (?tab=references)", "Vancouver" in filament_references_html)

status, studio_review_html, _ = http_get("/studio?tab=review")
test("Standalone Studio: Review Ribbon Tab (?tab=review)", "Cek Pedoman" in studio_review_html and "Cek Gap" in studio_review_html and "Keterbacaan" in studio_review_html)
test("Standalone Studio: Review Ribbon Citation Audit (?tab=review)", "auditCitations" in studio_review_html or "Audit Sitasi" in studio_review_html)
test("Standalone Studio: Review Ribbon Comment Navigation & Resolve All (?tab=review)",
     "previousComment" in studio_review_html and "nextComment" in studio_review_html and "resolveAllComments" in studio_review_html)
test("Standalone Studio: Review Ribbon Revision Navigation (?tab=review)",
     "previousRevision" in studio_review_html and "nextRevision" in studio_review_html)

status, studio_comments_html, _ = http_get("/studio?tab=review&comments=1")
test("Standalone Studio: Review Comments Task Pane & Filter (?comments=1)",
     "setCommentFilter" in studio_comments_html)

status, filament_review_html, _ = http_get("/admin/naskah-studio?tab=review", headers=auth_headers)
test("Filament Studio: Review Ribbon Comment Navigation & Resolve All (?tab=review)",
     "previousComment" in filament_review_html and "nextComment" in filament_review_html and "resolveAllComments" in filament_review_html)
test("Filament Studio: Review Ribbon Revision Navigation (?tab=review)",
     "previousRevision" in filament_review_html and "nextRevision" in filament_review_html)

status, filament_comments_html, _ = http_get("/admin/naskah-studio?tab=review&comments=1", headers=auth_headers)
test("Filament Studio: Review Comments Task Pane & Filter (?comments=1)",
     "setCommentFilter" in filament_comments_html)

status, studio_view_html, _ = http_get("/studio?tab=view")
test("Standalone Studio: View Ribbon Tab Line Numbers & Zen Mode (?tab=view)", ("toggleLineNumbers" in studio_view_html or "Line Numbers" in studio_view_html) and ("toggleZenMode" in studio_view_html or "Zen" in studio_view_html))

status, studio_draw_html, _ = http_get("/studio?tab=draw")
test("Standalone Studio: Draw Ribbon Tab (?tab=draw)", status == 200 and ("Pena Biru" in studio_draw_html or "Eraser" in studio_draw_html or "Drawing Tools" in studio_draw_html or "Blue Pen" in studio_draw_html))
test("Standalone Studio: Draw Ribbon Green & Purple Pen (?tab=draw)",
     "Green Pen" in studio_draw_html and "Purple Pen" in studio_draw_html)

status, filament_draw_html, _ = http_get("/admin/naskah-studio?tab=draw", headers=auth_headers)
test("Filament Studio: Draw Ribbon Green & Purple Pen (?tab=draw)",
     "Pena Hijau" in filament_draw_html and "Pena Ungu" in filament_draw_html)

status, studio_help_html, _ = http_get("/studio?tab=help")
test("Standalone Studio: Help Ribbon Tab (?tab=help)", status == 200 and ("Shortcuts" in studio_help_html or "Commands" in studio_help_html or "Pintasan" in studio_help_html))
test("Standalone Studio: Help Ribbon Audit Presets (?tab=help)", "Cek Gap" in studio_help_html and "Kebaruan" in studio_help_html)

# 5. Omnigensi Platform Integration & Agnes 3.0 Flash Discovery
print("\n--- 5. Omnigensi Platform Integration & Agnes 3.0 Flash Handshake ---")
status, handshake_body, _ = http_get(f"{OMNIGENSI_URL}/api/v1/integrations/v1/handshake", headers={
    "X-API-Key": "omni_live_nzhbW53Z98gwZJprsGOF8E11zCnxqi4e"
})
handshake_data = json.loads(handshake_body)
test("Omnigensi Integration Handshake (Status Connected)",
     status == 200 and handshake_data.get("status") == "connected",
     f"status={handshake_data.get('status')}")

test("Target URL Bound to Xishie (http://localhost:8208/)",
     handshake_data.get("target_url") == "http://localhost:8208/",
     f"target_url={handshake_data.get('target_url')}")

avail_models = [m.get("model_id") for m in handshake_data.get("available_models", [])]
test("Agnes 3.0 Flash Present in Omnigensi Available Models",
     "hv/agnes-3.0-flash" in avail_models,
     "model=hv/agnes-3.0-flash")

# Verify default model flag on Agnes 3.0 Flash
agnes_meta = next((m for m in handshake_data.get("available_models", []) if m.get("model_id") == "hv/agnes-3.0-flash"), None)
test("Agnes 3.0 Flash Configured as Active Default Model",
     agnes_meta is not None and agnes_meta.get("is_default") is True,
     f"is_default={agnes_meta.get('is_default') if agnes_meta else False}")

print("\n=====================================================================")
print(f"BLACK-BOX TEST SUMMARY: {passed} PASSED, {failed} FAILED")
print("=====================================================================")

if failed > 0:
    sys.exit(1)
sys.exit(0)
