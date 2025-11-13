<div class="container-fluid">
	<div class="row">
		<div class="col-12">
			<h1 class="mb-4">Member Dashboard</h1>

			<div class="alert alert-success" role="alert">
				<h4 class="alert-heading"><?= htmlspecialchars( $WelcomeMessage ?? 'Welcome!' ) ?></h4>
				<p class="mb-0">You are logged in to your member account.</p>
			</div>
		</div>
	</div>

	<div class="row mt-4">
		<div class="col-md-4 mb-4">
			<div class="card">
				<div class="card-body">
					<h5 class="card-title">
						<i class="bi bi-person-circle me-2"></i>Profile
					</h5>
					<p class="card-text">Manage your account settings and personal information.</p>
					<a href="<?= route_path('member_profile') ?>" class="btn btn-primary btn-sm">
						Edit Profile
					</a>
				</div>
			</div>
		</div>

		<div class="col-md-4 mb-4">
			<div class="card">
				<div class="card-body">
					<h5 class="card-title">
						<i class="bi bi-shield-check me-2"></i>Account Status
					</h5>
					<p class="card-text">
						<strong>Username:</strong> <?= htmlspecialchars( $User->getUsername() ) ?><br>
						<strong>Email:</strong> <?= htmlspecialchars( $User->getEmail() ) ?><br>
						<strong>Status:</strong>
						<?php if( $User->isEmailVerified() ): ?>
							<span class="badge bg-success">Verified</span>
						<?php else: ?>
							<span class="badge bg-warning">Unverified</span>
						<?php endif; ?>
					</p>
				</div>
			</div>
		</div>

		<div class="col-md-4 mb-4">
			<div class="card">
				<div class="card-body">
					<h5 class="card-title">
						<i class="bi bi-clock-history me-2"></i>Account Info
					</h5>
					<p class="card-text">
						<strong>Member Since:</strong>
						<?= $User->getCreatedAt() ? $User->getCreatedAt()->format('F j, Y') : 'N/A' ?><br>
						<strong>Last Login:</strong>
						<?= $User->getLastLoginAt() ? $User->getLastLoginAt()->format('F j, Y g:i A') : 'Never' ?>
					</p>
				</div>
			</div>
		</div>
	</div>
</div>
