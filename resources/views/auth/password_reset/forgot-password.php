<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
</head>
<body>
    <h1>Forgot Password</h1>
    <p>Enter your email address and we'll send you a password reset link.</p>
    <form method="POST" action="/forgot-password">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

        <label>Email:</label>
        <input type="email" name="email" required>

        <button type="submit">Send Reset Link</button>
    </form>
</body>
</html>
