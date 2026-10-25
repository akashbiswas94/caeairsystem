# CAE AAR Training Platform

An Unreal Engine training project with a PHP/MySQL After Action Review (AAR) dashboard. This repository brings together the **boom training simulator** and the **Bounce Ball training game**, with session tracking and telemetry for reviewing trainee performance.

## Project components

| Component | Purpose |
| --- | --- |
| Boom simulator | Boom movement training and telemetry, including position and joystick input where configured. Uses `Main_World`. |
| Bounce Ball | A separate Unreal training game for movement, jumping, obstacles and collision tracking. |
| AAR dashboard | Trainee session monitoring, telemetry display, session history and administrator/instructor review. |

Keep the two Unreal project folders separate. Each retains its own `.uproject`, assets, source code and plugin requirements. The dashboard is a shared web application; combining the folders in one repository does not merge the Unreal projects.

> Folder names may differ between checkouts. Open the `.uproject` inside the relevant simulator folder. The PHP paths below are relative to the `aar-training` web application.

## Features

- Trainee dashboard with live session data and previous training sessions.
- **Start Main_World** from the trainee dashboard or an administrator/instructor launch control.
- Administrator/instructor overview of trainees and sessions.
- AAR session review with an optional overall score and `Review`, `Passed` or `Failed` result.
- Unreal telemetry associated with training sessions through the PHP API.
- Separate boom and Bounce Ball exercises within the same repository.

The dashboard launcher described here starts the **boom simulator's Main_World**. Launch Bounce Ball from its own Unreal project unless a separate launcher has been configured.

## Requirements

- Windows PC for the dashboard-controlled Unreal launcher.
- Unreal Engine **5.6** and the plugins required by each `.uproject`.
- XAMPP with Apache, PHP and MySQL/MariaDB.
- The AAR database schema and configured database connection.
- A C++ build toolchain compatible with the Unreal project if source modules need rebuilding.

## Set up the dashboard

