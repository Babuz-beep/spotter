#!/usr/bin/env python3
"""
SPOTTER — Upload the 4 missing C2H papers (Jun19 + Jun25) to the C2H Drive folder
Same proven approach as drive_upload_test.py — reuses your existing refresh_token.txt.

Run this on the Hetzner server, in the same folder as your existing
drive_upload_test.py / refresh_token.txt.
"""

import io
import requests
from google.oauth2.credentials import Credentials
from google.auth.transport.requests import Request
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseUpload

# ── CONFIG ────────────────────────────────────────────────────────────────
CLIENT_ID = '601070815539-5kmq4mlc6t9utopd21kmi0b3ulo8gfvp.apps.googleusercontent.com'
CLIENT_SECRET = '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'  # same as before — worth rotating when you get a chance, same as the service account key
REFRESH_TOKEN_FILE = 'refresh_token.txt'          # reuses the one you already created
FOLDER_ID = '1gAVyHmf38Kf5oIEjJxyu3ExtAATeFiG7'   # C2H Drive folder — SPOTTER_GUIDANCE.txt

SCOPES = ['https://www.googleapis.com/auth/drive.file']

# ── The 4 confirmed, independently-verified files ──────────────────────────
FILES = [
    ("AQA-8464C2H-QP-JUN19.PDF", "https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464C2H-QP-JUN19_PDF/644ba32bba82ff1d62d7550ec11700aee49eada4.pdf"),
    ("AQA-8464C2H-W-MS-JUN19.PDF", "https://www.aqa.org.uk/files/sample-papers-and-mark-schemes.2019.june.AQA-8464C2H-W-MS-JUN19_PDF/9a5d70c9119c00b7d3e4322d2a59105f8e955021.pdf"),
    ("AQA-8464C2H-QP-JUN25.PDF", "https://www.aqa.org.uk/files/cRIpdimsSKiybuSFvPvj7O/9555e568f9626fb73491b7a1d9abf678d95f70bc.pdf"),
    ("AQA-8464C2H-MS-JUN25.PDF", "https://www.aqa.org.uk/files/2vT3LmcUzNO62qpl9IxmTz/aab375dfa2e8e3223a154b55d53f28426412edd1.pdf"),
]

def main():
    with open(REFRESH_TOKEN_FILE) as f:
        refresh_token = f.read().strip()

    creds = Credentials(
        token=None,
        refresh_token=refresh_token,
        token_uri='https://oauth2.googleapis.com/token',
        client_id=CLIENT_ID,
        client_secret=CLIENT_SECRET,
        scopes=SCOPES,
    )
    creds.refresh(Request())
    drive = build('drive', 'v3', credentials=creds)

    print(f"Uploading {len(FILES)} files to C2H folder {FOLDER_ID}...\n")
    successes, failures = 0, []

    for filename, url in FILES:
        try:
            resp = requests.get(url, timeout=30)
            resp.raise_for_status()
            if len(resp.content) < 1000:
                raise ValueError(f"Response too small ({len(resp.content)} bytes) - likely not a real PDF")

            media = MediaIoBaseUpload(io.BytesIO(resp.content), mimetype='application/pdf', resumable=False)
            file_metadata = {'name': filename, 'parents': [FOLDER_ID]}
            uploaded = drive.files().create(body=file_metadata, media_body=media, fields='id').execute()

            print(f"  OK   {filename}  ({len(resp.content):,} bytes)  ->  {uploaded.get('id')}")
            successes += 1
        except Exception as e:
            print(f"  FAIL {filename}  ->  {e}")
            failures.append((filename, str(e)))

    print(f"\n{successes}/{len(FILES)} uploaded successfully.")
    if failures:
        print(f"\n{len(failures)} failures:")
        for fn, err in failures:
            print(f"  - {fn}: {err}")

if __name__ == '__main__':
    main()
