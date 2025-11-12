<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Welcome to <?= htmlspecialchars($SiteName) ?></title>
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
		.username {
			color: #667eea;
			font-weight: 700;
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
			<h1>Welcome to <?= htmlspecialchars($SiteName) ?>!</h1>
		</div>

		<!-- Body -->
		<div class="email-body">
			<p class="greeting">Hi <span class="username"><?= htmlspecialchars($Username) ?></span>,</p>

			<p>
				Thank you for joining <strong><?= htmlspecialchars($SiteName) ?></strong>!
				We're thrilled to have you as part of our community.
			</p>

			<p>
				Your account has been successfully created and you're all set to get started.
				Explore our platform, connect with others, and make the most of your membership.
			</p>

			<div class="cta-container">
				<a href="<?= htmlspecialchars($SiteUrl) ?>" class="cta-button">Visit <?= htmlspecialchars($SiteName) ?></a>
			</div>

			<div class="divider"></div>

			<p>
				If you have any questions or need assistance, don't hesitate to reach out.
				We're here to help you get the most out of your experience.
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
