# AAR Dashboard Simulator Launch + Admin View

This patch adds two features to the working AAR project:

1. **Start Main_World from the trainee or admin dashboard**
2. **Administrator / instructor dashboard** for all trainees and sessions

## Files included

- `trainee/dashboard.php` — existing live dashboard plus **Start Main_World** button
- `api/start-game.php` — local-only Windows launcher
- `api/common.php` — uses `NULL` for In Progress and gives dashboard launches 180 seconds to attach to Unreal `game_started`
- `api/telemetry.php` — uses `Review` instead of invalid `completed`
- `api/end-session.php` — closes sessions as `Review`
- `config/game.php` — Unreal/project path configuration
- `admin/dashboard.php` — admin overview and launch controls
- `admin/session.php` — AAR review page with score/result update
- `login.php` — redirects trainee accounts to trainee dashboard and non-trainee accounts to admin dashboard
- `admin_setup.sql` — safe role-check/promote examples

## Install

Back up `C:\xampp\htdocs\aar-training` first.

Copy the patch files into the matching folders under:

`C:\xampp\htdocs\aar-training`

Allow Windows to replace the matching PHP files.

## Configure the Unreal project path

Open:

`C:\xampp\htdocs\aar-training\config\game.php`

The patch already checks these likely paths:

- `C:\Program Files\Epic Games\UE_5.6\Engine\Binaries\Win64\UnrealEditor.exe`
- `D:\QUT\IFN636\CAE_Project\Development\projectboomboom\UE5 5.6 5.6\projectboomboom.uproject`
- the older `UE5 5.6` project folder as a fallback

If your installation is elsewhere, edit the candidate arrays.

## Important: launch through localhost

Open the dashboard as:

`http://localhost/aar-training/...`

`api/start-game.php` intentionally refuses remote launch requests. It can start desktop software on the local Windows PC, so it should not be exposed as an internet-accessible launcher.

Also start Apache from the **XAMPP Control Panel in your logged-in Windows session**. If Apache is running as a Windows service under another account/session, Windows may not display the Unreal window.

## Admin account

Run `admin_setup.sql` in phpMyAdmin and inspect the existing `users.role` column.

The admin pages allow any signed-in role other than `trainee`, so both `admin` and `instructor` work if your database supports either value.

After login:

- trainee -> `trainee/dashboard.php`
- admin/instructor -> `admin/dashboard.php`

## How dashboard launch works

When **Start Main_World** is clicked:

1. PHP verifies the request is from localhost.
2. It creates an `In Progress` training session (`status = NULL`) for the selected trainee.
3. Windows launches Unreal Engine 5.6 with `Main_World` in `-game` mode.
4. Unreal sends `game_started` and flight telemetry to the existing telemetry API.
5. `api/common.php` attaches that traffic to the pre-created dashboard session.
6. The live dashboard continues updating from the same session ID.

The `game_started` matching window is 180 seconds because Unreal may need more than 10 seconds to start.

## Test order

1. Start Apache + MySQL in XAMPP.
2. Sign in as a trainee.
3. Click **Start Main_World**.
4. Unreal should open a standalone game window.
5. The dashboard should show the newly created live session and telemetry.
6. Stop Unreal, then click **End Session**.
7. Sign in as admin/instructor and verify the session appears in Admin Dashboard.
8. Open the session and assign `Review`, `Passed`, or `Failed` plus an optional overall score.
