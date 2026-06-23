<section class="py-5">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-7 text-center">
				<h1 class="mb-3">Product Not Found</h1>
				<p class="lead"><?= htmlspecialchars( (string) ( $Message ?? 'Sorry, that product is not available.' ), ENT_QUOTES, 'UTF-8' ) ?></p>
				<a href="/store" class="btn btn-primary mt-3">Back to shop</a>
			</div>
		</div>
	</div>
</section>
