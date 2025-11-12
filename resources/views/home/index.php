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
				<a href="<?= route_path('blog') ?>" class="btn btn-primary btn-lg px-4 gap-3">Blog</a>
			</div>

			<div class="row mt-5">
				<div class="col-md-4">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body text-center">
							<h3 class="h5 card-title mb-3">Blog</h3>
							<p class="card-text text-muted">Fully functional blog with posts, categories, and tags.</p>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body text-center">
							<h3 class="h5 card-title mb-3">Member Portal</h3>
							<p class="card-text text-muted">Customizable member section with profile and timezone support.</p>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-body text-center">
							<h3 class="h5 card-title mb-3">Admin Portal</h3>
							<p class="card-text text-muted">Manage users and blog posts in an easy to use back end.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