1. Back up the existing `C:\xampp\htdocs\aar-training` folder and its database before replacing files.
2. Copy the dashboard application into `C:\xampp\htdocs\aar-training`. For an existing installation, copy the patch files into their matching folders and allow Windows to replace the corresponding PHP files.
3. For a fresh installation, import the application's full database schema into MySQL/MariaDB and configure the existing database connection settings. `admin_setup.sql` contains role-check/promote examples; it is not a replacement for the full schema.
4. Start Apache and MySQL from the XAMPP Control Panel.
5. Open [the local login page](http://localhost/aar-training/login.php).

Start Apache from the **XAMPP Control Panel in your logged-in Windows session**. If Apache runs as a Windows service under another account or session, the Unreal window may not appear on your desktop.

## Set up the Unreal projects

### Boom simulator

1. Open the boom simulator's `.uproject` in Unreal Engine 5.6.
2. Enable its required plugins and rebuild source modules if prompted.
3. Check that `Main_World` opens and runs in the editor.
4. Confirm that its telemetry requests target the AAR application's telemetry API.
5. Configure the dashboard launcher as described below.

### Bounce Ball

1. Open the Bounce Ball `.uproject` in Unreal Engine 5.6.
2. Enable its required plugins and rebuild source modules if prompted.
3. Open the game's training map and run it from the editor.
4. Check the project's telemetry configuration and confirm that its session events reach the AAR API.
5. Verify that the events and metrics implemented in this exercise, such as collision counts, appear in the intended session.

Each project must have its telemetry integration configured and tested separately. Keeping both folders in this repository does not automatically connect either game to the dashboard.

## Configure dashboard launch

Open `C:\xampp\htdocs\aar-training\config\game.php` and check the executable and project candidate arrays.

The supplied patch checks these likely locations:

```text
C:\Program Files\Epic Games\UE_5.6\Engine\Binaries\Win64\UnrealEditor.exe
D:\QUT\IFN636\CAE_Project\Development\projectboomboom\UE5 5.6 5.6\projectboomboom.uproject
```

It also checks the older `UE5 5.6` project folder as a fallback. Update the candidates to match your local checkout. Repository folder changes must also be reflected in this configuration.

Use the dashboard through `http://localhost/aar-training/`. The `api/start-game.php` endpoint intentionally refuses remote launch requests because it starts desktop software on the Windows host. Keep this launcher local; do not expose it as an internet-accessible endpoint.

## Accounts and access

Inspect the existing `users.role` column before running the relevant examples in `admin_setup.sql`. Only assign role values supported by your database.

| Signed-in role | Login destination |
| --- | --- |
| `trainee` | `trainee/dashboard.php` |
| `admin` or `instructor`, if supported by the schema | `admin/dashboard.php` |

The supplied patch routes **any signed-in role other than `trainee`** to the admin area. Review the role values in your installation before granting access.

## Session workflow

1. A trainee signs in, or an administrator/instructor selects a trainee.
2. Clicking **Start Main_World** sends a local launch request.
3. PHP creates a training session with `status = NULL`, displayed as **In Progress**.
4. Windows starts Unreal Engine 5.6 with `Main_World` in `-game` mode.
5. Unreal sends `game_started` and subsequent telemetry to the existing API.
6. The API attaches the launch traffic to the pre-created dashboard session, allowing **180 seconds** for startup.
7. The live dashboard updates against that session ID.
8. After stopping Unreal, click **End Session** to close the session as `Review`.
9. An administrator/instructor opens the AAR session page and assigns `Review`, `Passed` or `Failed`, with an optional overall score.

The 180-second attachment window allows for Unreal startup time. It does not replace the need to check that telemetry reaches the correct trainee and session.

### Session status values

| Database value | Meaning |
| --- | --- |
| `NULL` | In Progress |
| `Review` | Session closed and awaiting or undergoing review |
| `Passed` | Reviewed and passed |
| `Failed` | Reviewed and failed |

The patch closes sessions using `Review` rather than the unsupported `completed` value.

## Dashboard patch files

| File | Change |
| --- | --- |
| `trainee/dashboard.php` | Existing live dashboard with a Start Main_World button. |
| `api/start-game.php` | Local-only Windows launcher. |
| `api/common.php` | Uses `NULL` for In Progress and allows 180 seconds for dashboard launches to attach to `game_started`. |
| `api/telemetry.php` | Uses `Review` instead of `completed` when closing sessions. |
| `api/end-session.php` | Closes sessions as `Review`. |
| `config/game.php` | Unreal executable and project path candidates. |
| `admin/dashboard.php` | Trainee/session overview and launch controls. |
| `admin/session.php` | AAR session review and score/result updates. |
| `login.php` | Role-based dashboard redirection. |
| `admin_setup.sql` | Role-check and account-promotion examples. |

## Verification checklist

### Boom simulator and launcher

- [ ] Apache and MySQL are running in XAMPP.
- [ ] A trainee can sign in and open the trainee dashboard.
- [ ] Start Main_World opens a standalone Unreal game window through localhost.
- [ ] A new In Progress session appears in the dashboard.
- [ ] `game_started` and telemetry attach to the same session ID.
- [ ] Telemetry continues updating during the exercise.
- [ ] After stopping Unreal and clicking End Session, the session becomes Review.
- [ ] An admin/instructor can find the session and update its result and score.
- [ ] An admin/instructor can launch a session for the selected trainee.

### Bounce Ball

- [ ] The separate Unreal project opens and runs.
- [ ] Movement, jumping and collision tracking behave as intended.
- [ ] The game's telemetry reaches the configured AAR API.
- [ ] Events are associated with the intended trainee and session.
- [ ] Session data can be reviewed in the dashboard where supported by the current integration.

## Troubleshooting

| Issue | Check |
| --- | --- |
| Launch request refused | Open the dashboard using localhost on the Windows PC hosting Apache. |
| Unreal does not open | Check the executable/project candidates in `config/game.php` and start Apache in the logged-in desktop session. |
| Unreal opens without telemetry | Check Apache/MySQL, the Unreal API URL, and the game's HTTP integration. |
| Telemetry appears in a different session | Check `game_started`, trainee/session identifiers and whether startup exceeded the 180-second attachment window. |
| Status update fails | Confirm the database supports `Review`, `Passed` and `Failed`, and that the patch files were replaced together. |
| Admin dashboard is unavailable | Inspect the signed-in user's supported role value and sign in again after changing it. |
| Bounce Ball does not open from Start Main_World | That button targets the boom project. Open Bounce Ball separately or implement a dedicated launcher. |

## Repository housekeeping

Keep Unreal source, configuration, project descriptors and required assets under version control. Exclude generated build/cache folders such as `Binaries`, `Intermediate`, `Saved` and `DerivedDataCache` where appropriate for the project. Keep database passwords, local credentials and private trainee data out of the repository. Use Git LFS for large binary assets if required by your repository setup.

## Scope

This README combines the two simulator workflows and the supplied dashboard patch instructions. Confirm exact folder names, map paths, database setup and each game's telemetry coverage against the files in your checkout. The Main_World launcher configuration applies to the boom simulator; a unified simulator selector is not described by this patch.
