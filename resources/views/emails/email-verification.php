<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Verify Your Email - <?= htmlspecialchars($SiteName) ?></title>
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
			background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
			background-color: #10b981;
			color: #ffffff;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 600;
			transition: background-color 0.3s ease;
		}
		.cta-button:hover {
			background-color: #059669;
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
			color: #10b981;
			text-decoration: none;
			font-size: 14px;
		}
		.security-notice {
			background-color: #dbeafe;
			border-left: 4px solid #3b82f6;
			padding: 16px;
			margin: 24px 0;
			border-radius: 4px;
		}
		.security-notice p {
			margin: 0;
			color: #1e40af;
			font-size: 14px;
		}
		.security-notice strong {
			color: #1e3a8a;
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
			<h1>Verify Your Email Address</h1>
		</div>

		<!-- Body -->
		<div class="email-body">
			<p class="greeting">Hello<?= isset($Username) ? ', ' . htmlspecialchars($Username) : '' ?>!</p>

			<p>
				Thank you for registering with <strong><?= htmlspecialchars($SiteName) ?></strong>.
				To complete your registration and activate your account, please verify your email address by clicking the button below:
			</p>

			<div class="cta-container">
				<a href="<?= htmlspecialchars($VerificationLink) ?>" class="cta-button">Verify Email Address</a>
			</div>

			<p style="text-align: center; font-size: 14px; color: #6b7280;">
				Or copy and paste this link into your browser:
			</p>

			<div class="link-box">
				<a href="<?= htmlspecialchars($VerificationLink) ?>"><?= htmlspecialchars($VerificationLink) ?></a>
			</div>

			<div class="security-notice">
				<p>
					<strong>Important:</strong> This verification link will expire in <?= htmlspecialchars($ExpirationMinutes) ?> minutes.
					If you did not create an account with us, please ignore this email.
				</p>
			</div>

			<div class="divider"></div>

			<p>
				Once you verify your email, you'll be able to log in and access all member features.
			</p>

			<p>
				If you're having trouble clicking the verification button, copy and paste the URL above into your web browser.
			</p>

			<p>
				Welcome aboard!<br>
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
