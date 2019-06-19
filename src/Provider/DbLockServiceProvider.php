<?php


	namespace MehrIt\LaraMySqlLocks\Provider;


	use Illuminate\Support\ServiceProvider;
	use MehrIt\LaraMySqlLocks\DbLockFactory;


	class DbLockServiceProvider extends ServiceProvider
	{

		protected $defer = true;


		/**
		 * All of the container singletons that should be registered.
		 *
		 * @var array
		 */
		public $singletons = [
			DbLockFactory::class => DbLockFactory::class,
		];

		/**
		 * Bootstrap the application services.
		 *
		 * @return void
		 */
		public function boot() {

			if ($this->app->runningInConsole()) {
				// migrations
				$this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
			}

		}

		/**
		 * Get the services provided by the provider.
		 *
		 * @return array
		 */
		public function provides() {
			return [
				DbLockFactory::class,
			];
		}

	}