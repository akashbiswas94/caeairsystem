# Dashboard UI update

This build keeps the existing Unreal/API/session functionality and refreshes the two main dashboards for the CAE demonstration.

## Updated
- `admin/dashboard.php` — instructor control dashboard with compact simulator controls, live sessions and session history.
- `trainee/dashboard.php` — live telemetry, events, charts and session history in a cleaner trainee view.
- `assets/css/style.css` — shared enterprise-style UI.
- Admin session history now includes **Session avg**, calculated from available Alignment Accuracy, Position Stability, Approach Accuracy and Safety Score values. If those values are not available, the existing Overall Score is used as the display fallback.

## Demo trainees
Import `demo_trainees.sql` once if you want the extra presentation accounts:
- Tanmay Singh — tanmay.trainee@test.com
- Swapnil Bonde — swapnil.trainee@test.com
- Francisco — francisco.trainee@test.com

Demo password: `123456`

## Install
Replace the current project files with this build, then hard-refresh the browser (`Ctrl + F5`). No database migration is required for the UI changes.
