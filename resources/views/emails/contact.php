<?php
/**
 * Generic contact form email body.
 *
 * Available variables (extracted by the Sender):
 *   $fields    array  Field definitions for the form
 *   $values    array  Submitted values keyed by field name
 *   $formLabel string Human label for the form
 *   $formKey   string Form key
 */
$fields    = $fields ?? [];
$values    = $values ?? [];
$formLabel = $formLabel ?? 'Contact Form';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars( $formLabel ) ?></title>
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
			<h1><?= htmlspecialchars( $formLabel ) ?></h1>
		</div>
		<div class="email-body">
			<p>A new contact form submission was received:</p>
			<table class="fields">
				<tbody>
				<?php foreach( $fields as $field ): ?>
					<?php
					$name = $field['name'] ?? null;
					if( $name === null )
					{
						continue;
					}
					$label = $field['label'] ?? $name;
					$value = $values[ $name ] ?? '';
					if( is_array( $value ) )
					{
						$value = implode( "\n", \Neuron\Cms\Services\Contact\FieldOptions::labelsFor( $field, $value ) );
					}
					elseif( !is_scalar( $value ) )
					{
						$value = json_encode( $value );
					}
					?>
					<tr>
						<th><?= htmlspecialchars( (string) $label ) ?></th>
						<td><?= nl2br( htmlspecialchars( (string) $value ) ) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="email-footer">
			This message was sent from the website contact form.
		</div>
	</div>
</body>
</html>
