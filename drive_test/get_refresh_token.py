#!/usr/bin/env python3
"""
SPOTTER — one-time OAuth authorization (device flow)
Run this ONCE. It gives you a short code to enter at google.com/device on
ANY browser (your own computer, phone, whatever) - no long URL, no redirect
back to the server needed. Correct approach for a headless server.

Requires: pip3 install requests --break-system-packages

Usage:
    python3 get_refresh_token.py
Then follow the printed instructions.
"""

import requests
import time

# ── CONFIG — paste your OAuth client details here ───────────────────────────
CLIENT_ID = '601070815539-5kmq4mlc6t9utopd21kmi0b3ulo8gfvp.apps.googleusercontent.com'
CLIENT_SECRET = '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'

SCOPES = 'https://www.googleapis.com/auth/drive.file'

def main():
    # Step 1: request a device code
    resp = requests.post('https://oauth2.googleapis.com/device/code', data={
        'client_id': CLIENT_ID,
        'scope': SCOPES,
    })
    if resp.status_code != 200:
        print("Error response from Google:")
        print(resp.text)
    resp.raise_for_status()
    data = resp.json()

    print("\n" + "="*60)
    print(f"1. On ANY browser (phone, your computer, doesn't matter),")
    print(f"   go to:  {data['verification_url']}")
    print(f"2. Enter this code when asked:\n")
    print(f"      {data['user_code']}")
    print("="*60)
    print("\n3. Log in with your personal Google account and click Allow.")
    print("Waiting for you to complete this (checking every few seconds)...\n")

    device_code = data['device_code']
    interval = data.get('interval', 5)
    expires_in = data.get('expires_in', 1800)
    elapsed = 0

    while elapsed < expires_in:
        time.sleep(interval)
        elapsed += interval
        token_resp = requests.post('https://oauth2.googleapis.com/token', data={
            'client_id': CLIENT_ID,
            'client_secret': CLIENT_SECRET,
            'device_code': device_code,
            'grant_type': 'urn:ietf:params:oauth:grant-type:device_code',
        })
        token_data = token_resp.json()

        if 'refresh_token' in token_data:
            with open('refresh_token.txt', 'w') as f:
                f.write(token_data['refresh_token'])
            print("Authorized! Saved to refresh_token.txt — this step is now done permanently.")
            print("You can now run drive_upload_test.py normally.")
            return
        elif token_data.get('error') == 'authorization_pending':
            continue  # user hasn't finished yet, keep waiting
        elif token_data.get('error') == 'slow_down':
            interval += 5
            continue
        else:
            print("Error:", token_data)
            return

    print("Timed out waiting for authorization. Run the script again.")

if __name__ == '__main__':
    main()
