<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Password Reset - <?= htmlspecialchars($SiteName) ?></title>
	<style>
		/* Inline styles for better email client compatibility */
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
			line-height: 1.6;
			color: #333333;
			margin: 0;
			padding: 0;
			background-color: #f4f4f4;
		}
		.email-container {
			max-width: 600px;
			margin: 20px auto;
			background-color: #ffffff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		}
		.email-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #ffffff;
			padding: 40px 20px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			font-size: 28px;
			font-weight: 600;
		}
		.email-body {
			padding: 40px 30px;
			background-color: #ffffff;
		}
		.email-body p {
			margin: 0 0 16px 0;
			color: #555555;
		}
		.email-body p:last-child {
			margin-bottom: 0;
		}
		.greeting {
			font-size: 18px;
			font-weight: 600;
			color: #333333;
			margin-bottom: 20px;
		}
		.cta-button {
			display: inline-block;
			padding: 14px 32px;
			margin: 24px 0;
			background-color: #667eea;
			color: #ffffff;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 600;
			transition: background-color 0.3s ease;
		}
		.cta-button:hover {
			background-color: #5568d3;
		}
		.cta-container {
			text-align: center;
		}
		.link-box {
			background-color: #f9fafb;
			border: 1px solid #e5e7eb;
			border-radius: 6px;
			padding: 16px;
			margin: 20px 0;
			word-break: break-all;
		}
		.link-box a {
			color: #667eea;
			text-decoration: none;
			font-size: 14px;
		}
		.security-notice {
			background-color: #fef3c7;
			border-left: 4px solid #f59e0b;
			padding: 16px;
			margin: 24px 0;
			border-radius: 4px;
		}
		.security-notice p {
			margin: 0;
			color: #92400e;
			font-size: 14px;
		}
		.security-notice strong {
			color: #78350f;
		}
		.email-footer {
			background-color: #f9fafb;
			padding: 30px 20px;
			text-align: center;
			color: #6b7280;
			font-size: 14px;
			border-top: 1px solid #e5e7eb;
		}
		.email-footer p {
			margin: 8px 0;
		}
		.divider {
			height: 1px;
			background-color: #e5e7eb;
			margin: 24px 0;
		}
		@media only screen and (max-width: 600px) {
			.email-container {
				margin: 0;
				border-radius: 0;
			}
			.email-body {
				padding: 30px 20px;
			}
		}
	</style>
</head>
<body>
	<div class="email-container">
		<!-- Header -->
		<div class="email-header">
			<h1>Password Reset Request</h1>
		</div>

		<!-- Body -->
		<div class="email-body">
			<p class="greeting">Hello,</p>

			<p>
				You have requested to reset your password for <strong><?= htmlspecialchars($SiteName) ?></strong>.
				Click the button below to create a new password:
			</p>

			<div class="cta-container">
				<a href="<?= htmlspecialchars($ResetLink) ?>" class="cta-button">Reset Password</a>
			</div>

			<p style="text-align: center; font-size: 14px; color: #6b7280;">
				Or copy and paste this link into your browser:
			</p>

			<div class="link-box">
				<a href="<?= htmlspecialchars($ResetLink) ?>"><?= htmlspecialchars($ResetLink) ?></a>
			</div>

			<div class="security-notice">
				<p>
					<strong>Security Notice:</strong> This link will expire in <?= htmlspecialchars($ExpirationMinutes) ?> minutes.
					If you did not request a password reset, please ignore this email and your password will remain unchanged.
				</p>
			</div>

			<div class="divider"></div>

			<p>
				If you're having trouble clicking the reset button, copy and paste the URL above into your web browser.
			</p>

			<p>
				Best regards,<br>
				<strong>The <?= htmlspecialchars($SiteName) ?> Team</strong>
			</p>
		</div>

		<!-- Footer -->
		<div class="email-footer">
			<p>&copy; <?= date('Y') ?> <?= htmlspecialchars($SiteName) ?>. All rights reserved.</p>
			<p>This is an automated message. Please do not reply to this email.</p>
		</div>
	</div>
</body>
</html>
