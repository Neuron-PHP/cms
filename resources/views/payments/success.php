<section class="py-5">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-7 text-center">
				<div class="mb-3" style="font-size:3rem;line-height:1;">&#10003;</div>
				<h1 class="mb-3">Thank You</h1>
				<p class="lead"><?= htmlspecialchars( (string) ( $Message ?? 'Thank you for your payment!' ), ENT_QUOTES, 'UTF-8' ) ?></p>
				<?php if( !empty( $Payment ) && !empty( $Payment['amount_cents'] ) ): ?>
					<p class="text-muted">
						A receipt has been sent to your email.
					</p>
				<?php endif; ?>
				<a href="/" class="btn btn-primary mt-3">Return Home</a>
			</div>
		</div>
	</div>
</section>
