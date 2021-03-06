<?php
/**
 *
 */

namespace Elgg;

use Elgg\Database\Seeds\Seedable;
use Elgg\Di\ServiceProvider;
use Elgg\Plugins\PluginTesting;
use Elgg\Project\Paths;
use PHPUnit\Framework\TestCase;

/**
 * Base test case abstraction
 */
abstract class BaseTestCase extends TestCase implements Seedable, Testable {

	use Testing;
	use PluginTesting;

	static $_instance;
	static $_settings;

	public function __construct($name = null, array $data = [], $dataName = '') {
		parent::__construct($name, $data, $dataName);

		self::$_instance = $this;
	}

	public function __destruct() {
		self::$_instance = null;
	}

	/**
	 * Build a new testing application
	 * @return Application|false
	 */
	public static function createApplication() {
		return false;
	}

	/**
	 * Returns testing config
	 * @return Config
	 */
	public static function getTestingConfig() {
		if (!empty($_ENV['ELGG_SETTINGS_FILE'])) {
			$settings_path = $_ENV['ELGG_SETTINGS_FILE'];

			return Config::factory($settings_path);
		}

		return new Config([
			'dbprefix' => getenv('ELGG_DB_PREFIX') ? : 't_i_elgg_',
			'dbname' => getenv('ELGG_DB_NAME') ? : '',
			'dbuser' => getenv('ELGG_DB_USER') ? : '',
			'dbpass' => getenv('ELGG_DB_PASS') ? : '',
			'dbhost' => getenv('ELGG_DB_HOST') ? : 'localhost',
			'dbencoding' => getenv('ELGG_DB_ENCODING') ? : 'utf8mb4',

			'memcache' => (bool) getenv('ELGG_MEMCACHE'),
			'memcache_servers' => [
				[getenv('ELGG_MEMCACHE_SERVER1_HOST'), getenv('ELGG_MEMCACHE_SERVER1_PORT')],
				[getenv('ELGG_MEMCACHE_SERVER2_HOST'), getenv('ELGG_MEMCACHE_SERVER2_PORT')],
			],
			'memcache_namespace_prefix' => getenv('ELGG_MEMCACHE_NAMESPACE_PREFIX') ? : 'elgg_mc_prefix_',

			// These are fixed, because tests rely on specific location of the dataroot for source files
			'wwwroot' => getenv('ELGG_WWWROOT') ? : 'http://localhost/',
			'dataroot' => Paths::elgg() . 'engine/tests/test_files/dataroot/',
			'cacheroot' => Paths::elgg() . 'engine/tests/test_files/cacheroot/',

			'system_cache_enabled' => false,
			'simplecache_enabled' => false,
			'boot_cache_ttl' => 0,

			'profile_files' => [],
			'group' => [],
			'group_tool_options' => [],

			'minusername' => 10,
			'profile_custom_fields' => [],
			'elgg_maintenance_mode' => false,

			'icon_sizes' => [
				'topbar' => [
					'w' => 16,
					'h' => 16,
					'square' => true,
					'upscale' => true
				],
				'tiny' => [
					'w' => 25,
					'h' => 25,
					'square' => true,
					'upscale' => true
				],
				'small' => [
					'w' => 40,
					'h' => 40,
					'square' => true,
					'upscale' => true
				],
				'medium' => [
					'w' => 100,
					'h' => 100,
					'square' => true,
					'upscale' => true
				],
				'large' => [
					'w' => 200,
					'h' => 200,
					'square' => true,
					'upscale' => true
				],
				'master' => [
					'w' => 2048,
					'h' => 2048,
					'square' => false,
					'upscale' => false
				],
			],
			'debug' => 'NOTICE',
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function setUp() {

		Application::setInstance(null);

		$app = static::createApplication();
		if (!$app) {
			$this->markTestSkipped();
		}

		$dt = new \DateTime();

		$app->_services->entityTable->setCurrentTime($dt);
		$app->_services->metadataTable->setCurrentTime($dt);
		$app->_services->relationshipsTable->setCurrentTime($dt);
		$app->_services->annotationsTable->setCurrentTime($dt);
		$app->_services->usersTable->setCurrentTime($dt);

		$app->_services->session->removeLoggedInUser();
		$app->_services->session->setIgnoreAccess(false);
		access_show_hidden_entities(false);

		// Make sure the application has been bootstrapped correctly
		$this->assertInstanceOf(Application::class, elgg(), __METHOD__ . ': Elgg not bootstrapped');
		$this->assertInstanceOf(ServiceProvider::class, $app->_services, __METHOD__ . ': ServiceProvider not bootstrapped');
		$this->assertInstanceOf(Config::class, $app->_services->config, __METHOD__ . ': Config not bootstrapped');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function tearDown() {

		// We do not want overflowing ignored access
		$this->assertFalse((bool) elgg_get_ignore_access(), __METHOD__ . ': ignore acces not reset');

		// We do not want overflowing show hidden status
		$this->assertFalse((bool) access_get_show_hidden_status(), __METHOD__ . ': hidden entities not reset');

		// Tests should run without a logged in user
		$this->assertFalse((bool) elgg_is_logged_in(), __METHOD__ . ': there should be no logged in user');
	}

	/**
	 * Called after setUp() method and can be used by test cases to setup their test logic
	 * @return mixed
	 */
	abstract function up();

	/**
	 * Called before tearDown() method and can be used by test cases to clear their test logic
	 * @return mixed
	 */
	abstract function down();

	/**
	 * @source https://gist.github.com/gnutix/7746893
	 * @return \Doctrine\DBAL\Platforms\AbstractPlatform|\PHPUnit_Framework_MockObject_MockObject
	 */
	public function getDatabasePlatformMock() {
		$mock = $this->getAbstractMock(
			'Doctrine\DBAL\Platforms\AbstractPlatform',
			[
				'getName',
				'getTruncateTableSQL',
			]
		);

		$mock->expects($this->any())
			->method('getName')
			->will($this->returnValue('mysql'));

		$mock->expects($this->any())
			->method('getTruncateTableSQL')
			->with($this->anything())
			->will($this->returnValue('#TRUNCATE {table}'));

		return $mock;
	}

	/**
	 * @source https://gist.github.com/gnutix/7746893
	 * @return \Doctrine\DBAL\Connection|\PHPUnit_Framework_MockObject_MockObject
	 */
	public function getConnectionMock() {
		$mock = $this->getMockBuilder('Doctrine\DBAL\Connection')
			->disableOriginalConstructor()
			->setMethods(
				[
					'beginTransaction',
					'commit',
					'rollback',
					'prepare',
					'query',
					'executeQuery',
					'executeUpdate',
					'getDatabasePlatform',
					'lastInsertId',
					'getExpressionBuilder',
					'quote',
				]
			)
			->getMock();

		$mock->expects($this->any())
			->method('prepare')
			->will($this->returnValue($this->getStatementMock()));

//		$mock->expects($this->any())
//			->method('query')
//			->will($this->returnValue($this->getStatementMock()));

		$mock->expects($this->any())
			->method('getDatabasePlatform')
			->will($this->returnValue($this->getDatabasePlatformMock()));

		return $mock;
	}

	/**
	 * @source https://gist.github.com/gnutix/7746893
	 * @return \Doctrine\DBAL\Driver\Statement|\PHPUnit_Framework_MockObject_MockObject
	 */
	public function getStatementMock() {
		$mock = $this->getAbstractMock(
			'Doctrine\DBAL\Driver\Statement',
			[
				'bindValue',
				'execute',
				'rowCount',
				'fetchColumn',
			]
		);

		$mock->expects($this->any())
			->method('fetchColumn')
			->will($this->returnValue(1));

		return $mock;
	}

	/**
	 * @source https://gist.github.com/gnutix/7746893
	 *
	 * @param string $class   The class name
	 * @param array  $methods The available methods
	 *
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getAbstractMock($class, array $methods) {
		return $this->getMockForAbstractClass(
			$class,
			[],
			'',
			true,
			true,
			true,
			$methods,
			false
		);
	}

}
