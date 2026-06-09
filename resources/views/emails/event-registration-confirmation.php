<?php
/**
 * Registrant confirmation email for an event registration.
 *
 * Available variables (extracted by the Sender):
 *   $registration \Neuron\Cms\Models\EventRegistration
 *   $event        \Neuron\Cms\Models\Event
 */
/** @var \Neuron\Cms\Models\EventRegistration $registration */
/** @var \Neuron\Cms\Models\Event $event */
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Registration Confirmed</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
		.email-container { max-width: 640px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
		.email-header { background: #2c3e50; color: #fff; padding: 24px 28px; }
		.email-header h1 { margin: 0; font-size: 20px; }
		.email-body { padding: 24px 28px; }
		.event-meta { margin: 16px 0; padding: 16px; background: #f7f9fb; border-radius: 6px; }
		.event-meta div { margin-bottom: 6px; }
		.email-footer { padding: 16px 28px; color: #888; font-size: 12px; background: #f9fafb; border-top: 1px solid #e5e7eb; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1>Registration Confirmed</h1>
		</div>
		<div class="email-body">
			<p>Hi <?= htmlspecialchars( $registration->getName() ) ?>,</p>
			<p>Your registration for <strong><?= htmlspecialchars( $event->getTitle() ) ?></strong> is confirmed.</p>
			<div class="event-meta">
				<div><strong>Date:</strong> <?= htmlspecialchars( $event->getStartDate()->format( 'l, F j, Y g:i A' ) ) ?></div>
				<?php if( $event->getLocation() ): ?>
					<div><strong>Location:</strong> <?= htmlspecialchars( $event->getLocation() ) ?></div>
				<?php endif; ?>
			</div>
			<p>We look forward to seeing you!</p>
		</div>
		<div class="email-footer">
			You are receiving this email because you registered for an event on our website.
		</div>
	</div>
</body>
</html>
