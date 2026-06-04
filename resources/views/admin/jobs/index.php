<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Scheduled Jobs</h2>
	</div>

	<?php if( empty( $scheduleFileExists ) ): ?>
		<div class="alert alert-info" role="alert">
			<i class="bi bi-info-circle me-2"></i>
			No <code>config/schedule.yaml</code> file was found. Scheduled jobs are defined in
			<code>config/schedule.yaml</code> at the project root.
		</div>
	<?php elseif( empty( $jobs ) ): ?>
		<div class="alert alert-info" role="alert">
			<i class="bi bi-info-circle me-2"></i>
			No scheduled jobs are defined in <code>config/schedule.yaml</code>.
		</div>
	<?php else: ?>
		<div class="card">
			<div class="card-body">
				<table class="table table-striped align-middle">
					<thead>
						<tr>
							<th>Name</th>
							<th>Class</th>
							<th>Cron</th>
							<th>Next Run</th>
							<th>Queue</th>
							<th>Arguments</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach( $jobs as $job ): ?>
							<tr>
								<td><?= htmlspecialchars( $job['name'] ) ?></td>
								<td><code><?= htmlspecialchars( $job['class'] ) ?></code></td>
								<td><code><?= htmlspecialchars( $job['cron'] ) ?></code></td>
								<td>
									<?php if( !empty( $job['valid'] ) && $job['nextRun'] !== null ): ?>
										<?= htmlspecialchars( $job['nextRun'] ) ?>
									<?php else: ?>
										<span class="badge bg-danger">Invalid cron</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if( !empty( $job['queue'] ) ): ?>
										<span class="badge bg-info text-dark"><?= htmlspecialchars( (string)$job['queue'] ) ?></span>
									<?php else: ?>
										<span class="badge bg-secondary">direct</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if( !empty( $job['args'] ) && is_array( $job['args'] ) ): ?>
										<?php foreach( $job['args'] as $key => $value ): ?>
											<?php
												$display = is_scalar( $value ) || $value === null
													? var_export( $value, true )
													: json_encode( $value );
											?>
											<span class="badge bg-light text-dark border me-1 mb-1">
												<?= htmlspecialchars( (string)$key ) ?>: <?= htmlspecialchars( (string)$display ) ?>
											</span>
										<?php endforeach; ?>
									<?php else: ?>
										<span class="text-muted">&mdash;</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>
