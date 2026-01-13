<?php

namespace Tests\Unit\Cms\Cli\Commands\Install;

use Neuron\Cli\Console\Output;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cli\IO\TestInputReader;
use Neuron\Cms\Cli\Commands\Install\InstallCommand;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class InstallCommandTest extends TestCase
{
	private InstallCommand $command;
	private Output $mockOutput;
	private TestInputReader $testInput;

	protected function setUp(): void
	{
		parent::setUp();

		$this->command = new InstallCommand();
		$this->mockOutput = $this->createMock( Output::class );
		$this->testInput = new TestInputReader( $this->mockOutput );

		$this->command->setOutput( $this->mockOutput );
		$this->command->setInputReader( $this->testInput );

		// Setup mock settings in Registry for tests that need it
		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturnCallback( function( $section, $key = null ) {
			if( $section === 'database' && $key === 'driver' ) return 'sqlite';
			if( $section === 'database' && $key === 'name' ) return ':memory:';
			return null;
		});
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $mockSettings );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cms:install', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'Install CMS admin UI into your project', $this->command->getDescription() );
	}

	public function testIsAlreadyInstalledReturnsBool(): void
	{
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'isAlreadyInstalled' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsBool( $result );
	}

	public function testConfigureSqliteCreatesValidConfig(): void
	{
		$this->testInput->addResponse( 'storage/database.sqlite3' ); // Database file path

		$this->mockOutput
			->expects( $this->once() )
			->method( 'writeln' )
			->with( "\n--- SQLite Configuration ---\n" );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureSqlite' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'database', $result );
		$this->assertEquals( 'sqlite', $result['database']['adapter'] );
		$this->assertStringContainsString( 'database.sqlite3', $result['database']['name'] );
	}

	public function testConfigureMysqlWithValidInput(): void
	{
		$this->testInput->addResponse( 'localhost' );      // Host
		$this->testInput->addResponse( '3306' );           // Port
		$this->testInput->addResponse( 'testdb' );         // Database name
		$this->testInput->addResponse( 'testuser' );       // Username
		$this->testInput->addResponse( 'testpass' );       // Password
		$this->testInput->addResponse( 'utf8mb4' );        // Charset

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureMysql' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'database', $result );
		$this->assertEquals( 'mysql', $result['database']['adapter'] );
		$this->assertEquals( 'localhost', $result['database']['host'] );
		$this->assertEquals( 3306, $result['database']['port'] );
		$this->assertEquals( 'testdb', $result['database']['name'] );
		$this->assertEquals( 'testuser', $result['database']['user'] );
		$this->assertEquals( 'testpass', $result['database']['pass'] );
		$this->assertEquals( 'utf8mb4', $result['database']['charset'] );
	}

	public function testConfigureMysqlWithMissingDatabaseName(): void
	{
		$this->testInput->addResponse( 'localhost' );      // Host
		$this->testInput->addResponse( '3306' );           // Port
		$this->testInput->addResponse( '' );               // Empty database name

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Database name is required!' );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureMysql' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertEquals( [], $result );
	}

	public function testConfigureMysqlWithMissingUsername(): void
	{
		$this->testInput->addResponse( 'localhost' );      // Host
		$this->testInput->addResponse( '3306' );           // Port
		$this->testInput->addResponse( 'testdb' );         // Database name
		$this->testInput->addResponse( '' );               // Empty username

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Username is required!' );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureMysql' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertEquals( [], $result );
	}

	public function testConfigurePostgresqlWithValidInput(): void
	{
		$this->testInput->addResponse( 'localhost' );      // Host
		$this->testInput->addResponse( '5432' );           // Port
		$this->testInput->addResponse( 'testdb' );         // Database name
		$this->testInput->addResponse( 'testuser' );       // Username
		$this->testInput->addResponse( 'testpass' );       // Password

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configurePostgresql' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'database', $result );
		$this->assertEquals( 'pgsql', $result['database']['adapter'] );
		$this->assertEquals( 'localhost', $result['database']['host'] );
		$this->assertEquals( 5432, $result['database']['port'] );
		$this->assertEquals( 'testdb', $result['database']['name'] );
		$this->assertEquals( 'testuser', $result['database']['user'] );
		$this->assertEquals( 'testpass', $result['database']['pass'] );
	}

	public function testConfigureApplicationWithValidInput(): void
	{
		$this->testInput->addResponse( 'America/New_York' );  // Timezone
		$this->testInput->addResponse( 'My Site' );           // Site name
		$this->testInput->addResponse( 'My Site Title' );     // Site title
		$this->testInput->addResponse( 'https://example.com' ); // Site URL
		$this->testInput->addResponse( 'Test description' );  // Site description

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureApplication' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertEquals( 'America/New_York', $result['timezone'] );
		$this->assertEquals( 'My Site', $result['siteName'] );
		$this->assertEquals( 'My Site Title', $result['siteTitle'] );
		$this->assertEquals( 'https://example.com', $result['siteUrl'] );
		$this->assertEquals( 'Test description', $result['siteDescription'] );
	}

	public function testConfigureApplicationWithMissingSiteName(): void
	{
		$this->testInput->addResponse( 'America/New_York' );  // Timezone
		$this->testInput->addResponse( '' );                  // Empty site name

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Site name is required!' );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureApplication' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertEquals( [], $result );
	}

	public function testConfigureApplicationWithMissingSiteUrl(): void
	{
		$this->testInput->addResponse( 'America/New_York' );  // Timezone
		$this->testInput->addResponse( 'My Site' );           // Site name
		$this->testInput->addResponse( 'My Site Title' );     // Site title
		$this->testInput->addResponse( '' );                  // Empty site URL

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Site URL is required!' );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureApplication' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertEquals( [], $result );
	}

	public function testConfigureCloudinarySkipped(): void
	{
		$this->testInput->addResponse( 'no' ); // Skip Cloudinary

		$this->mockOutput
			->expects( $this->once() )
			->method( 'info' )
			->with( 'Skipping Cloudinary configuration.' );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureCloudinary' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertEquals( [], $result );
	}

	public function testConfigureCloudinaryWithValidInput(): void
	{
		$this->testInput->addResponse( 'yes' );              // Configure Cloudinary
		$this->testInput->addResponse( 'my-cloud' );         // Cloud name
		$this->testInput->addResponse( '123456789' );        // API key
		$this->testInput->addResponse( 'secret-key' );       // API secret
		$this->testInput->addResponse( 'uploads' );          // Folder
		$this->testInput->addResponse( '10485760' );         // Max file size

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureCloudinary' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'cloudinary', $result );
		$this->assertEquals( 'my-cloud', $result['cloudinary']['cloud_name'] );
		$this->assertEquals( '123456789', $result['cloudinary']['api_key'] );
		$this->assertEquals( 'secret-key', $result['cloudinary']['api_secret'] );
		$this->assertEquals( 'uploads', $result['cloudinary']['folder'] );
		$this->assertEquals( 10485760, $result['cloudinary']['max_file_size'] );
	}

	public function testConfigureCloudinaryWithMissingCloudName(): void
	{
		$this->testInput->addResponse( 'yes' );              // Configure Cloudinary
		$this->testInput->addResponse( '' );                 // Empty cloud name

		$this->mockOutput
			->expects( $this->once() )
			->method( 'warning' )
			->with( 'Cloud name is required for Cloudinary. Skipping configuration.' );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureCloudinary' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertEquals( [], $result );
	}

	public function testConfigureEmailSkipped(): void
	{
		$this->testInput->addResponse( 'no' ); // Skip email

		$this->mockOutput
			->expects( $this->once() )
			->method( 'info' )
			->with( 'Skipping email configuration.' );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureEmail' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertEquals( 'mail', $result['email']['driver'] );
		$this->assertTrue( $result['email']['test_mode'] );
	}

	public function testConfigureEmailWithPhpMail(): void
	{
		$this->testInput->addResponse( 'yes' );                   // Configure email
		$this->testInput->addResponse( 'mail' );                  // Use PHP mail()
		$this->testInput->addResponse( 'noreply@test.com' );     // From address
		$this->testInput->addResponse( 'Test Site' );            // From name
		$this->testInput->addResponse( 'no' );                  // Don't enable test mode

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureEmail' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertEquals( 'mail', $result['email']['driver'] );
		$this->assertEquals( 'noreply@test.com', $result['email']['from_address'] );
		$this->assertEquals( 'Test Site', $result['email']['from_name'] );
		$this->assertFalse( $result['email']['test_mode'] );
	}

	public function testConfigureEmailWithSmtp(): void
	{
		$this->testInput->addResponse( 'yes' );                   // Configure email
		$this->testInput->addResponse( 'smtp' );                  // Use SMTP
		$this->testInput->addResponse( 'smtp.gmail.com' );       // Host
		$this->testInput->addResponse( '587' );                   // Port
		$this->testInput->addResponse( 'tls' );                   // Encryption
		$this->testInput->addResponse( 'test@gmail.com' );       // Username
		$this->testInput->addResponse( 'password123' );           // Password
		$this->testInput->addResponse( 'noreply@test.com' );     // From address
		$this->testInput->addResponse( 'Test Site' );            // From name
		$this->testInput->addResponse( 'yes' );                   // Enable test mode

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureEmail' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertEquals( 'smtp', $result['email']['driver'] );
		$this->assertEquals( 'smtp.gmail.com', $result['email']['host'] );
		$this->assertEquals( 587, $result['email']['port'] );
		$this->assertEquals( 'tls', $result['email']['encryption'] );
		$this->assertEquals( 'test@gmail.com', $result['email']['username'] );
		$this->assertEquals( 'password123', $result['email']['password'] );
		$this->assertTrue( $result['email']['test_mode'] );
	}

	public function testConfigureEmailWithSmtpMissingHost(): void
	{
		$this->testInput->addResponse( 'yes' );                   // Configure email
		$this->testInput->addResponse( 'smtp' );                  // Use SMTP
		$this->testInput->addResponse( '' );                      // Empty host

		$this->mockOutput
			->expects( $this->once() )
			->method( 'warning' )
			->with( 'SMTP host is required. Falling back to test mode.' );

		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'configureEmail' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->command );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertEquals( 'mail', $result['email']['driver'] );
		$this->assertTrue( $result['email']['test_mode'] );
	}

	public function testArrayToYamlConvertsSimpleArray(): void
	{
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'arrayToYaml' );
		$method->setAccessible( true );

		$data = [
			'database' => [
				'adapter' => 'sqlite',
				'name' => 'test.db'
			]
		];

		$result = $method->invoke( $this->command, $data );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'database:', $result );
		$this->assertStringContainsString( 'adapter: sqlite', $result );
		$this->assertStringContainsString( 'name: test.db', $result );
	}

	public function testYamlValueFormatsBoolean(): void
	{
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'yamlValue' );
		$method->setAccessible( true );

		$this->assertEquals( 'true', $method->invoke( $this->command, true ) );
		$this->assertEquals( 'false', $method->invoke( $this->command, false ) );
	}

	public function testYamlValueFormatsInteger(): void
	{
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'yamlValue' );
		$method->setAccessible( true );

		$this->assertEquals( '123', $method->invoke( $this->command, 123 ) );
		$this->assertEquals( '0', $method->invoke( $this->command, 0 ) );
	}

	public function testYamlValueFormatsStringWithSpaces(): void
	{
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'yamlValue' );
		$method->setAccessible( true );

		$this->assertEquals( '"test value"', $method->invoke( $this->command, 'test value' ) );
	}

	public function testYamlValueFormatsStringWithColon(): void
	{
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'yamlValue' );
		$method->setAccessible( true );

		$this->assertEquals( '"test:value"', $method->invoke( $this->command, 'test:value' ) );
	}

	public function testYamlValueFormatsSimpleString(): void
	{
		$reflection = new \ReflectionClass( $this->command );
		$method = $reflection->getMethod( 'yamlValue' );
		$method->setAccessible( true );

		$this->assertEquals( 'simple', $method->invoke( $this->command, 'simple' ) );
	}
}
