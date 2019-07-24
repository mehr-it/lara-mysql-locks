<?php


	namespace MehrIt\LaraMySqlLocks\Facades;


	use Illuminate\Support\Facades\Facade;
	use MehrIt\LaraMySqlLocks\DbLockFactory;

	/**
	 * Class DbLock
	 * @package MehrIt\LaraMySqlLocks\Facades
	 * @method static \MehrIt\LaraMySqlLocks\DbLock lock(string $name, float $timeout, int $ttl, string $boundConnection = null) Acquires a new database lock
	 * @method static mixed withLock(callable $callback, string $name, float $timeout, int $ttl, string $boundConnection = null, int $attempts = 1) Acquires a new database lock for the execution of the given callback
	 * @method static \MehrIt\LaraMySqlLocks\DbWait wait(string $name = null, float $timeout = 0, string $boundConnection = null) Acquires a new wait
	 * @method static void awaitRelease(string $name, float $timeout, string $boundConnection = null) Waits for a given wait to be released
	 * @method static mixed withWait(callable $callback, string $name = null, float $timeout = 0, string $boundConnection = null) Acquires a new new wait for the execution of the given callback
	 */
	class DbLock extends Facade
	{
		/**
		 * Get the registered name of the component.
		 *
		 * @return string
		 */
		protected static function getFacadeAccessor() {
			return DbLockFactory::class;
		}
	}