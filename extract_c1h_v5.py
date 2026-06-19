"""
C1H Coordinate Extractor v5 — SPOTTER
Handles single-span and split-span AQA question number formats.
"""
import sys, json, re, base64, fitz

def extract_y_map(doc, is_ms=False):
    results = {}
    start_page = 6 if is_ms else 0

    for page_idx in range(start_page, len(doc)):
        page = doc[page_idx]
        page_height = page.rect.height
        page_num = page_idx + 1

        spans = []
        for block in page.get_text("dict")["blocks"]:
            for line in block.get("lines", []):
                for span in line.get("spans", []):
                    txt = span["text"]
                    bbox = span["bbox"]
                    y_top = bbox[1]
                    y_mid = (bbox[1] + bbox[3]) / 2
                    if y_top < 55 or y_top > page_height - 55:
                        continue
                    spans.append({
                        "text": txt,
                        "y_top": y_top,
                        "y_mid": y_mid,
                        "x": bbox[0],
                    })

        spans.sort(key=lambda s: (s["y_top"], s["x"]))

        for j, span in enumerate(spans):
            txt = span["text"]
            if re.search(r"[a-zA-Z()\[\]]", txt):
                continue
            stripped = txt.replace(" ", "")
            if not stripped or not re.match(r"^[\d.]+$", stripped):
                continue

            # --- Check if this span begins a question number ---
            # Case A: full sub-question in one span e.g. "0 1 . 1" -> "01.1"
            m_full = re.match(r"^(0[1-9]|10)(\.[1-9])$", stripped)
            if m_full:
                qnum = m_full.group(1) + m_full.group(2)
                if qnum not in results:
                    results[qnum] = {"page": page_num, "y_top": round(span["y_top"],1),
                                     "y_mid": round(span["y_mid"],1), "page_height": round(page_height,1)}
                continue

            # Case B: major only in span e.g. "0 1" -> check next span for ". N"
            m_major = re.match(r"^(0[1-9]|10)$", stripped)
            if m_major:
                # Must have internal space to be an AQA question box (not a page number)
                if " " not in txt.strip():
                    continue
                major = m_major.group(1)
                # Look ahead for sub-question dot span on same line
                sub = None
                for k in range(j+1, min(j+5, len(spans))):
                    ns = spans[k]
                    if abs(ns["y_top"] - span["y_top"]) > 8:
                        break
                    ns_stripped = ns["text"].replace(" ", "")
                    if re.match(r"^\.[1-9]$", ns_stripped):
                        sub = ns_stripped
                        break
                    # If we hit letters/brackets, no sub follows
                    if re.search(r"[a-zA-Z()\[\]]", ns["text"]):
                        break

                qnum = major + (sub or "")
                if qnum not in results:
                    results[qnum] = {"page": page_num, "y_top": round(span["y_top"],1),
                                     "y_mid": round(span["y_mid"],1), "page_height": round(page_height,1)}
                continue

            # Case C: window fallback for other formats (e.g. "02.3" printed as plain text in MS)
            win_text = "".join(
                s["text"] for s in spans[j: j + 6]
                if abs(s["y_top"] - span["y_top"]) < 8
            ).replace(" ", "")
            m3 = re.match(r"^(0[1-9]|10)(\.[1-9])?(?!\d)", win_text)
            if not m3:
                continue
            qnum = m3.group(1) + (m3.group(2) or "")
            if "." not in qnum and " " not in txt.strip():
                continue
            if qnum not in results:
                results[qnum] = {"page": page_num, "y_top": round(span["y_top"],1),
                                 "y_mid": round(span["y_mid"],1), "page_height": round(page_height,1)}

    return results


def main():
    json_file = sys.argv[1]
    is_ms = sys.argv[2] == "1"
    label = sys.argv[3]

    with open(json_file) as f:
        data = json.load(f)
    parsed = json.loads(data[0]["text"])
    b64 = parsed.get("content", "")
    pdf_bytes = base64.b64decode(b64)
    doc = fitz.open(stream=pdf_bytes, filetype="pdf")

    print(f"  {label}: {len(doc)} pages", file=sys.stderr)
    results = extract_y_map(doc, is_ms=is_ms)

    def sort_key(k):
        parts = k.split(".")
        return (int(parts[0]), int(parts[1]) if len(parts) > 1 else 0)

    output = {
        "label": label,
        "is_ms": is_ms,
        "pages": len(doc),
        "coords": {k: results[k] for k in sorted(results.keys(), key=sort_key)}
    }
    print(json.dumps(output, indent=2))

if __name__ == "__main__":
    main()
