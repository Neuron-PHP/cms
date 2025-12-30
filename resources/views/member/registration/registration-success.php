<!DOCTYPE html>
<html>
<head>
    <title>Registration Successful</title>
</head>
<body>
    <h1>Registration Successful</h1>
    <p>Your account has been created successfully.</p>
    <?php if (isset($requiresVerification) && $requiresVerification): ?>
        <p>Please check your email to verify your account.</p>
    <?php else: ?>
        <p>You can now <a href="/auth/login">log in</a>.</p>
    <?php endif; ?>
</body>
</html>
