<?php
/**
 * Admin notification email for a new event registration.
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
	<title>New Event Registration</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
		.email-container { max-width: 640px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
		.email-header { background: #2c3e50; color: #fff; padding: 24px 28px; }
		.email-header h1 { margin: 0; font-size: 20px; }
		.email-body { padding: 24px 28px; }
		table.fields { width: 100%; border-collapse: collapse; }
		table.fields th { text-align: left; vertical-align: top; padding: 10px 12px; width: 30%; background: #f7f9fb; border-bottom: 1px solid #e5e7eb; color: #555; }
		table.fields td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; white-space: pre-wrap; }
		.email-footer { padding: 16px 28px; color: #888; font-size: 12px; background: #f9fafb; border-top: 1px solid #e5e7eb; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1>New Event Registration</h1>
		</div>
		<div class="email-body">
			<p>A new registration was received for <strong><?= htmlspecialchars( $event->getTitle() ) ?></strong>.</p>
			<table class="fields">
				<tbody>
					<tr>
						<th>Event</th>
						<td><?= htmlspecialchars( $event->getTitle() ) ?></td>
					</tr>
					<tr>
						<th>Date</th>
						<td><?= htmlspecialchars( $event->getStartDate()->format( 'l, F j, Y g:i A' ) ) ?></td>
					</tr>
					<?php if( $event->getLocation() ): ?>
						<tr>
							<th>Location</th>
							<td><?= htmlspecialchars( $event->getLocation() ) ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th>Name</th>
						<td><?= htmlspecialchars( $registration->getName() ) ?></td>
					</tr>
					<tr>
						<th>Email</th>
						<td><?= htmlspecialchars( $registration->getEmail() ) ?></td>
					</tr>
					<?php if( $registration->getUserId() ): ?>
						<tr>
							<th>Member account</th>
							<td>#<?= htmlspecialchars( (string)$registration->getUserId() ) ?></td>
						</tr>
					<?php endif; ?>
					<?php if( $registration->getNotes() ): ?>
						<tr>
							<th>Notes</th>
							<td><?= nl2br( htmlspecialchars( $registration->getNotes() ) ) ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<div class="email-footer">
			This message was sent from the website event registration form.
		</div>
	</div>
</body>
</html>
