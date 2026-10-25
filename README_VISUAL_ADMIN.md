# AAR Training – Visual Admin Demo Update

This update keeps the working Unreal telemetry and dashboard launch flow, while upgrading the admin/instructor view for presentation use.

## Included

- Visual Admin Command Centre with hero area, stat cards, charts and live-session cards.
- Trainee roster showing session count and average assessed score.
- Recent-session table now includes **Session Average**.
- Session Average is calculated from available percentage metrics:
  - Alignment Accuracy
  - Position Stability
  - Approach Accuracy
  - Safety Score
- If those metrics are not available, the admin dashboard falls back to the session's Overall Score for the displayed Session Average.
- Admin Session Review now allows entry of the four performance metrics and Reaction Time.
- If Overall Score is left blank when saving a review, it is automatically set to the average of the available percentage metrics.
- Session Review also shows average/max altitude and speed from real telemetry.
- Demo trainee seed script with three extra trainee accounts.
- Start-game endpoint also passes `ScenarioName` to Unreal as a command-line argument.

## Demo trainee accounts

Run `demo_trainees.sql` once in phpMyAdmin.

All three use password: `123456`

- Tanmay Singh – `tanmay.trainee@test.com`
- Swapnil Bonde – `swapnil.trainee@test.com`
- Francisco – `francisco.trainee@test.com`

Your existing Akash trainee account is left unchanged.

## Install

Copy the contents of this project over your existing local `aar-training` project, replacing matching files.

Then run:

```sql
SOURCE demo_trainees.sql;
```

or import `demo_trainees.sql` through phpMyAdmin.

## Important

The supplied demo accounts follow the project's current plain-text password approach. This is fine for the local university demonstration, but production deployment should migrate to `password_hash()` and `password_verify()`.
