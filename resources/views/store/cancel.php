<section class="py-5">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-7 text-center">
				<h1 class="mb-3">Order Canceled</h1>
				<p class="lead"><?= htmlspecialchars( (string) ( $Message ?? 'Your order was canceled and you have not been charged.' ), ENT_QUOTES, 'UTF-8' ) ?></p>
				<a href="/cart" class="btn btn-primary mt-3">Return to cart</a>
			</div>
		</div>
	</div>
</section>
