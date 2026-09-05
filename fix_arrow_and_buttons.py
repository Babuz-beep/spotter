"""
Run this ON THE SERVER via PuTTY, from inside /var/www/html/qspotter:

    cd /var/www/html/qspotter
    python3 fix_arrow_and_buttons.py

Fixes applied:
1. Replaces the hand-emoji arrow (renders inconsistently across OS/browser
   fonts) with a clean CSS-drawn triangle, on EVERY page in this folder.
2. On h43201.html specifically: replaces the single generic "open" button
   with separate "Full Paper" / "Full MS" buttons, matching the c1h standard
   (every other page already has these; h43201 was the one exception).

Safe to run more than once - if a pattern isn't found (already fixed), it
just prints a note and moves on rather than breaking anything.
"""
import glob

# ============================================================
# FIX 1: Replace the hand-emoji arrow with a clean CSS triangle,
# on every page. The emoji renders inconsistently across
# OS/browser fonts - a CSS-drawn triangle looks identical everywhere.
# ============================================================
OLD_ARROW_CSS = "#arrow-indicator::after { content: '👉'; font-size: 14px; line-height: 1; }"
NEW_ARROW_CSS = "#arrow-indicator::after { content: ''; display: block; width: 0; height: 0; border-top: 6px solid transparent; border-bottom: 6px solid transparent; border-left: 9px solid #fff; }"

pages = glob.glob('*.html')
arrow_fixed = []
for page in pages:
    with open(page, encoding='utf-8') as f:
        c = f.read()
    if OLD_ARROW_CSS in c:
        c = c.replace(OLD_ARROW_CSS, NEW_ARROW_CSS)
        with open(page, 'w', encoding='utf-8') as f:
            f.write(c)
        arrow_fixed.append(page)

print("Arrow fixed on:", arrow_fixed)
already_clean = [p for p in pages if p not in arrow_fixed]
print("Already clean / pattern not found on:", already_clean)

# ============================================================
# FIX 2: h43201.html specifically - replace the single generic
# "open" button with separate "Full Paper" / "Full MS" buttons,
# matching the c1h standard.
# ============================================================
page = 'h43201.html'
with open(page, encoding='utf-8') as f:
    c = f.read()

old_button_html = '<button class="pctrl" id="btn-open">&#8599;</button>'
new_button_html = ('<button class="pctrl pctrl-red" id="btn-open-qp" title="Open full question paper (Google Drive)">Full Paper</button>\n'
                    '        <button class="pctrl pctrl-green" id="btn-open-ms" title="Open full mark scheme (Google Drive)">Full MS</button>')

if old_button_html in c:
    c = c.replace(old_button_html, new_button_html)
    print("Button HTML replaced")
else:
    print("NOTE: button HTML pattern not found (already fixed, or different than expected)")

old_js_decl = "const btnOpen    = document.getElementById('btn-open');"
new_js_decl = ("const btnOpenQP  = document.getElementById('btn-open-qp');\n"
               "const btnOpenMS  = document.getElementById('btn-open-ms');")

if old_js_decl in c:
    c = c.replace(old_js_decl, new_js_decl)
    print("JS declaration replaced")
else:
    print("NOTE: JS declaration pattern not found (already fixed, or different than expected)")

old_js_handler = "btnOpen.onclick = () => window.open(fullUrl, '_blank');"
new_js_handler = ("btnOpenQP.onclick = () => window.open('https://drive.google.com/file/d/' + row.dataset.qpFullId + '/view', '_blank');\n"
                  "  btnOpenMS.onclick = () => window.open('https://drive.google.com/file/d/' + row.dataset.msFullId + '/view', '_blank');")

if old_js_handler in c:
    c = c.replace(old_js_handler, new_js_handler)
    print("JS handler replaced")
else:
    print("NOTE: JS handler pattern not found (already fixed, or different than expected)")

with open(page, 'w', encoding='utf-8') as f:
    f.write(c)

# ============================================================
# Verification - div balance check on every page touched
# ============================================================
print()
print("=== Verification (div balance - should all say OK) ===")
for page in pages:
    with open(page, encoding='utf-8') as f:
        c = f.read()
    open_div = c.count('<div')
    close_div = c.count('</div>')
    status = "OK" if open_div == close_div else f"MISMATCH ({open_div} vs {close_div}) - CHECK THIS FILE"
    print(f"{page}: {status}")
