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

def http_get(path, headers=None):
    url = f"{BASE_URL}{path}" if path.startswith("/") else path
    req = urllib.request.Request(url, headers=headers or {"User-Agent": "Mozilla/5.0"})
    try:
        with urllib.request.urlopen(req, timeout=10) as res:
            return res.status, res.read().decode("utf-8", errors="replace"), res.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", errors="replace"), e.headers

def http_post(path, data, headers=None):
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
        with urllib.request.urlopen(req, timeout=15) as res:
            return res.status, res.read().decode("utf-8", errors="replace"), res.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", errors="replace"), e.headers

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
test("Command Category: Semua Perintah", "Semua Perintah" in assistant_html)
test("Command Category: Struktur & Bab", "Struktur &amp; Bab" in assistant_html or "Struktur & Bab" in assistant_html)
test("Command Category: Sitasi & Literatur", "Sitasi &amp; Literatur" in assistant_html or "Sitasi & Literatur" in assistant_html)
test("Command Category: Audit & Validasi", "Audit &amp; Validasi" in assistant_html or "Audit & Validasi" in assistant_html)
test("Command Category: Gaya & Format", "Gaya &amp; Format" in assistant_html or "Gaya & Format" in assistant_html)
test("Academic Slash Commands Present (/abstrak, /pendahuluan, /sitasi, /kebaruan)",
     "/abstrak" in assistant_html and "/pendahuluan" in assistant_html and "/sitasi" in assistant_html and "/kebaruan" in assistant_html)
test("Command Search Input Field Present", "commandSearch" in assistant_html or "Cari..." in assistant_html)
test("HTML Transcript Export Option Present", "HTML" in assistant_html)
test("Quick Prompt Suggestion Chips Present (PROMPT CEPAT)", "PROMPT CEPAT:" in assistant_html)
test("Studio Execute Button (⚡ Eksekusi ke Studio)", "Eksekusi ke Studio" in assistant_html)

# 4. Naskah Studio Filament Page & Ribbon UI
print("\n--- 4. Naskah Studio Ribbon & Studio App ---")
status, studio_html, _ = http_get("/admin/naskah-studio", headers=auth_headers)
test("Visit /admin/naskah-studio (Authenticated)", status == 200, f"status={status}")
test("Studio Ribbon UI Loaded", "ns-ribbon" in studio_html or "Naskah Studio" in studio_html)
test("Ribbon Preset: Poles Cepat", "Poles Cepat" in studio_html)
test("Ribbon Preset: Kembangkan", "Kembangkan" in studio_html)
test("Ribbon Preset: Kebaruan", "Kebaruan" in studio_html)
test("Ribbon Preset: Abstrak", "Abstrak" in studio_html)
test("Ribbon Preset: Kesimpulan", "Kesimpulan" in studio_html)
test("Ribbon Button: Eksekusi Model AI", "Eksekusi Model" in studio_html)

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
