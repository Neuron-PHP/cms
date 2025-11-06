<style>
	.dashboard-welcome {
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		color: white;
		padding: 2rem;
		border-radius: 8px;
		margin-bottom: 2rem;
	}
	.dashboard-welcome h2 {
		font-size: 1.75rem;
		margin-bottom: 0.5rem;
	}
	.dashboard-welcome p {
		opacity: 0.9;
	}
	.stats-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
		gap: 1.5rem;
		margin-bottom: 2rem;
	}
	.stat-card {
		background: white;
		padding: 1.5rem;
		border-radius: 8px;
		border-left: 4px solid;
		box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	}
	.stat-card.primary { border-color: #667eea; }
	.stat-card.success { border-color: #28a745; }
	.stat-card.warning { border-color: #ffc107; }
	.stat-card.info { border-color: #17a2b8; }
	.stat-label {
		color: #7f8c8d;
		font-size: 0.9rem;
		margin-bottom: 0.5rem;
	}
	.stat-value {
		font-size: 2rem;
		font-weight: 700;
		color: #2c3e50;
	}
	.quick-actions {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 1rem;
	}
	.action-btn {
		display: block;
		padding: 1rem;
		background: white;
		border: 2px solid #e0e0e0;
		border-radius: 8px;
		text-align: center;
		text-decoration: none;
		color: #2c3e50;
		font-weight: 600;
		transition: all 0.2s;
	}
	.action-btn:hover {
		border-color: #667eea;
		color: #667eea;
		transform: translateY(-2px);
		box-shadow: 0 4px 8px rgba(0,0,0,0.1);
	}
</style>

<div class="card">
	<div class="dashboard-welcome">
		<h2><?= htmlspecialchars($WelcomeMessage) ?></h2>
		<p>You are logged in as <strong><?= htmlspecialchars($User->getRole()) ?></strong></p>
	</div>

	<h3 style="margin-bottom: 1rem; color: #2c3e50;">Quick Actions</h3>
	<div class="quick-actions">
		<a href="/blog" class="action-btn">View Site</a>
		<a href="/admin/posts" class="action-btn">Manage Posts</a>
		<a href="/admin/users" class="action-btn">Manage Users</a>
		<a href="/admin/settings" class="action-btn">Settings</a>
	</div>

	<div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
		<h3 style="margin-bottom: 1rem; color: #2c3e50;">User Information</h3>
		<p><strong>Username:</strong> <?= htmlspecialchars($User->getUsername()) ?></p>
		<p><strong>Email:</strong> <?= htmlspecialchars($User->getEmail()) ?></p>
		<p><strong>Role:</strong> <?= htmlspecialchars($User->getRole()) ?></p>
		<p><strong>Account Status:</strong> <?= htmlspecialchars($User->getStatus()) ?></p>
		<?php if($User->getLastLoginAt()): ?>
			<p><strong>Last Login:</strong> <?= $User->getLastLoginAt()->format('F j, Y g:i A') ?></p>
		<?php endif; ?>
	</div>
</div>
