<?php


	namespace MehrIt\LaraMySqlLocks;


	use Illuminate\Support\Str;
	use MehrIt\LaraMySqlLocks\Exception\DbLockAcquireException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockReleaseException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockTimeoutException;

	class DbLockFactory
	{

		/**
		 * Acquires a new database lock
		 * @param string $name The lock name
		 * @param float $timeout The timeout for lock acquiring in seconds
		 * @param int $ttl The maximum TTL for the lock which is established. If the TTL elapses, other processes might take over the lock
		 * @param string|null $boundConnection The name of the database connection the lock is bound to. When lock is expired this is the database session which is terminated causing any transactions to fail.
		 * @return DbLock The acquired lock
		 * @throws DbLockAcquireException Thrown if an unexpected error occurred
		 * @throws DbLockTimeoutException Thrown if the operation did not succeed due to timeout elapsed
		 * @throws DbLockException
		 */
		public function lock(string $name, float $timeout, int $ttl, string $boundConnection = null) : DbLock {
			return new DbLock($name, $timeout, $ttl, $boundConnection);
		}

		/**
		 * Acquires a new database lock for the execution of the given callback
		 * @param callable $callback the callback. Will receive the acquired lock as first parameter
		 * @param string $name The lock name
		 * @param float $timeout The timeout for lock acquiring in seconds
		 * @param int $ttl The maximum TTL for the lock which is established. If the TTL elapses, other processes might take over the lock
		 * @param string|null $boundConnection The name of the database connection the lock is bound to. When lock is expired this is the database session which is terminated causing any transactions to fail.
		 * @param int $attempts The number of attempts for the transaction
		 * @return mixed The callback return
		 * @throws DbLockAcquireException Thrown if an unexpected error occurred
		 * @throws DbLockTimeoutException Thrown if the operation did not succeed due to timeout elapsed
		 * @throws DbLockException
		 * @throws DbLockReleaseException
		 */
		public function withLock(callable $callback, string $name, float $timeout, int $ttl, string $boundConnection = null, int $attempts = 1) {

			return $this->lock($name, $timeout, $ttl, $boundConnection)
				->releaseAfter($callback, $attempts);

		}

	}