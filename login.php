<?php

session_start();

require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE email = ? LIMIT 1"
    );

    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $password === $user["password"]) {

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] =
            $user["first_name"] . " " . $user["last_name"];

        $_SESSION["role"] = $user["role"];

        header("Location: trainee/dashboard.php");
        exit;

    } else {

        $error = "Invalid email or password.";

    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>AAR Training Login</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">

<div class="container">

    <div class="row justify-content-center mt-5">

        <div class="col-md-5">

            <div class="card shadow">

                <div class="card-body p-5">

                    <h2 class="text-center">
                        AAR Training System
                    </h2>

                    <p class="text-center text-muted">
                        Trainee Login
                    </p>

                    <?php if ($error): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error); ?>
                        </div>

                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">

                            <label>Email</label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label>Password</label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-dark w-100"
                        >
                            Login
                        </button>

                    </form>

                    <hr>

                    <small class="text-muted">

                        Demo login:<br>

                        trainee@test.com<br>

                        Password: 123456

                    </small>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>