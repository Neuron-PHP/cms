#!/usr/bin/env php
<?php
/**
 * Regenerate cms/resources/.cms-manifest.json from the package's resource
 * directories.
 *
 * Why this exists:
 *   The `cms:upgrade` command discovers new migrations by diffing the package
 *   manifest's "migrations" list against the installation's manifest. If a new
 *   migration file is added under resources/database/migrate/ but not listed in
 *   the manifest, `cms:upgrade` will NOT see it (it reports "already up to
 *   date") and the migration is never copied to the installation.
 *
 *   Keeping the manifest in sync by hand is error-prone, so this script rebuilds
 *   the derived lists straight from the filesystem:
 *     - migrations        from resources/database/migrate/*.php
 *     - config_files      from resources/config/*            (non-hidden files)
 *     - view_directories  from resources/views/*             (directories)
 *     - public_assets     from resources/public/*            (files, incl. dotfiles)
 *
 *   Release metadata is preserved from the existing manifest unless overridden:
 *     - version, release_date, breaking_changes, deprecations, upgrade_notes
 *
 * Usage (run from anywhere; paths are derived from __DIR__):
 *   php scripts/generate-manifest.php
 *       Rewrite resources/.cms-manifest.json in place.
 *
 *   php scripts/generate-manifest.php --check
 *       Do not write. Exit 0 if the manifest is already in sync, or exit 1 and
 *       report the drift. Intended for CI and release pre-flight checks.
 *
 *   php scripts/generate-manifest.php --version=2026.6.18 --release-date=2026-06-18
 *       Override the preserved version / release_date while regenerating.
 *
 * Exit codes:
 *   0  success (written, or --check found no drift)
 *   1  failure (write failed, parse error, or --check found drift)
 *   2  invalid usage
 */

$root         = dirname( __DIR__ );          // cms/
$resources    = $root . '/resources';
$manifestPath = $resources . '/.cms-manifest.json';

$options = parseOptions( array_slice( $argv, 1 ) );

$existing = loadExistingManifest( $manifestPath );

// Derive the filesystem-backed lists.
$migrations      = listFiles( $resources . '/database/migrate', '/\.php$/' );
$configFiles     = listFiles( $resources . '/config', null, false );
$publicAssets    = listFiles( $resources . '/public', null, true );
$viewDirectories = listDirectories( $resources . '/views' );

// Build the manifest, preserving release metadata.
$manifest = [
	'version'          => $options['version'] ?? ( $existing['version'] ?? 'unknown' ),
	'release_date'     => $options['release-date'] ?? ( $existing['release_date'] ?? date( 'Y-m-d' ) ),
	'migrations'       => $migrations,
	'config_files'     => $configFiles,
	'view_directories' => $viewDirectories,
	'public_assets'    => $publicAssets,
	'breaking_changes' => $existing['breaking_changes'] ?? [],
	'deprecations'     => $existing['deprecations'] ?? [],
	'upgrade_notes'    => $existing['upgrade_notes'] ?? 'See UPGRADE_NOTES.md for detailed upgrade instructions',
];

$json = encodeManifest( $manifest );

if( $options['check'] )
{
	exit( checkManifest( $manifestPath, $json, $migrations ) );
}

if( file_put_contents( $manifestPath, $json ) === false )
{
	fwrite( STDERR, "ERROR: failed to write manifest: $manifestPath\n" );
	exit( 1 );
}

echo "✓ Wrote " . relativePath( $root, $manifestPath ) . "\n";
echo "  migrations:       " . count( $migrations ) . "\n";
echo "  config_files:     " . count( $configFiles ) . "\n";
echo "  view_directories: " . count( $viewDirectories ) . "\n";
echo "  public_assets:    " . count( $publicAssets ) . "\n";
exit( 0 );


/**
 * Parse CLI options.
 *
 * @param array<int, string> $args
 * @return array{check: bool, version: ?string, release-date: ?string}
 */
function parseOptions( array $args ): array
{
	$options = [ 'check' => false, 'version' => null, 'release-date' => null ];

	foreach( $args as $arg )
	{
		if( $arg === '--check' || $arg === '-c' )
		{
			$options['check'] = true;
		}
		elseif( str_starts_with( $arg, '--version=' ) )
		{
			$options['version'] = substr( $arg, strlen( '--version=' ) );
		}
		elseif( str_starts_with( $arg, '--release-date=' ) )
		{
			$options['release-date'] = substr( $arg, strlen( '--release-date=' ) );
		}
		else
		{
			fwrite( STDERR, "Unknown option: $arg\n" );
			fwrite( STDERR, "Usage: php scripts/generate-manifest.php [--check] [--version=X] [--release-date=Y]\n" );
			exit( 2 );
		}
	}

	return $options;
}

