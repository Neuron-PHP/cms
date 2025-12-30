<!DOCTYPE html>
<html>
<head>
    <title>Email Verified</title>
</head>
<body>
    <?php if (isset($Success) && $Success): ?>
        <h1>Email Verified</h1>
        <p><?= htmlspecialchars($Message ?? 'Your email has been verified successfully.') ?></p>
        <p>You can now <a href="/auth/login">log in</a> to your account.</p>
    <?php else: ?>
        <h1>Verification Failed</h1>
        <p><?= htmlspecialchars($Message ?? 'Invalid or expired verification token.') ?></p>
        <p><a href="/member/registration">Register again</a></p>
    <?php endif; ?>
</body>
</html>
