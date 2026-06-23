<section class="py-5">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-7 text-center">
				<h1 class="mb-3">Payment Canceled</h1>
				<p class="lead"><?= htmlspecialchars( (string) ( $Message ?? 'Your payment was canceled and you have not been charged.' ), ENT_QUOTES, 'UTF-8' ) ?></p>
				<a href="/" class="btn btn-outline-primary mt-3">Return Home</a>
			</div>
		</div>
	</div>
</section>
