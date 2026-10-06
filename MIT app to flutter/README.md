# MIT App → Flutter (Resident-only) — CONNECT READY

## Status
- Flutter UI in `flutter_resident/` (mock mode, `useMock=true`)
- PHP bridge in root `/api/` (11 files, `php -l` clean) → same Firebase RTDB
- Children fixed to web format (`child_name` + `age`), profile expanded-inline, change-password added, passwords 8+letters+numbers to match web.

## What syncs realtime (polling V1)
- Admin creates survey on web → phone Survey list shows it on open / pull-to-refresh
- Profile edit web↔phone → same `residents/{id}` node, `RES-0001` immutable
- Password change phone → works on web (bcrypt server-side)
- Survey submit phone → appears in web `admin/survey_results.php`, notifies admins

## Your 3 tasks for APK
1. Host this project (including `/api/` + `config/firebase_credentials.json`) on public HTTPS.
   Test in browser: `https://yourhost/Group-5-Enhance/api/health.php` → `{"ok":true,...}`
2. In `flutter_resident/lib/config.dart` set:
   `useMock = false`, `apiBaseUrl = 'https://yourhost/Group-5-Enhance/api'`
3. Build + install:
```
cd "MIT app to flutter/flutter_resident"
flutter pub get
flutter test
flutter build apk --release
```
Install `build/app/outputs/flutter-apk/app-release.apk` on phone.
Test: login `RES-0001` → Surveys pull-to-refresh → Take → check web results.

## Files
- `flutter_resident/lib/models/resident.dart` — Child `{child_name, age}`
- `flutter_resident/lib/screens/profile_screen.dart` — main info + View Full Information expander + children
- `flutter_resident/lib/screens/change_password_screen.dart` — new
- `api/*.php` — login, register, dashboard, surveys, survey_detail, submit, profile_get, profile_update, change_password, health
