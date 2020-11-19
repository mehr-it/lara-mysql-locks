<?php


	namespace MehrIt\LaraMySqlLocks;


	use Illuminate\Database\Connection;
	use Illuminate\Database\Query\Builder;
	use Illuminate\Database\Query\Expression;
	use Illuminate\Database\QueryException;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Str;
	use InvalidArgumentException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockAcquireException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockReleaseException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockRemainingTTLException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockTimeoutException;
	use PDOException;
	use Throwable;

	class DbLock
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
		 * @var int
		 */
		protected $ttl;

		/**
		 * @var string|null
		 */
		protected $boundConnection;

		/**
		 * @var string
		 */
		protected $table = 'database_locks';

		/**
		 * @var float
		 */
		protected $createdAt;

		/**
		 * @var float
		 */
		protected $waited;



		/**
		 * Creates a new database lock. The lock is acquired immediately when calling the constructor!
		 * @param string $name The lock name
		 * @param float $timeout The timeout for lock acquiring in seconds
		 * @param int $ttl The maximum TTL for the lock which is established. If the TTL elapses, other processes might take over the lock
		 * @param string|null $boundConnection The name of the database connection the lock is bound to. When lock is expired this is the database session which is terminated causing any transactions to fail.
		 * @throws DbLockAcquireException Thrown if an unexpected error occurred
		 * @throws DbLockTimeoutException Thrown if the operation did not succeed due to timeout elapsed
		 * @throws DbLockException
		 */
		public function __construct(string $name, float $timeout, int $ttl, string $boundConnection = null) {

			if (trim($name) === '')
				throw new InvalidArgumentException("Invalid lock name \"{$name}\"");
			if ($timeout < 0)
				throw new InvalidArgumentException("Timeout must not be negative");
			if ($ttl < 0)
				throw new InvalidArgumentException("TTL must not be negative");

			$this->name            = $name;
			$this->timeout         = $timeout;
			$this->ttl             = $ttl;
			$this->boundConnection = $boundConnection;

			$ts = microtime(true);

			// acquire lock
			$this->lock();

			// measure times
			$this->createdAt = microtime(true);
			$this->waited    = $this->createdAt - $ts;
		}

		public function __destruct() {

			// We try our best not leaving any orphaned locks.
			// Anyways: if an error occurs, we ignore it as this
			// is just our best effort and not an expectation
			// to rely on
			try {
				if ($this->acquired())
					$this->release();
			}
			catch (Throwable $ex) { }
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
		 * Gets the maximum TTL for the lock which is established. If the TTL elapses, other processes might take over the lock
		 * @return int The maximum TTL for the lock which is established. If the TTL elapses, other processes might take over the lock
		 */
		public function getTtl(): int {
			return $this->ttl;
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
		}/** @noinspection PhpDocMissingThrowsInspection */


		/**
		 * Executes the given callback using a database transaction and releases the lock afterwards
		 * @param callable $callback The callback. Receives the lock instance as argument
		 * @param int $attempts The number of attempts for the transaction
		 * @return mixed The callback return
		 * @throws DbLockException
		 * @throws DbLockReleaseException
		 */
		public function releaseAfter(callable $callback, int $attempts = 1) {

			if (!$this->acquired())
				throw new DbLockException($this->name, "The lock \"{$this->name}\" is not acquired anymore.");

			try {
				/** @noinspection PhpUnhandledExceptionInspection */
				return DB::connection($this->boundConnection)
					->transaction(function() use ($callback) {
						return call_user_func($callback, $this);
					}, $attempts);
			}
			finally {
				$this->release();
			}
		}/** @noinspection PhpDocMissingThrowsInspection */

		/**
		 * Releases the lock
		 * @return DbLock
		 * @throws DbLockReleaseException
		 */
		public function release() {

			$ts        = microtime(true);
			$tsDiffStr = number_format($ts - $this->createdAt, 3, '.', '');

			// deregister lock
			try {
				$released = $this->deregisterLock($this->connectionId(), $nativeLockKey);

				// release native lock
				if ($released) {
					/** @noinspection PhpStatementHasEmptyBodyInspection */
					while ($this->releaseNativeLock($nativeLockKey)) {
					}
				}
			}
			catch (Throwable $ex) {
				if ($this->causedByLostConnection($ex))
					throw new DbLockReleaseException($this->name, "Releasing lock {$this->name} after {$tsDiffStr}s failed. {$ex->getMessage()}", 0, $ex);

				/** @noinspection PhpUnhandledExceptionInspection */
				throw $ex;
			}

			// throw exception if could not be released
			if (!$released)
				throw new DbLockReleaseException($this->name, "Failed releasing lock {$this->name} after {$tsDiffStr}s. It does not exist anymore.");


			return $this;
		}
		/** @noinspection PhpDocMissingThrowsInspection */

		/**
		 * Checks if the lock is acquired. Not the lock may immediately be lost after calling this function. Use remainsAcquiredFor() to be sure
		 * lock is held for at least a given period of time
		 * @return bool True if lock was acquired when the call was executed. Else false.
		 */
		public function acquired(): bool {
			/** @noinspection PhpUnhandledExceptionInspection */
			return $this->remainsAcquiredFor(0);
		}/** @noinspection PhpDocMissingThrowsInspection */

		/**
		 * Checks if the lock remains acquired for at least the specified time before it might be taken over by another process
		 * @param int $minTTL The minimum remaining TTL in seconds
		 * @return bool True if remains acquired for at least the specified time. Else false.
		 */
		public function remainsAcquiredFor($minTTL) : bool {

			try {
				return $this->withFreshConnection(function ($fresh) use ($minTTL) {
					$grammar = $this->queryGrammar();

					return ((array)DB::connection($fresh)
							->table($this->table)
							->select(new Expression('1 as res'))
							->where('name', '=', $this->name)
							->where('connection_id', '=', $this->connectionId())
							->whereRaw(new Expression($grammar->wrap('created') . ' + ' . $grammar->wrap('ttl') . ' > unix_timestamp() + ?'), [$minTTL])
							->first())['res'] ?? null == 1;
				});
			}
			catch (Throwable $ex) {
				if ($this->causedByLostConnection($ex))
					return 0;

				/** @noinspection PhpUnhandledExceptionInspection */
				throw $ex;
			}
		}/** @noinspection PhpDocMissingThrowsInspection */

		/**
		 * Asserts that the lock remains acquired for at least the specified time. If not an DbLockRemainingTTLException is thrown
		 * @param int $minTTL The minimum remaining TTL in seconds
		 * @return $this
		 * @throws DbLockRemainingTTLException
		 */
		public function assertAcquiredFor($minTTL) {

			/** @noinspection PhpUnhandledExceptionInspection */
			if (!$this->remainsAcquiredFor($minTTL))
				throw new DbLockRemainingTTLException($this->name, $minTTL);

			return $this;
		}


		/**
		 * Acquires the lock
		 * @throws DbLockTimeoutException
		 * @throws DbLockAcquireException
		 */
		protected function lock() {

			$startTime = microtime(true);

			do {
				// try to get lock
				$acquiredNativeLock = $this->getLock();

				if (!$acquiredNativeLock) {

					// try to clean obsolete locks
					if ($this->cleanLock()) {

						// s.th. was cleaned => continue without waiting
						continue;
					}

					// break if timeout exceeded
					if (microtime(true) >= $startTime + $this->timeout)
						break;


					// sleep until lock release (timeout when lock expires or when timeout exceeded)
					$remainingTimeout = ceil($this->timeout - (microtime(true) - $startTime));
					$remainingTTL     = $this->getExistingLockTTL();

					$this->sleepUntilNativeLockRelease($acquiredNativeLock, min($remainingTimeout, $remainingTTL));
				}

			} while (!$acquiredNativeLock);


			// throw exception if not acquired
			if (!$acquiredNativeLock)
				throw new DbLockTimeoutException($this->name, $this->timeout, $this->getExistingLockTTL());

		}


		/** @noinspection PhpDocMissingThrowsInspection */
		/**
		 * Tries to get the lock
		 * @return string|false The native lock id if got lock. Else false.
		 * @throws DbLockAcquireException
		 */
		protected function getLock() {

			$connectionId = $this->connectionId();

			// try to register lock
			if (!$this->registerLock($connectionId, $nativeLockKey))
				return false;


			// we are the lock holder, so let's also get the native lock
			try {
				if (!$this->awaitNativeLock($nativeLockKey,0)) {    /* we don't wait, since no other process is legitimated to have the lock */

					$sessionId = $this->getNativeLockingSessionId($nativeLockKey);

					throw new DbLockAcquireException($this->name, "Could not acquire lock \"{$this->name}\". Session {$sessionId} holds the native lock \"{$nativeLockKey}\".");
				}

				// mark the lock as acquired (this mark is used on cleaning to detect died processes faster)
				if (!$this->markLockAcquired($connectionId)) {
					throw new DbLockAcquireException($this->name, "Could not acquire lock \"{$this->name}\". Could not mark database lock as acquired.");
				}
			}
			catch (Throwable $ex) {
				// s.th. went wrong => we do not have the lock, so deregister it

				// we try to deregister
				try {
					$this->deregisterLock($connectionId);
				}
				catch (Throwable $ex) {
				}


				/** @noinspection PhpUnhandledExceptionInspection */
				throw $ex;
			}

			return $nativeLockKey;
		}

		/**
		 * Register the lock ownership
		 * @param $connectionId
		 * @param string|null $nativeLockKey Returns the key for the native lock
		 * @return bool True on success. False on error.
		 */
		protected function registerLock($connectionId, &$nativeLockKey = null) {
			try {
				return $this->withFreshConnection(function($fresh) use ($connectionId, &$nativeLockKey) {

					$nKey = $this->name . '_' . uniqid();

					$ret = DB::connection($fresh)
						->table($this->table)
						->insert([
							'name'            => $this->name,
							'created'         => new Expression('unix_timestamp()'),
							'ttl'             => $this->ttl,
							'connection_id'   => $connectionId,
							'lock_acquired'   => false,
							'native_lock_key' => $nKey,
						]);

					$nativeLockKey = $nKey;
					return $ret;
				});
			}
			catch (PDOException $ex) {

				// duplicate key [23000] exception is expected here, so simply return false if thrown
				if ($ex->getCode() == 23000)
					return false;
				// deadlock [40001] exception may sometimes occur, but should not bother as we simply can retry
				if ($ex->getCode() == 40001) {

					// for debugging deadlocks:
					//echo 'Dead lock ' . $GLOBALS['count'] . '  ' . microtime(true) . "\n";

					return false;
				}

				throw $ex;
			}
		}

		/**
		 * Deregisters the specified lock
		 * @param int $connectionId The connection id
		 * @param string|null $nativeLockKey Returns the native lock id
		 * @return boolean True if lock was released. False if there was nothing to release
		 */
		protected function deregisterLock($connectionId, &$nativeLockKey = null) {

			return $this->withFreshConnection(function($fresh) use ($connectionId, &$nativeLockKey) {
				$existing = (array)DB::connection($fresh)
					->table($this->table)
					->select()
					->where('name', '=', $this->name)
					->where('connection_id', '=', $connectionId)
					->first();

				if ($existing) {

					$nativeLockKey = $existing['native_lock_key'];

					DB::connection($fresh)
						->table($this->table)
						->select()
						->where('name', '=', $this->name)
						->where('connection_id', '=', $connectionId)
						->delete();

					return true;
				}
				else {
					return false;
				}
			});
		}

		/**
		 * Wait until the native DB lock is free
		 * @param string $nativeLockKey The key of the native lock
		 * @param int $timeout The look timeout
		 * @return bool True if we got the lock. Else false.
		 */
		protected function awaitNativeLock(string $nativeLockKey, int $timeout) : bool {
			return $this->sqlBoundConnectionExpressionSelect('get_lock(?, ?)', [$nativeLockKey, $timeout]) == 1;
		}

		/**
		 * Releases the native DB lock
		 * @param string $nativeLockKey The key of the native lock
		 * @return bool True on success. Else false.
		 */
		protected function releaseNativeLock(string $nativeLockKey): bool {
			return $this->sqlBoundConnectionExpressionSelect('release_lock(?)', [$nativeLockKey]) == 1;
		}

		/**
		 * Get the id of the session holding the native DB lock
		 * @param string $nativeLockKey The key of the native lock
		 * @return int|null The lock session id or null if not locked
		 */
		protected function getNativeLockingSessionId(string $nativeLockKey) {
			return $this->sqlBoundConnectionExpressionSelect('is_used_lock(?)', [$nativeLockKey]);
		}

		/**
		 * Kills the session with the specified id
		 * @param int $sessionId The session id
		 */
		protected function killSession($sessionId) {

			$this->sqlAffecting('KILL ?', [$sessionId]);
		}

		/**
		 * Marks the database lock as acquired. This mark is used on cleaning to detect died processes faster
		 * @return bool True if marked. Else false
		 */
		protected function markLockAcquired($connectionId) {

			return $this->withFreshConnection(function($fresh) use ($connectionId) {
				return DB::connection($fresh)
					       ->table($this->table)
					       ->where('name', '=', $this->name)
					       ->where('connection_id', '=', $connectionId)
					       ->update(['lock_acquired' => true]) == 1;
			});
		}

		/**
		 * Gets the remaining TTL for the currently existing lock
		 * @return int The remaining TTL for the currently existing lock in seconds. If no lock exists, 0 is returned
		 */
		protected function getExistingLockTTL() : int {

			return $this->withFreshConnection(function($fresh) {
				$grammar = $this->queryGrammar();

				return (((array)DB::connection($fresh)
							->table($this->table)
					        ->select(new Expression($grammar->wrap('created') . ' + ' . $grammar->wrap('ttl') . ' - unix_timestamp() as ttl'))
					        ->where('name', '=', $this->name)
					        ->first())['ttl'] ?? null) ?: 0;
			});
		}

		/**
		 * Removes obsolete locks
		 * @return boolean True if there was an obsolete lock removed. Else false.
		 */
		protected function cleanLock() {



			return $this->withFreshConnection(function($fresh) {

				$grammar = $this->queryGrammar();

				$expired = (array)DB::connection($fresh)
					->table($this->table)
					->select()
					->where('name', '=', $this->name)
					->where(function ($query) use ($grammar) {
						/** @var Builder $query */
						$query
							->where(new Expression($grammar->wrap('created') . ' + ' . $grammar->wrap('ttl')), '<', new Expression('unix_timestamp()'))
							->orWhereRaw('(' . $grammar->wrap('lock_acquired') . ' and ifnull(is_used_lock(' . $grammar->wrap('native_lock_key') . '), 0) != ' . $grammar->wrap('connection_id') . ')');
					})
					->first();

				if ($expired) {

					logger()->debug("Cleaning up expired session holding lock \"{$this->name}\".", $expired);

					// kill expired session
					$expiredSession = $this->getNativeLockingSessionId($expired['native_lock_key']);
					if ($expiredSession)
						$this->killSession($expiredSession);

					// delete lock table entry
					DB::connection($fresh)
						->table($this->table)
						->where('name', '=', $this->name)
						->where('native_lock_key', $expired['native_lock_key'])
						->delete();

					return true;
				}
				else {
					return false;
				}
			});


		}

		/**
		 * Lets the process sleep until specified lock is released or timeout is elapsed
		 * @param string $nativeLockKey The native lock key
		 * @param int $timeout The timeout
		 * @return bool True if lock was released. False if timed out
		 */
		protected function sleepUntilNativeLockRelease(string $nativeLockKey, int $timeout) : bool {

			// try to get lock
			$gotLock = $this->awaitNativeLock($nativeLockKey, $timeout);

			// release lock if we got it (we only wanted to wait until it was released)
			if ($gotLock)
				$this->releaseNativeLock($nativeLockKey);

			return $gotLock;
		}

		protected function sqlBoundConnectionExpressionSelect($expression, $bindings = []) {

			return ((array)DB::connection($this->boundConnection)
				->selectOne("select {$expression} as res", $bindings))['res'] ?? null;

		}

		protected function sqlAffecting($query, $bindings) {

			return DB::affectingStatement($query, $bindings);

		}


		/**
		 * Gets the query grammar
		 * @return \Illuminate\Database\Query\Grammars\Grammar
		 */
		protected function queryGrammar() {
			/** @var Connection $connection */
			$connection = DB::connection();
			return $connection->getQueryGrammar();
		}

		/**
		 * Gets the connection id of the bound connection
		 * @return int The connection id
		 */
		protected function connectionId() {
			return $this->sqlBoundConnectionExpressionSelect('connection_id()');
		}

		/**
		 * Determine if the given exception was caused by a lost connection.
		 *
		 * @param \Throwable $e
		 * @return bool
		 */
		protected function causedByLostConnection(Throwable $e) {
			$message = $e->getMessage();

			return Str::contains($message, [
				'server has gone away',
				'no connection to the server',
				'Lost connection',
				'is dead or not enabled',
				'Error while sending',
				'decryption failed or bad record mac',
				'server closed the connection unexpectedly',
				'SSL connection has been closed unexpectedly',
				'Error writing data to the connection',
				'Resource deadlock avoided',
				'Transaction() on null',
				'child connection forced to terminate due to client_idle_limit',
				'query_wait_timeout',
				'reset by peer',
				'Physical connection is not usable',
				'TCP Provider: Error code 0x68',
				'ORA-03114',
				'Packets out of order. Expected',
				'Adaptive Server connection failed',
				'Communication link failure',
			]);
		}

		/**
		 * Executes the given callback with a temporary fresh copy of the connection
		 * @param callable $callback The callback
		 * @return mixed The callback return
		 */
		protected function withFreshConnection(callable $callback) {

			/** @var Connection $conn */
			$conn = DB::connection();

			// set the temporary configuration and create connection
			$freshName = "{$conn->getName()}-copy-" . uniqid();

			return $this->withConnectionConfig($freshName, $conn->getConfig(), function () use ($freshName, $callback) {

				try {
					return call_user_func($callback, $freshName);
				}
				finally {


					// instantly close connection => this seams to help us to protect from deadlocks on insert
					try {
						DB::connection($freshName)->affectingStatement('kill connection_id()');
					}
					catch(QueryException $ex) {}


					// purge the connection after usage - we do not need it again
					/** @noinspection PhpUndefinedMethodInspection */
					DB::purge($freshName);
				}
			});

		}

		/**
		 * Configures given database connection only during callback execution
		 * @param string $name The connection name
		 * @param array $config The config
		 * @param callable $callback The callback
		 * @return mixed The callback return
		 */
		protected function withConnectionConfig($name, $config, callable $callback) {
			config()->set("database.connections.$name", $config);
			try {
				return call_user_func($callback);
			}
			finally {
				config()->set("database.connections.$name", null);
			}
		}

		/**
		 * Gets the name for the native MySQL lock
		 * @return string The native MySQL lock name
		 */
		protected function nativeLockName() : string {
			return "l{$this->name}";
		}
	}