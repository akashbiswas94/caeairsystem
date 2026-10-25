<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';


/*
|--------------------------------------------------------------------------
| Already logged in
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['user_id'])) {

    $role = strtolower(
        trim((string) ($_SESSION['role'] ?? 'trainee'))
    );

    if ($role === 'admin' || $role === 'instructor') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: trainee/dashboard.php');
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

$error = '';
$email = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');


    if ($email === '' || $password === '') {

        $error = 'Enter your email and password.';

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);


        $passwordValid = false;


        if ($user) {

            $storedPassword =
                (string) ($user['password'] ?? '');


            /*
             * Supports your current demo passwords
             * as well as password_hash() passwords later.
             */
            $isHashed =
                str_starts_with($storedPassword, '$2y$')
                || str_starts_with($storedPassword, '$2a$')
                || str_starts_with($storedPassword, '$argon2');


            if ($isHashed) {

                $passwordValid =
                    password_verify(
                        $password,
                        $storedPassword
                    );

            } else {

                $passwordValid =
                    hash_equals(
                        $storedPassword,
                        $password
                    );

            }

        }


        if ($user && $passwordValid) {

            session_regenerate_id(true);


            $_SESSION['user_id'] =
                (int) $user['id'];


            $_SESSION['user_name'] =
                trim(
                    (string) ($user['first_name'] ?? '')
                    . ' '
                    . (string) ($user['last_name'] ?? '')
                );


            $_SESSION['role'] =
                (string) ($user['role'] ?? 'trainee');


            $role = strtolower(
                trim($_SESSION['role'])
            );


            if (
                $role === 'admin'
                || $role === 'instructor'
            ) {

                header(
                    'Location: admin/dashboard.php'
                );

            } else {

                header(
                    'Location: trainee/dashboard.php'
                );

            }

            exit;

        }


        $error = 'Incorrect email or password.';

    }

}

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        CAE | AAR Training Dashboard
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <style>

        :root {

            --navy-950: #06101d;
            --navy-900: #0a1625;
            --navy-850: #0e1c2e;
            --navy-800: #13233a;

            --blue-500: #3783f6;
            --blue-400: #60a5fa;

            --slate-950: #0f172a;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;

            --white: #ffffff;

        }


        * {
            box-sizing: border-box;
        }


        html,
        body {
            margin: 0;
            min-height: 100%;
        }


        body {

            min-height: 100vh;

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Arial,
                sans-serif;

            background: #f5f7fa;

            color: var(--slate-950);

        }


        /*
        |--------------------------------------------------------------------------
        | Layout
        |--------------------------------------------------------------------------
        */

        .login-page {

            min-height: 100vh;

            display: grid;

            grid-template-columns:
                minmax(480px, 1.15fr)
                minmax(420px, .85fr);

        }


        /*
        |--------------------------------------------------------------------------
        | CAE panel
        |--------------------------------------------------------------------------
        */

        .cae-panel {

            position: relative;

            overflow: hidden;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            min-height: 100vh;

            padding: 46px 56px;

            color: white;

            background:

                radial-gradient(
                    circle at 15% 15%,
                    rgba(55, 131, 246, .22),
                    transparent 31%
                ),

                radial-gradient(
                    circle at 85% 80%,
                    rgba(14, 165, 233, .09),
                    transparent 30%
                ),

                linear-gradient(
                    145deg,
                    var(--navy-950) 0%,
                    var(--navy-900) 50%,
                    var(--navy-800) 100%
                );

        }


        .cae-panel::before {

            content: "";

            position: absolute;

            inset: 0;

            background-image:

                linear-gradient(
                    rgba(255, 255, 255, .018) 1px,
                    transparent 1px
                ),

                linear-gradient(
                    90deg,
                    rgba(255, 255, 255, .018) 1px,
                    transparent 1px
                );

            background-size:
                56px 56px;

            pointer-events: none;

        }


        .cae-panel::after {

            content: "";

            position: absolute;

            width: 520px;
            height: 520px;

            top: 50%;
            right: -285px;

            transform: translateY(-50%);

            border:

                1px solid
                rgba(255, 255, 255, .055);

            border-radius: 50%;

            box-shadow:

                0 0 0 80px
                rgba(255,255,255,.012),

                0 0 0 160px
                rgba(255,255,255,.008);

        }


        .cae-brand,
        .cae-main,
        .cae-footer {

            position: relative;

            z-index: 2;

        }


        /*
        |--------------------------------------------------------------------------
        | CAE branding
        |--------------------------------------------------------------------------
        */

        .cae-brand {

            display: flex;

            align-items: center;

            gap: 14px;

        }


        .cae-logo {

            width: 50px;
            height: 50px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 11px;

            background:

                linear-gradient(
                    145deg,
                    #438cf9,
                    #1d5fd1
                );

            color: white;

            font-size: 13px;
            font-weight: 800;

            letter-spacing: .07em;

            box-shadow:

                0 12px 32px
                rgba(37, 99, 235, .25);

        }


        .brand-name {

            font-size: 16px;

            font-weight: 700;

            letter-spacing: -.01em;

        }


        .brand-subtitle {

            margin-top: 3px;

            color: #91a0b5;

            font-size: 11px;

        }


        /*
        |--------------------------------------------------------------------------
        | Left content
        |--------------------------------------------------------------------------
        */

        .cae-main {

            max-width: 650px;

            margin-top: 70px;

        }


        .system-label {

            margin-bottom: 20px;

            color: #83b7ff;

            font-size: 11px;

            font-weight: 700;

            letter-spacing: .14em;

            text-transform: uppercase;

        }


        .cae-main h1 {

            margin: 0;

            max-width: 620px;

            font-size:
                clamp(
                    42px,
                    5vw,
                    68px
                );

            line-height: 1.02;

            letter-spacing: -.045em;

            font-weight: 700;

        }


        .cae-main h1 span {

            display: block;

            color: var(--blue-400);

        }


        .cae-description {

            max-width: 510px;

            margin:

                25px 0 0;

            color: #aebbd0;

            font-size: 15px;

            line-height: 1.75;

        }


        .cae-divider {

            width: 54px;
            height: 2px;

            margin-top: 30px;

            border-radius: 10px;

            background: var(--blue-500);

        }


        .cae-footer {

            display: flex;

            align-items: center;

            gap: 8px;

            color: #69798e;

            font-size: 10px;

            letter-spacing: .03em;

        }


        .cae-footer-dot {

            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: #38bdf8;

        }


        /*
        |--------------------------------------------------------------------------
        | Login side
        |--------------------------------------------------------------------------
        */

        .login-side {

            min-height: 100vh;

            display: flex;

            align-items: center;
            justify-content: center;

            padding: 52px;

            background: #f7f8fa;

        }


        .login-wrapper {

            width: 100%;

            max-width: 410px;

        }


        .mobile-brand {

            display: none;

        }


        .login-heading {

            margin-bottom: 27px;

        }


        .login-heading h2 {

            margin: 0;

            color: #111827;

            font-size: 29px;

            font-weight: 700;

            letter-spacing: -.03em;

        }


        .login-heading p {

            margin:
                8px 0 0;

            color: var(--slate-500);

            font-size: 13px;

        }


        /*
        |--------------------------------------------------------------------------
        | Form card
        |--------------------------------------------------------------------------
        */

        .login-card {

            padding: 30px;

            border:

                1px solid
                #e4e9f0;

            border-radius: 14px;

            background: white;

            box-shadow:

                0 15px 45px
                rgba(15, 23, 42, .065);

        }


        .form-label {

            margin-bottom: 7px;

            color: var(--slate-700);

            font-size: 12px;

            font-weight: 650;

        }


        .form-control {

            min-height: 48px;

            padding:

                11px 13px;

            border:

                1px solid
                #d8dee7;

            border-radius: 8px;

            background: white;

            color: var(--slate-950);

            font-size: 13px;

            box-shadow: none;

            transition:
                border-color .15s,
                box-shadow .15s;

        }


        .form-control::placeholder {
            color: #a6afbc;
        }


        .form-control:hover {

            border-color: #c3ccd9;

        }


        .form-control:focus {

            border-color: var(--blue-500);

            box-shadow:

                0 0 0 3px
                rgba(55, 131, 246, .10);

        }


        /*
        |--------------------------------------------------------------------------
        | Password
        |--------------------------------------------------------------------------
        */

        .password-field {

            position: relative;

        }


        .password-field .form-control {

            padding-right: 48px;

        }


        .password-toggle {

            position: absolute;

            top: 50%;
            right: 10px;

            transform:
                translateY(-50%);

            width: 32px;
            height: 32px;

            display: grid;

            place-items: center;

            border: none;

            border-radius: 6px;

            background: transparent;

            color: var(--slate-500);

            cursor: pointer;

        }


        .password-toggle:hover {

            background: var(--slate-100);

            color: var(--slate-700);

        }


        /*
        |--------------------------------------------------------------------------
        | Sign in button
        |--------------------------------------------------------------------------
        */

        .login-button {

            width: 100%;

            min-height: 48px;

            margin-top: 7px;

            border: 0;

            border-radius: 8px;

            background: #16243a;

            color: white;

            font-size: 13px;

            font-weight: 700;

            transition:

                background .16s,
                transform .16s,
                box-shadow .16s;

        }


        .login-button:hover {

            background: #0c192b;

            transform:
                translateY(-1px);

            box-shadow:

                0 9px 22px
                rgba(15,23,42,.14);

        }


        /*
        |--------------------------------------------------------------------------
        | Error
        |--------------------------------------------------------------------------
        */

        .login-error {

            display: flex;

            align-items: center;

            gap: 9px;

            margin-bottom: 20px;

            padding: 11px 13px;

            border:

                1px solid
                #fecaca;

            border-radius: 8px;

            background: #fff7f7;

            color: #b91c1c;

            font-size: 12px;

        }


        .error-dot {

            width: 7px;
            height: 7px;

            flex-shrink: 0;

            border-radius: 50%;

            background: #ef4444;

        }


        /*
        |--------------------------------------------------------------------------
        | Card footer
        |--------------------------------------------------------------------------
        */

        .login-status {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-top: 22px;

            padding-top: 19px;

            border-top:

                1px solid
                #eef1f5;

            color: var(--slate-400);

            font-size: 10px;

        }


        .status-online {

            display: flex;

            align-items: center;

            gap: 6px;

        }


        .online-dot {

            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: #22c55e;

        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media
        (max-width: 980px) {

            .login-page {

                display: block;

            }


            .cae-panel {

                display: none;

            }


            .login-side {

                min-height: 100vh;

                padding:
                    36px 22px;

            }


            .mobile-brand {

                display: flex;

                align-items: center;

                gap: 12px;

                margin-bottom: 42px;

            }


            .mobile-brand .cae-logo {

                color: white;

            }


            .mobile-brand-name {

                color: var(--slate-950);

                font-size: 15px;

                font-weight: 700;

            }


            .mobile-brand-subtitle {

                margin-top: 3px;

                color: var(--slate-500);

                font-size: 11px;

            }

        }


        @media
        (max-width: 480px) {

            .login-side {

                align-items: flex-start;

                padding-top: 30px;

            }


            .login-card {

                padding:
                    24px 20px;

            }


            .login-heading h2 {

                font-size: 27px;

            }

        }

    </style>

</head>


<body>


<div class="login-page">


    <!--
    ========================================================================
    CAE / AAR INFORMATION
    ========================================================================
    -->

    <section class="cae-panel">


        <div class="cae-brand">

            <div class="cae-logo">
                CAE
            </div>


            <div>

                <div class="brand-name">
                    AAR Training Dashboard
                </div>

                <div class="brand-subtitle">
                    After Action Review System
                </div>

            </div>

        </div>



        <div class="cae-main">


            <div class="system-label">
                CAE Training System
            </div>


            <h1>

                AAR Training

                <span>
                    Dashboard
                </span>

            </h1>


            <p class="cae-description">

                Track training sessions,
                monitor live simulator telemetry,
                and review trainee performance.

            </p>


            <div class="cae-divider"></div>


        </div>



        <div class="cae-footer">

            <span class="cae-footer-dot"></span>

            Unreal Engine training integration

        </div>


    </section>



    <!--
    ========================================================================
    LOGIN
    ========================================================================
    -->

    <main class="login-side">


        <div class="login-wrapper">


            <!-- MOBILE BRAND -->

            <div class="mobile-brand">


                <div class="cae-logo">
                    CAE
                </div>


                <div>

                    <div class="mobile-brand-name">
                        AAR Training Dashboard
                    </div>

                    <div class="mobile-brand-subtitle">
                        After Action Review System
                    </div>

                </div>


            </div>



            <!-- HEADING -->

            <div class="login-heading">


                <h2>
                    Sign in
                </h2>


                <p>
                    Use your training system account.
                </p>


            </div>



            <!-- CARD -->

            <div class="login-card">


                <?php if ($error !== ''): ?>


                    <div class="login-error">


                        <span class="error-dot"></span>


                        <span>
                            <?= htmlspecialchars($error); ?>
                        </span>


                    </div>


                <?php endif; ?>



                <form
                    method="POST"
                    autocomplete="on"
                >


                    <!-- EMAIL -->

                    <div class="mb-3">


                        <label
                            for="email"
                            class="form-label"
                        >
                            Email address
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="name@example.com"
                            value="<?= htmlspecialchars($email); ?>"
                            autocomplete="email"
                            autofocus
                            required
                        >


                    </div>



                    <!-- PASSWORD -->

                    <div class="mb-3">


                        <label
                            for="password"
                            class="form-label"
                        >
                            Password
                        </label>


                        <div class="password-field">


                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Enter password"
                                autocomplete="current-password"
                                required
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                id="passwordToggle"
                                aria-label="Show password"
                            >


                                <svg
                                    width="17"
                                    height="17"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >

                                    <path
                                        d="
                                            M2 12
                                            s3.5-7 10-7
                                            10 7 10 7
                                            -3.5 7-10 7
                                            S2 12 2 12Z
                                        "
                                    />

                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="3"
                                    />

                                </svg>


                            </button>


                        </div>


                    </div>



                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        class="login-button"
                    >

                        Sign in

                    </button>


                </form>



                <!-- STATUS -->

                <div class="login-status">


                    <span>
                        CAE AAR Training
                    </span>


                    <span class="status-online">

                        <span class="online-dot"></span>

                        System online

                    </span>


                </div>


            </div>


        </div>


    </main>


</div>



<script>

    const passwordInput =
        document.getElementById('password');

    const passwordToggle =
        document.getElementById('passwordToggle');


    passwordToggle.addEventListener(
        'click',
        function () {

            const hidden =
                passwordInput.type === 'password';


            passwordInput.type =
                hidden
                    ? 'text'
                    : 'password';


            passwordToggle.setAttribute(
                'aria-label',
                hidden
                    ? 'Hide password'
                    : 'Show password'
            );

        }
    );

</script>


</body>

</html>