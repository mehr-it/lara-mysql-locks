<?php


	namespace MehrIt\LaraMySqlLocks;


	use Illuminate\Support\Facades\DB;
	use InvalidArgumentException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockReleaseException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockTimeoutException;
	use Throwable;

	class DbWait
	{

		/**
		 * @var string
		 */
		protected $name;

		/**
		 * @var float
		 */
		protected $timeout;

		/**
		 * @var string|null
		 */
		protected $boundConnection;

		/**
		 * @var float
		 */
		protected $createdAt;

		/**
		 * @var float
		 */
		protected $waited;


		/**
		 * Creates a new database wait. The wait is acquired immediately when calling the constructor!
		 * @param string $name The wait name
		 * @param float $timeout The timeout for wait acquiring in seconds
		 * @param string|null $boundConnection The name of the database connection the wait is bound to. The wait is automatically released when database connection is terminated.
		 * @throws DbLockTimeoutException Thrown if the operation did not succeed due to timeout elapsed
		 */
		public function __construct(string $name, float $timeout, string $boundConnection = null) {

			if (trim($name) === '')
				throw new InvalidArgumentException("Invalid lock name \"{$name}\"");
			if ($timeout < 0)
				throw new InvalidArgumentException("Timeout must not be negative");

			$this->name            = $name;
			$this->timeout         = $timeout;
			$this->boundConnection = $boundConnection;

			$ts = microtime(true);

			// acquire native lock
			if (!$this->awaitNativeLock($this->timeout))
				throw new DbLockTimeoutException($this->name, $this->timeout, "Acquiring DB wait \"{$name}\" timed out after {$timeout} seconds. Session " . $this->getNativeLockingSessionId() . ' still holds it');

			// measure times
			$this->createdAt = microtime(true);
			$this->waited    = $this->createdAt - $ts;
		}

		/**
		 * Releases the wait
		 */
		public function release() {

			try {
				if (!$this->releaseNativeLock())
					throw new DbLockReleaseException($this->name, 'Releasing lock "' . $this->name . '" failed. Probably it has already been released.');
			}
			catch (Throwable $ex) {
				throw new DbLockReleaseException($this->name, 'Releasing lock "' . $this->name . '" failed. Probably it has already been released.', 0 , $ex);
			}

			return $this;
		}

		/**
		 * Gets the connection used
		 * @return string The connection name
		 */
		public function getBoundConnection(): ?string {
			return $this->boundConnection;
		}

		/**
		 * Gets the lock name
		 * @return string The lock name
		 */
		public function getName(): string {
			return $this->name;
		}

		/**
		 * Gets the timeout for acquiring the lock
		 * @return float The the timeout for acquiring the lock in seconds
		 */
		public function getTimeout(): float {
			return $this->timeout;
		}

		/**
		 * Gets the time when the lock was successfully obtained
		 * @return float The time when the lock was successfully obtained
		 */
		public function getCreatedAt(): float {
			return $this->createdAt;
		}

		/**
		 * Gets the time it took to acquire the lock
		 * @return float The time in seconds it took to acquire the lock
		 */
		public function getWaited(): float {
			return $this->waited;
		}


		/**
		 * Wait until the native DB lock is free
		 * @param int $timeout The look timeout
		 * @return bool True if we got the lock. Else false.
		 */
		protected function awaitNativeLock($timeout): bool {
			return $this->sqlBoundConnectionExpressionSelect('get_lock(?, ?)', [$this->nativeLockName(), $timeout]) == 1;
		}

		/**
		 * Releases the native DB lock
		 * @return bool True on success. Else false.
		 */
		protected function releaseNativeLock(): bool {
			return $this->sqlBoundConnectionExpressionSelect('release_lock(?)', [$this->nativeLockName()]) == 1;
		}

		/**
		 * Get the id of the session holding the native DB lock
		 * @return int|null The lock session id or null if not locked
		 */
		protected function getNativeLockingSessionId() {
			return $this->sqlBoundConnectionExpressionSelect('is_used_lock(?)', [$this->nativeLockName()]);
		}

		protected function sqlBoundConnectionExpressionSelect($expression, $bindings = []) {

			return ((array)DB::connection($this->boundConnection)
					->selectOne("select {$expression} as res", $bindings))['res'] ?? null;

		}

		/**
		 * Gets the name for the native MySQL lock
		 * @return string The native MySQL lock name
		 */
		protected function nativeLockName(): string {
			return "w{$this->name}";
		}
	}