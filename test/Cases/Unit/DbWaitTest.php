<?php


	namespace MehrItLaraMySqlLocksTest\Cases\Unit;


	use Illuminate\Support\Facades\DB;
	use MehrIt\LaraMySqlLocks\DbWait;
	use MehrIt\LaraMySqlLocks\Exception\DbLockReleaseException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockTimeoutException;

	class DbWaitTest extends TestCase
	{
		use TestsParallelProcesses;

		/**
		 * Test if lock is released if explicitly done
		 */
		public function testWait_transactionStarted() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(2, function () use ($lockName) {

						$this->assertDurationLessThan(5, function () use ($lockName) {
							try {
								$wait = new DbWait($lockName, 5);
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be released by other process within timeout');
							}
						});

					});

				},
				function ($sh) use ($lockName) {
					// child

					DB::beginTransaction();

					$wait = new DbWait($lockName, 0);

					$this->sendMessage('acquired', $sh);

					sleep(3);

					// explicit release
					$wait->release();

					sleep(2);
				}
			);
		}

		/**
		 * Test if lock is released if explicitly done (other connection)
		 */
		public function testLock_transactionStarted_otherConn() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(2, function () use ($lockName) {

						$this->assertDurationLessThan(5, function () use ($lockName) {
							try {
								$wait = new DbWait($lockName, 10, 'other');
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be released by other process within timeout');
							}
						});

					});

				},
				function ($sh) use ($lockName) {
					// child

					DB::beginTransaction();

					$wait = new DbWait($lockName, 0, 'other');

					$this->sendMessage('acquired', $sh);

					sleep(3);

					// explicit release
					$wait->release();

					sleep(2);
				}
			);
		}

		/**
		 * Test if lock is released if explicitly done
		 */
		public function testLock_explicitRelease() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(2, function () use ($lockName) {

						$this->assertDurationLessThan(5, function () use ($lockName) {
							try {
								$wait = new DbWait($lockName, 5);
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be released by other process within timeout');
							}
						});

					});

				},
				function ($sh) use ($lockName) {
					//child

					$wait = new DbWait($lockName, 0);

					$this->sendMessage('acquired', $sh);

					sleep(3);

					// explicit release
					$wait->release();

					sleep(2);
				}
			);
		}

		/**
		 * Test if lock is released if explicitly done (other Connection)
		 */
		public function testLock_explicitRelease_otherConn() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(2, function () use ($lockName) {

						$this->assertDurationLessThan(5, function () use ($lockName) {
							try {
								$wait = new DbWait($lockName, 5, 'other');
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be released by other process within timeout');
							}
						});

					});

				},
				function ($sh) use ($lockName) {
					//child

					$wait = new DbWait($lockName, 0, 'other');

					$this->sendMessage('acquired', $sh);

					sleep(3);

					// explicit release
					$wait->release();

					sleep(2);
				}
			);
		}

		/**
		 * Test if locks are cleaned immediately if they had the database lock and do not have it anymore
		 */
		public function testLock_implicitReleaseOnProcessEnd() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(1, function () use ($lockName) {
						$this->assertDurationLessThan(4, function () use ($lockName) {
							try {
								$wait = new DbWait($lockName, 4);
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
							}
						});
					});
				},
				function ($sh) use ($lockName) {
					//child

					$wait = new DbWait($lockName, 0);

					$this->sendMessage('acquired', $sh);

					sleep(2);
				}
			);
		}

		/**
		 * Test if locks are cleaned immediately if they had the database lock and do not have it anymore (other Connection)
		 */
		public function testLock_implicitReleaseOnProcessEnd_otherConn() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(1, function () use ($lockName) {
						$this->assertDurationLessThan(4, function () use ($lockName) {
							try {
								$wait = new DbWait($lockName, 4,  'other');
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
							}
						});
					});
				},
				function ($sh) use ($lockName) {
					//child

					$wait = new DbWait($lockName, 0,  'other');

					$this->sendMessage('acquired', $sh);

					sleep(2);
				}
			);
		}
		/**
		 * Test if acquire times out after specified amount of time
		 */
		public function testLock_timesOutAfterGivenTimeout() {

			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationLessThan(4, function () use ($lockName) {

						$this->expectException(DbLockTimeoutException::class);

						$wait = new DbWait($lockName, 3);
					});

				},
				function ($sh) use ($lockName) {
					//child

					$wait = new DbWait($lockName, 0);

					$this->sendMessage('acquired', $sh);

					// avoid implicit release before test finished
					sleep(6);
				}
			);
		}

		/**
		 * Test if acquire times out after specified amount of time (other Connection)
		 */
		public function testLock_timesOutAfterGivenTimeout_otherConn() {

			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationLessThan(4, function () use ($lockName) {

						$this->expectException(DbLockTimeoutException::class);

						$wait = new DbWait($lockName, 3, 'other');
					});

				},
				function ($sh) use ($lockName) {
					//child

					$wait = new DbWait($lockName, 0,  'other');

					$this->sendMessage('acquired', $sh);

					// avoid implicit release before test finished
					sleep(6);
				}
			);
		}

		/**
		 * Test if acquire times out after specified amount of time
		 */
		public function testLock_timesOutAfterGivenTimeout_transactionStarted() {

			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationLessThan(4, function () use ($lockName) {

						$this->expectException(DbLockTimeoutException::class);

						$wait = new DbWait($lockName, 3);
					});

				},
				function ($sh) use ($lockName) {
					//child

					DB::beginTransaction();

					$wait = new DbWait($lockName, 0);

					$this->sendMessage('acquired', $sh);

					// avoid implicit release before test finished
					sleep(6);
				}
			);
		}

		/**
		 * Test if acquire times out after specified amount of time (other connection)
		 */
		public function testLock_timesOutAfterGivenTimeout_transactionStarted_otherConn() {

			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationLessThan(4, function () use ($lockName) {

						$this->expectException(DbLockTimeoutException::class);

						$wait = new DbWait($lockName, 3, 'other');
					});

				},
				function ($sh) use ($lockName) {
					//child

					DB::beginTransaction();

					$wait = new DbWait($lockName, 0, 'other');

					$this->sendMessage('acquired', $sh);

					// avoid implicit release before test finished
					sleep(6);
				}
			);
		}

		/**
		 * Test if lock release returns the lock instance
		 */
		public function testRelease() {

			$lockName = uniqid();

			$lock = new DbWait($lockName, 0);
			$this->assertSame($lock, $lock->release());

		}

		/**
		 * Test if lock release throws exception if not locked anymore
		 */
		public function testRelease_failsIfAlreadyReleased() {

			$lockName = uniqid();


			$lock = new DbWait($lockName, 0);
			$lock->release();

			$this->expectException(DbLockReleaseException::class);


			$lock->release();

		}

		/**
		 * Test if lock release throws exception if not locked anymore (other connection)
		 */
		public function testRelease_failsIfAlreadyReleased_otherConn() {

			$lockName = uniqid();


			$lock = new DbWait($lockName, 0, 'other');
			$lock->release();

			$this->expectException(DbLockReleaseException::class);


			$lock->release();

		}

		/**
		 * Test if lock release throws exception if not locked anymore
		 */
		public function testRelease_failsAfterConnectionLoss() {

			$lockName = uniqid();

			$wait = new DbWait($lockName, 0);

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockReleaseException::class);

			$wait->release();
		}

		/**
		 * Test if lock release throws exception if not locked anymore (other connection)
		 */
		public function testRelease_failsAfterConnectionLoss_otherConn() {

			$lockName = uniqid();

			$wait = new DbWait($lockName, 0, 'other');

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockReleaseException::class);

			$wait->release();
		}

		/**
		 * Test if lock release throws exception if not locked anymore
		 */
		public function testRelease_failsAfterConnectionLossInTransaction() {

			$lockName = uniqid();

			$wait = new DbWait($lockName, 0);

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockReleaseException::class);

			$wait->release();
		}

		/**
		 * Test if lock release throws exception if not locked anymore (other connection)
		 */
		public function testRelease_failsAfterConnectionLossInTransaction_otherConn() {

			$lockName = uniqid();

			$wait = new DbWait($lockName, 0, 'other');

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockReleaseException::class);

			$wait->release();
		}

		public function testGetName() {
			$lockName = uniqid();

			$lock = new DbWait($lockName, 0);

			$this->assertSame($lockName, $lock->getName());
		}

		public function testGetBoundConnection() {
			$lockName = uniqid();

			$lock = new DbWait($lockName, 0, 'other');

			$this->assertSame('other', $lock->getBoundConnection());
		}


		public function testGetTimeout() {
			$lockName = uniqid();

			$lock = new DbWait($lockName, 6);

			$this->assertEquals(6, $lock->getTimeout());
		}

		public function testGetCreatedAt() {
			$lockName = uniqid();

			$lock = new DbWait($lockName, 6);

			$ts = microtime(true);

			$this->assertGreaterThan($ts - 1, $lock->getCreatedAt());
			$this->assertLessThan($ts, $lock->getCreatedAt());
		}

		public function testGetWaited() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$wait = new DbWait($lockName, 5);

					$this->assertGreaterThan(1, $wait->getWaited());

				},
				function ($sh) use ($lockName) {
					// child

					$wait = new DbWait($lockName, 0);

					$this->sendMessage('acquired', $sh);

					sleep(2);

					// explicit release
					$wait->release();

					sleep(1);
				}
			);
		}
	}
