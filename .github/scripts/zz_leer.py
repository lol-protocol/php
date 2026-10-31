import json, re, subprocess, sys, urllib.request, html, ssl, tempfile, os
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
items = json.load(open(".github/scripts/zz_urls.json"))
def get(url):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/124 Safari/537.36", "Accept": "*/*"})
    with urllib.request.urlopen(req, timeout=60, context=ctx) as r:
        return r.read(), r.headers.get("content-type", ""), r.geturl()
def text(data, ctype, url):
    if data[:4] == b"%PDF" or url.lower().endswith(".pdf"):
        with tempfile.NamedTemporaryFile(suffix=".pdf", delete=False) as f: f.write(data)
        out = subprocess.run(["pdftotext", "-layout", f.name, "-"], capture_output=True, text=True).stdout
        os.unlink(f.name); return out
    s = data.decode("utf-8", "ignore")
    s = re.sub(r"(?is)<(script|style).*?</\1>", " ", s); s = re.sub(r"(?s)<[^>]+>", "\n", s)
    return re.sub(r"\n\s*\n+", "\n", html.unescape(s))
for it in items:
    url, kws, ctxn = it["url"], it.get("kw", []), it.get("ctx", 2)
    print(f"\n===== {it.get('id','')} {url}", flush=True)
    try:
        data, ctype, final = get(url)
        t = text(data, ctype, final)
    except Exception as e:
        print("  ERROR", repr(e)[:200]); continue
    lines = [l.strip() for l in t.splitlines() if l.strip()]
    print(f"  ok: {len(data)} bytes, {len(lines)} lines, final={final}")
    if it.get("head"): print("\n".join("  | " + l[:220] for l in lines[:it["head"]]))
    if kws:
        rx = re.compile("|".join(kws), re.I); shown = 0; last = -99
        for i, l in enumerate(lines):
            if rx.search(l) and i > last + ctxn and shown < it.get("max", 14):
                seg = lines[max(0, i - 1): i + ctxn + 1]
                print("  --- l.%d" % i); print("\n".join("  | " + s[:300] for s in seg)); shown += 1; last = i
