<?php


	namespace MehrItLaraMySqlLocksTest\Cases\Unit;


	use Illuminate\Support\Facades\DB;
	use MehrIt\LaraMySqlLocks\Provider\DbLockServiceProvider;

	abstract class TestCase extends \Orchestra\Testbench\TestCase
	{
		protected static $migrationsRun = false;

		protected function getPackageProviders($app) {

			return [
				DbLockServiceProvider::class,
			];
		}

		/**
		 * Define environment setup.
		 *
		 * @param \Illuminate\Foundation\Application $app
		 * @return void
		 */
		protected function getEnvironmentSetUp($app) {
			// Configure a clone of our default connection, so we can test with two independent connections
			$app['config']->set('database.connections.other', $app['config']->get('database.connections.' . $app['config']->get('database.default')));
		}

		protected function setUp() {
			parent::setUp();

			DB::reconnect();
			DB::reconnect('other');

			// run migrations
			if (!static::$migrationsRun) {

				// migrations
				$this->artisan('migrate')->run();

				static::$migrationsRun = true;
			}

			// clear locks table
			DB::table('database_locks')->delete();
		}

		/**
		 * Mocks an instance in the application service container
		 * @param string $instance The instance to mock
		 * @param string|null $mockedClass The class to use for creating a mock object. Null to use same as $instance
		 * @return \PHPUnit\Framework\MockObject\MockObject
		 */
		protected function mockAppSingleton($instance, $mockedClass = null) {

			if (!$mockedClass)
				$mockedClass = $instance;

			$mock = $this->getMockBuilder($mockedClass)->disableOriginalConstructor()->getMock();
			app()->singleton($instance, function () use ($mock) {
				return $mock;
			});

			return $mock;
		}


	}