/**
 * Load and decode the existing manifest, or return an empty array if absent.
 *
 * @param string $path
 * @return array<string, mixed>
 */
function loadExistingManifest( string $path ): array
{
	if( !file_exists( $path ) )
	{
		return [];
	}

	$decoded = json_decode( (string)file_get_contents( $path ), true );

	if( json_last_error() !== JSON_ERROR_NONE )
	{
		fwrite( STDERR, "ERROR: failed to parse existing manifest: " . json_last_error_msg() . "\n" );
		exit( 1 );
	}

	return is_array( $decoded ) ? $decoded : [];
}

/**
 * List file names directly inside a directory, sorted.
 *
 * @param string      $dir           Directory to scan
 * @param string|null $pattern       Optional regex the file name must match
 * @param bool        $includeHidden Whether to include dotfiles
 * @return array<int, string>
 */
function listFiles( string $dir, ?string $pattern = null, bool $includeHidden = false ): array
{
	if( !is_dir( $dir ) )
	{
		return [];
	}

	$entries = scandir( $dir );

	if( $entries === false )
	{
		return [];
	}

	$files = [];

	foreach( $entries as $entry )
	{
		if( $entry === '.' || $entry === '..' )
		{
			continue;
		}

		if( !$includeHidden && $entry[0] === '.' )
		{
			continue;
		}

		$path = $dir . '/' . $entry;

		if( !is_file( $path ) )
		{
			continue;
		}

		if( $pattern !== null && !preg_match( $pattern, $entry ) )
		{
			continue;
		}

		$files[] = $entry;
	}

	sort( $files );

	return $files;
}

/**
 * List directory names directly inside a directory, sorted (dotdirs skipped).
 *
 * @param string $dir
 * @return array<int, string>
 */
function listDirectories( string $dir ): array
{
	if( !is_dir( $dir ) )
	{
		return [];
	}

	$entries = scandir( $dir );

	if( $entries === false )
	{
		return [];
	}

	$dirs = [];

	foreach( $entries as $entry )
	{
		if( $entry === '.' || $entry === '..' || $entry[0] === '.' )
		{
			continue;
		}

		if( is_dir( $dir . '/' . $entry ) )
		{
			$dirs[] = $entry;
		}
	}

	sort( $dirs );

	return $dirs;
}

/**
 * Encode the manifest as pretty JSON using the repo's 2-space indentation.
 *
 * @param array<string, mixed> $data
 * @return string
 */
function encodeManifest( array $data ): string
{
	$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	if( $json === false )
	{
		fwrite( STDERR, "ERROR: failed to encode manifest: " . json_last_error_msg() . "\n" );
		exit( 1 );
	}

	// json_encode uses 4-space indentation; the repo manifest uses 2 spaces.
	$json = preg_replace_callback(
		'/^( +)/m',
		static fn( array $m ): string => str_repeat( ' ', intdiv( strlen( $m[1] ), 2 ) ),
		$json
	);

	return $json . "\n";
}

/**
 * Compare the freshly generated manifest against the file on disk.
 *
 * @param string             $path          Manifest path
 * @param string             $generated     Freshly generated manifest JSON
 * @param array<int, string> $migrations    Migrations derived from disk
 * @return int Exit code (0 in sync, 1 drift)
 */
function checkManifest( string $path, string $generated, array $migrations ): int
{
	$current = file_exists( $path ) ? (string)file_get_contents( $path ) : '';

	if( rtrim( $current ) === rtrim( $generated ) )
	{
		echo "✓ Manifest is in sync.\n";
		return 0;
	}

	fwrite( STDERR, "✗ Manifest is OUT OF DATE.\n" );
	fwrite( STDERR, "  Run: php scripts/generate-manifest.php\n" );

	// Migration drift is the part that breaks cms:upgrade, so call it out.
	$currentManifest    = json_decode( $current, true );
	$currentMigrations  = is_array( $currentManifest ) ? ( $currentManifest['migrations'] ?? [] ) : [];

	foreach( array_values( array_diff( $migrations, $currentMigrations ) ) as $missing )
	{
		fwrite( STDERR, "    + missing from manifest (will be invisible to cms:upgrade): $missing\n" );
	}

	foreach( array_values( array_diff( $currentMigrations, $migrations ) ) as $extra )
	{
		fwrite( STDERR, "    - listed in manifest but not on disk: $extra\n" );
	}

	return 1;
}

/**
 * Render a path relative to the package root for friendlier output.
 */
function relativePath( string $root, string $path ): string
{
	return str_starts_with( $path, $root . '/' ) ? substr( $path, strlen( $root ) + 1 ) : $path;
}
