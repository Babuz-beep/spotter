#!/usr/bin/env python3
"""
SPOTTER — Drive auto-upload test script
Fetches official OCR H432 past papers by direct URL, uploads each into a
target Google Drive folder via a service account.

Run this on the Hetzner server (has working outbound access to Google's APIs,
same as chop.php already does). Requires:
    pip3 install google-auth google-api-python-client requests --break-system-packages

Usage:
    python3 drive_upload_test.py

Before running, edit the two CONFIG values below.
"""

import io
import requests
from google.oauth2.credentials import Credentials
from google.auth.transport.requests import Request
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseUpload

# ── CONFIG — edit these ──────────────────────────────────────────────────────
CLIENT_ID = '601070815539-5kmq4mlc6t9utopd21kmi0b3ulo8gfvp.apps.googleusercontent.com'
CLIENT_SECRET = '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'
REFRESH_TOKEN_FILE = 'refresh_token.txt'         # created by get_refresh_token.py
FOLDER_ID = '1yOxiVKjn5hS83icOiR9ztHFz4rAwyKNY'  # the shared test folder's ID

SCOPES = ['https://www.googleapis.com/auth/drive.file']

# ── The 48 official OCR H432 files (24 QP + 24 MS), direct from ocr.org.uk ──
# ── Test structure: GCSE Science > Combined / Triple, matching the real AQA layout ──
# Each entry: (folder_path_as_list, filename, url)
# NOTE: URLs below are placeholders (marked TODO) - real AQA GCSE URLs need collecting
# before this actually fetches real papers. The folder STRUCTURE is what's being tested now.
FILES = [
    # GCSE Combined Science - 6 papers (Biology 1&2, Chemistry 1&2, Physics 1&2)
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Biology_Paper1_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Biology_Paper1_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Biology_Paper2_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Biology_Paper2_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Chemistry_Paper1_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Chemistry_Paper1_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Chemistry_Paper2_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Chemistry_Paper2_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Physics_Paper1_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Physics_Paper1_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Physics_Paper2_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Combined Science'], 'Combined_Physics_Paper2_MS.pdf', 'TODO_URL'),

    # GCSE Triple Science - separate Biology / Chemistry / Physics subfolders, 2 papers each
    (['GCSE Science', 'GCSE Triple Science', 'Biology'], 'Biology_Paper1_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Biology'], 'Biology_Paper1_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Biology'], 'Biology_Paper2_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Biology'], 'Biology_Paper2_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Chemistry'], 'Chemistry_Paper1_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Chemistry'], 'Chemistry_Paper1_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Chemistry'], 'Chemistry_Paper2_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Chemistry'], 'Chemistry_Paper2_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Physics'], 'Physics_Paper1_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Physics'], 'Physics_Paper1_MS.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Physics'], 'Physics_Paper2_QP.pdf', 'TODO_URL'),
    (['GCSE Science', 'GCSE Triple Science', 'Physics'], 'Physics_Paper2_MS.pdf', 'TODO_URL'),
]

def get_or_create_folder_path(drive, root_id, path_parts, cache):
    """Walk/create a nested folder path (list of folder names), returning the final folder's ID.
    Caches each level so repeated calls for shared parent paths don't recreate folders."""
    cache_key = tuple(path_parts)
    if cache_key in cache:
        return cache[cache_key]

    parent_id = root_id
    built_so_far = []
    for name in path_parts:
        built_so_far.append(name)
        step_key = tuple(built_so_far)
        if step_key in cache:
            parent_id = cache[step_key]
            continue
        query = (f"'{parent_id}' in parents and name = '{name}' "
                 f"and mimeType = 'application/vnd.google-apps.folder' and trashed = false")
        results = drive.files().list(q=query, fields='files(id, name)').execute()
        existing = results.get('files', [])
        if existing:
            folder_id = existing[0]['id']
        else:
            folder_metadata = {'name': name, 'mimeType': 'application/vnd.google-apps.folder', 'parents': [parent_id]}
            folder = drive.files().create(body=folder_metadata, fields='id').execute()
            folder_id = folder.get('id')
        cache[step_key] = folder_id
        parent_id = folder_id

    return parent_id

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

    # one folder ID cache per full path, created once and reused across all files
    folder_cache = {}

    print(f"Building folder structure and uploading {len(FILES)} files...\n")
    successes, failures, skipped = 0, [], 0

    for folder_path, filename, url in FILES:
        if url == 'TODO_URL':
            # still creates/confirms the folder exists, just doesn't fetch a real file yet
            get_or_create_folder_path(drive, FOLDER_ID, folder_path, folder_cache)
            print(f"  SKIP {'/'.join(folder_path)}/{filename}  (no real URL yet)")
            skipped += 1
            continue
        try:
            target_folder = get_or_create_folder_path(drive, FOLDER_ID, folder_path, folder_cache)

            resp = requests.get(url, timeout=30)
            resp.raise_for_status()
            if len(resp.content) < 1000:
                raise ValueError(f"Response too small ({len(resp.content)} bytes) - likely not a real PDF")

            media = MediaIoBaseUpload(io.BytesIO(resp.content), mimetype='application/pdf', resumable=False)
            file_metadata = {'name': filename, 'parents': [target_folder]}
            uploaded = drive.files().create(body=file_metadata, media_body=media, fields='id').execute()

            print(f"  OK   {'/'.join(folder_path)}/{filename}  ->  {uploaded.get('id')}")
            successes += 1
        except Exception as e:
            print(f"  FAIL {'/'.join(folder_path)}/{filename}  ->  {e}")
            failures.append((filename, str(e)))

    print(f"\n{successes}/{len(FILES)} uploaded, {skipped} skipped (no URL yet).")
    if failures:
        print(f"\n{len(failures)} failures:")
        for fn, err in failures:
            print(f"  - {fn}: {err}")

if __name__ == '__main__':
    main()
