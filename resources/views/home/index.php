<div class="container">
	<div class="row">
		<div class="col-lg-8 mx-auto text-center py-5">
			<h1 class="display-4 mb-4"><?= htmlspecialchars( $Name ?? 'Neuron CMS' ) ?></h1>

			<?php if( !empty( $Description ) ): ?>
				<p class="lead mb-4"><?= htmlspecialchars( $Description ) ?></p>
			<?php else: ?>
				<p class="lead mb-4">A modern, database-backed CMS built on the Neuron framework</p>
			<?php endif; ?>

			<div class="d-grid gap-2 d-sm-flex justify-content-sm-center mb-5">
				<a href="<?= route_path('blog') ?>" class="btn btn-primary btn-lg px-4 gap-3">Visit Blog</a>
				<a href="<?= route_path('admin') ?>" class="btn btn-outline-secondary btn-lg px-4">Admin Dashboard</a>
			</div>

			<div class="row mt-5">
				<div class="col-md-4">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body text-center">
							<h3 class="h5 card-title mb-3">Content Management</h3>
							<p class="card-text text-muted">Create and manage blog posts, categories, and tags with an intuitive admin interface.</p>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body text-center">
							<h3 class="h5 card-title mb-3">User Management</h3>
							<p class="card-text text-muted">Manage users with role-based access control and secure authentication.</p>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body text-center">
							<h3 class="h5 card-title mb-3">Modern & Fast</h3>
							<p class="card-text text-muted">Built with PHP 8.4+ using modern practices and the Neuron MVC framework.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
