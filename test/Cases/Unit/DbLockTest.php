<?php


	namespace MehrItLaraMySqlLocksTest\Cases\Unit;


	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Str;
	use MehrIt\LaraMySqlLocks\DbLock;
	use MehrIt\LaraMySqlLocks\Exception\DbLockException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockReleaseException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockRemainingTTLException;
	use MehrIt\LaraMySqlLocks\Exception\DbLockTimeoutException;

	class DbLockTest extends TestCase
	{
		use TestsParallelProcesses;

		public function testLock_acquiredLongerThanTTLIfNotAcquiredByAnotherProcess() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 1);

			sleep(2);

			$lock->release();

			$this->expectNotToPerformAssertions();
		}


		/**
		 * Test if lock is released if explicitly done
		 */
		public function testLock_transactionStarted() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(2, function () use ($lockName) {

						$this->assertDurationLessThan(5, function () use ($lockName) {
							try {
								$lock = new DbLock($lockName, 5, 10);
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

					$lock = new DbLock($lockName, 0, 10);

					$this->sendMessage('acquired', $sh);

					sleep(3);

					// explicit release
					$lock->release();

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
								$lock = new DbLock($lockName, 5, 10, 'other');
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

					$lock = new DbLock($lockName, 0, 10, 'other');

					$this->sendMessage('acquired', $sh);

					sleep(3);

					// explicit release
					$lock->release();

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

					$this->assertDurationGreaterThan(2, function() use ($lockName) {

						$this->assertDurationLessThan(5, function () use ($lockName) {
							try {
								$lock = new DbLock($lockName, 5, 10);
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be released by other process within timeout');
							}
						});

					});

				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLock($lockName, 0, 10);

					$this->sendMessage('acquired', $sh);

					sleep(3);

					// explicit release
					$lock->release();

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

					$this->assertDurationGreaterThan(2, function() use ($lockName) {

						$this->assertDurationLessThan(5, function () use ($lockName) {
							try {
								$lock = new DbLock($lockName, 5, 10, 'other');
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be released by other process within timeout');
							}
						});

					});

				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLock($lockName, 0, 10, 'other');

					$this->sendMessage('acquired', $sh);

					sleep(3);

					// explicit release
					$lock->release();

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

					$this->assertDurationGreaterThan(1, function() use ($lockName) {
						$this->assertDurationLessThan(4, function () use ($lockName) {
							try {
								$lock = new DbLock($lockName, 4, 10);
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
							}
						});
					});
				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLock($lockName, 0, 10);

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

					$this->assertDurationGreaterThan(1, function() use ($lockName) {
						$this->assertDurationLessThan(4, function () use ($lockName) {
							try {
								$lock = new DbLock($lockName, 4, 10, 'other');
							}
							catch (DbLockTimeoutException $ex) {
								$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
							}
						});
					});
				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLock($lockName, 0, 10, 'other');

					$this->sendMessage('acquired', $sh);

					sleep(2);
				}
			);
		}

		/**
		 * Test if locks are cleaned (even if wait timeout 0) if they had the database lock and do not have it anymore
		 */
		public function testLock_implicitReleaseOnProcessEnd_timeout0() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					sleep(1);

					$this->assertDurationLessThan(3, function () use ($lockName) {
						try {
							$lock = new DbLock($lockName, 0, 10);
						}
						catch (DbLockTimeoutException $ex) {
							$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
						}
					});

				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLock($lockName, 0, 10);

					$this->sendMessage('acquired', $sh);

				}
			);
		}

		/**
		 * Test if locks are cleaned (even if wait timeout 0) if they had the database lock and do not have it anymore (other Connection)
		 */
		public function testLock_implicitReleaseOnProcessEnd_timeout0_otherConn() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					sleep(1);

					$this->assertDurationLessThan(3, function () use ($lockName) {
						try {
							$lock = new DbLock($lockName, 0, 10, 'other');
						}
						catch (DbLockTimeoutException $ex) {
							$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
						}
					});

				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLock($lockName, 0, 10, 'other');

					$this->sendMessage('acquired', $sh);

				}
			);
		}

		/**
		 * Test if locks are cleaned after their TTL if they did not have the database lock
		 */
		public function testLock_isCleanedAfterTTL_whenProcessNotHavingLockAnymore() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(6, function () use ($lockName) {
						try {
							$lock = new DbLock($lockName, 10, 10);
						}
						catch (DbLockTimeoutException $ex) {
							$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
						}
					});

				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLockWithoutDestructRelease($lockName, 0, 8);

					// simulate that the database lock was not hold
					if (DB::table('database_locks')
						->where('name', '=', $lockName)
						->update(['lock_acquired' => false]) != 1)
					{
						$this->fail('Test-Scenario-Error: Simulating lock_acquired = false failed');
					}

					$this->sendMessage('acquired', $sh);


					sleep(1);
				}
			);
		}

		/**
		 * Test if locks are cleaned after their TTL if they did not have the database lock (other Connection)
		 */
		public function testLock_isCleanedAfterTTL_whenProcessNotHavingLockAnymore_otherConn() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					$this->assertDurationGreaterThan(6, function () use ($lockName) {
						try {
							$lock = new DbLock($lockName, 10, 10, 'other');
						}
						catch (DbLockTimeoutException $ex) {
							$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
						}
					});

				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLockWithoutDestructRelease($lockName, 0, 8, 'other');

					// simulate that the database lock was not hold
					if (DB::table('database_locks')
						->where('name', '=', $lockName)
						->update(['lock_acquired' => false]) != 1)
					{
						$this->fail('Test-Scenario-Error: Simulating lock_acquired = false failed');
					}

					$this->sendMessage('acquired', $sh);


					sleep(1);
				}
			);
		}

		/**
		 * Test if database session is killed when lock taken over by another process
		 */
		public function testLock_DatabaseSessionIsKilledWhenLockTakenOver() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					try {
						$lock = new DbLock($lockName, 3, 10);

					}
					catch (DbLockTimeoutException $ex) {
						$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
					}

					$this->assertNextMessage('connection lost', $sh, 1000000 * 5);


				},
				function ($sh) use ($lockName) {
					//child

					DB::beginTransaction();

					$lock = new DbLockWithoutDestructRelease($lockName, 0, 1);

					$this->sendMessage('acquired', $sh);

					sleep(4);


					try {
						//print_r(DB::select('SELECT connection_id()'));

						DB::select('SELECT 1');

						$this->sendMessage('connection still intact', $sh);
					}
					catch (\PDOException $ex) {


						if (Str::contains($ex->getMessage(), ['MySQL server has gone away', 'Error while sending STMT_PREPARE packet']))
							$this->sendMessage('connection lost', $sh);
						else
							$this->sendMessage('received unexpected PDO error: ' . $ex->getMessage(), $sh);

					}

					sleep(1);

				}
			);
		}

		/**
		 * Test if database session is killed when lock taken over by another process (other Connection)
		 */
		public function testLock_DatabaseSessionIsKilledWhenLockTakenOver_otherConn() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					try {
						$lock = new DbLock($lockName, 3, 10, 'other');

					}
					catch (DbLockTimeoutException $ex) {
						$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
					}

					$this->assertNextMessage('connection lost', $sh, 1000000 * 5);


				},
				function ($sh) use ($lockName) {
					//child

					DB::connection('other')->beginTransaction();

					$lock = new DbLockWithoutDestructRelease($lockName, 0, 1, 'other');

					$this->sendMessage('acquired', $sh);

					sleep(4);


					try {

						DB::connection('other')->select('SELECT 1');

						$this->sendMessage('connection still intact', $sh);
					}
					catch (\PDOException $ex) {


						if (Str::contains($ex->getMessage(), ['MySQL server has gone away', 'Error while sending STMT_PREPARE packet']))
							$this->sendMessage('connection lost', $sh);
						else
							$this->sendMessage('received unexpected PDO error: ' . $ex->getMessage(), $sh);

					}

					sleep(1);

				}
			);
		}

		/**
		 * Test if release fails when lock taken over by another process
		 */
		public function testLock_releaseFailsWhenLockTakenOver() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					try {
						$lock = new DbLock($lockName, 3, 10);

					}
					catch (DbLockTimeoutException $ex) {
						$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
					}

					$this->assertNextMessage('release failure', $sh, 1000000 * 5);


				},
				function ($sh) use ($lockName) {
					//child


					$lock = new DbLockWithoutDestructRelease($lockName, 0, 1);

					$this->sendMessage('acquired', $sh);

					sleep(4);


					try {

						$lock->release();

						$this->sendMessage('release success', $sh);
					}
					catch (DbLockReleaseException $ex) {

						$this->sendMessage('release failure', $sh);

					}

					sleep(1);

				}
			);
		}

		/**
		 * Test if release fails when lock taken over by another process (other Connection)
		 */
		public function testLock_releaseFailsWhenLockTakenOver_otherConn() {
			$lockName = uniqid();

			$this->fork(
				function ($sh) use ($lockName) {
					// parent

					// wait for lock to be acquired
					$this->assertNextMessage('acquired', $sh);

					try {
						$lock = new DbLock($lockName, 3, 10, 'other');

					}
					catch (DbLockTimeoutException $ex) {
						$this->fail('Lock was not acquired but should be cleaned since holding process already should have died');
					}

					$this->assertNextMessage('release failure', $sh, 1000000 * 5);


				},
				function ($sh) use ($lockName) {
					//child


					$lock = new DbLockWithoutDestructRelease($lockName, 0, 1, 'other');

					$this->sendMessage('acquired', $sh);

					sleep(4);


					try {

						$lock->release();

						$this->sendMessage('release success', $sh);
					}
					catch (DbLockReleaseException $ex) {

						$this->sendMessage('release failure', $sh);

					}

					sleep(1);

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

						$lock = new DbLock($lockName, 3, 10);
					});

				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLock($lockName, 0, 10);

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

						$lock = new DbLock($lockName, 3, 10, 'other');
					});

				},
				function ($sh) use ($lockName) {
					//child

					$lock = new DbLock($lockName, 0, 10, 'other');

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

						$lock = new DbLock($lockName, 3, 10);
					});

				},
				function ($sh) use ($lockName) {
					//child

					DB::beginTransaction();

					$lock = new DbLock($lockName, 0, 10);

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

						$lock = new DbLock($lockName, 3, 10, 'other');
					});

				},
				function ($sh) use ($lockName) {
					//child

					DB::beginTransaction();

					$lock = new DbLock($lockName, 0, 10, 'other');

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

			$lock = new DbLock($lockName, 0, 10);
			$this->assertSame($lock, $lock->release());

		}

		/**
		 * Test if lock release throws exception if not locked anymore
		 */
		public function testRelease_failsIfAlreadyReleased() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);
			$lock->release();

			$this->expectException(DbLockReleaseException::class);


			$lock->release();

		}

		/**
		 * Test if lock release throws exception if not locked anymore (other connection)
		 */
		public function testRelease_failsIfAlreadyReleased_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');
			$lock->release();

			$this->expectException(DbLockReleaseException::class);


			$lock->release();

		}

		/**
		 * Test if lock release throws exception if not locked anymore
		 */
		public function testRelease_failsIfNotAcquiredAnymore() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$this->expectException(DbLockReleaseException::class);


			$lock->release();

		}

		/**
		 * Test if lock release throws exception if not locked anymore (other connection)
		 */
		public function testRelease_failsIfNotAcquiredAnymore_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$this->expectException(DbLockReleaseException::class);


			$lock->release();

		}

		/**
		 * Test if lock release throws exception if not locked anymore
		 */
		public function testRelease_failsAfterConnectionLoss() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockReleaseException::class);

			$lock->release();
		}

		/**
		 * Test if lock release throws exception if not locked anymore (other connection)
		 */
		public function testRelease_failsAfterConnectionLoss_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockReleaseException::class);

			$lock->release();
		}

		/**
		 * Test if lock release throws exception if not locked anymore
		 */
		public function testRelease_failsAfterConnectionLossInTransaction() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

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

			$lock->release();
		}

		/**
		 * Test if lock release throws exception if not locked anymore (other connection)
		 */
		public function testRelease_failsAfterConnectionLossInTransaction_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

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

			$lock->release();
		}

		public function testRemainsAcquiredFor() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			$this->assertTrue($lock->remainsAcquiredFor(8));
		}

		public function testRemainsAcquiredFor_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			$this->assertTrue($lock->remainsAcquiredFor(8));
		}

		public function testRemainsAcquiredFor_notEnoughRemaining() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			$this->assertFalse($lock->remainsAcquiredFor(11));
		}

		public function testRemainsAcquiredFor_notEnoughRemaining_otherCon() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			$this->assertFalse($lock->remainsAcquiredFor(11));
		}

		public function testRemainsAcquiredFor_alreadyReleased() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);
			$lock->release();

			$this->assertFalse($lock->remainsAcquiredFor(1));
		}

		public function testRemainsAcquiredFor_alreadyReleased_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');
			$lock->release();

			$this->assertFalse($lock->remainsAcquiredFor(1));
		}

		public function testRemainsAcquiredFor_notLockedAnymore() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$this->assertFalse($lock->remainsAcquiredFor(1));
		}

		public function testRemainsAcquiredFor_notLockedAnymore_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$this->assertFalse($lock->remainsAcquiredFor(1));
		}

		public function testRemainsAcquiredFor_connectionLoss() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->assertFalse($lock->remainsAcquiredFor(1));
		}

		public function testRemainsAcquiredFor_connectionLoss_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->assertFalse($lock->remainsAcquiredFor(1));
		}

		public function testRemainsAcquiredFor_connectionLossInTransaction() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->assertFalse($lock->remainsAcquiredFor(1));
		}

		public function testRemainsAcquiredFor_connectionLossInTransaction_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->assertFalse($lock->remainsAcquiredFor(1));
		}


		public function testAssertAcquiredFor() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			$this->assertSame($lock, $lock->assertAcquiredFor(9));
		}

		public function testAssertAcquiredFor_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			$this->assertSame($lock, $lock->assertAcquiredFor(9));
		}

		public function testAssertAcquiredFor_notEnoughRemaining() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(11);
		}

		public function testAssertAcquiredFor_notEnoughRemaining_otherCon() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(11);
		}

		public function testAssertAcquiredFor_alreadyReleased() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);
			$lock->release();

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(1);
		}

		public function testAssertAcquiredFor_alreadyReleased_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');
			$lock->release();

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(1);
		}

		public function testAssertAcquiredFor_notLockedAnymore() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(1);
		}

		public function testAssertAcquiredFor_notLockedAnymore_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(1);
		}

		public function testAssertAcquiredFor_connectionLoss() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(1);
		}

		public function testAssertAcquiredFor_connectionLoss_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(1);
		}

		public function testAssertAcquiredFor_connectionLossInTransaction() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(1);
		}

		public function testAssertAcquiredFor_connectionLossInTransaction_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->expectException(DbLockRemainingTTLException::class);

			$lock->assertAcquiredFor(1);
		}

		public function testAcquired() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			$this->assertTrue($lock->acquired());
		}

		public function testAcquired_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			$this->assertTrue($lock->acquired());
		}


		public function testAcquired_alreadyReleased() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);
			$lock->release();

			$this->assertFalse($lock->acquired());
		}

		public function testAcquired_alreadyReleased_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');
			$lock->release();

			$this->assertFalse($lock->acquired());
		}

		public function testAcquired_notLockedAnymore() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$this->assertFalse($lock->acquired());
		}

		public function testAcquired_notLockedAnymore_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$this->assertFalse($lock->acquired());
		}

		public function testAcquired_connectionLoss() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->assertFalse($lock->acquired());
		}

		public function testAcquired_connectionLoss_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->assertFalse($lock->acquired());
		}

		public function testAcquired_connectionLossInTransaction() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->assertFalse($lock->acquired());
		}

		public function testAcquired_connectionLossInTransaction_otherConn() {

			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$this->assertFalse($lock->acquired());
		}

		public function testReleaseAfter() {

			$lockName = uniqid();

			$ret = new \stdClass();

			$lock = new DbLock($lockName, 0, 10);

			$this->assertSame(0, DB::connection()->transactionLevel());

			$this->assertSame($ret, $lock->releaseAfter(function ($l) use ($ret, $lock) {

				$this->assertSame(1, DB::connection()->transactionLevel());

				$this->assertSame($lock, $l);

				return $ret;
			}));

			$this->assertSame(0, DB::connection()->transactionLevel());
			$this->assertFalse($lock->acquired());
		}

		public function testReleaseAfter_otherConn() {

			$lockName = uniqid();

			$ret = new \stdClass();

			$lock = new DbLock($lockName, 0, 10, 'other');

			$this->assertSame(0, DB::connection('other')->transactionLevel());

			$this->assertSame($ret, $lock->releaseAfter(function ($l) use ($ret, $lock) {

				$this->assertSame(1, DB::connection('other')->transactionLevel());

				$this->assertSame($lock, $l);

				return $ret;
			}));

			$this->assertSame(0, DB::connection('other')->transactionLevel());

			$this->assertFalse($lock->acquired());
		}

		public function testReleaseAfter_releasesLockOnInnerException() {

			$lockName = uniqid();

			$ex = new \Exception();

			$lock = new DbLock($lockName, 0, 10);

			try {
				$lock->releaseAfter(function () use ($ex) {
					throw $ex;
				});

				$this->fail('Expected exception was not thrown');
			}
			catch (\Exception $e) {
				$this->assertSame($ex, $e);
			}

			$this->assertFalse($lock->acquired());
		}

		public function testReleaseAfter_releasesLockOnInnerException_otherConn() {

			$lockName = uniqid();

			$ex = new \Exception();

			$lock = new DbLock($lockName, 0, 10, 'other');

			try {
				$lock->releaseAfter(function () use ($ex) {
					throw $ex;
				});

				$this->fail('Expected exception was not thrown');
			}
			catch (\Exception $e) {
				$this->assertSame($ex, $e);
			}

			$this->assertFalse($lock->acquired());
		}

		public function testReleaseAfter_alreadyReleased() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);
			$lock->release();

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {
					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(0, $callCount);
		}

		public function testReleaseAfter_alreadyReleased_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');
			$lock->release();

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {
					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(0, $callCount);
		}

		public function testReleaseAfter_alreadyNotHavingLockAnymore() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {
					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(0, $callCount);
		}

		public function testReleaseAfter_alreadyNotHavingLockAnymore_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate that the database lock was deleted
			if (DB::table('database_locks')
				    ->where('name', '=', $lockName)
				    ->delete() != 1) {
				$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
			}

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {
					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(0, $callCount);
		}

		public function testReleaseAfter_alreadyLostConnection() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {
					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(0, $callCount);
		}

		public function testReleaseAfter_alreadyLostConnection_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {
					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(0, $callCount);
		}

		public function testReleaseAfter_alreadyLostConnectionInTransaction() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {
					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(0, $callCount);
		}

		public function testReleaseAfter_alreadyLostConnectionInTransaction_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');

			DB::beginTransaction();

			// simulate connection loss
			try {

				DB::connection('other')->affectingStatement('KILL connection_id()');
			}
			catch (\PDOException $ex) {
				if ($ex->getCode() != 70100)
					throw $ex;
			}

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {
					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(0, $callCount);
		}


		public function testReleaseAfter_notHavingLockAnymore() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);


			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount, $lockName) {

					// simulate that the database lock was deleted
					if (DB::table('database_locks')
						    ->where('name', '=', $lockName)
						    ->delete() != 1) {
						$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
					}

					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(1, $callCount);
		}

		public function testReleaseAfter_notHavingLockAnymore_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');


			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount, $lockName) {

					// simulate that the database lock was deleted
					if (DB::table('database_locks')
						    ->where('name', '=', $lockName)
						    ->delete() != 1) {
						$this->fail('Test-Scenario-Error: Simulating lock deleted failed');
					}

					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(1, $callCount);
		}

		public function testReleaseAfter_lostConnection() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);


			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {

					// simulate connection loss
					try {

						DB::affectingStatement('KILL connection_id()');
					}
					catch (\PDOException $ex) {
						if ($ex->getCode() != 70100)
							throw $ex;
					}

					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(1, $callCount);
		}

		public function testReleaseAfter_lostConnection_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');


			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {

					// simulate connection loss
					try {

						DB::connection('other')->affectingStatement('KILL connection_id()');
					}
					catch (\PDOException $ex) {
						if ($ex->getCode() != 70100)
							throw $ex;
					}

					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(1, $callCount);
		}

		public function testReleaseAfter_lostConnectionInTransaction() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10);


			DB::beginTransaction();

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {

					// simulate connection loss
					try {

						DB::affectingStatement('KILL connection_id()');
					}
					catch (\PDOException $ex) {
						if ($ex->getCode() != 70100)
							throw $ex;
					}

					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(1, $callCount);
		}

		public function testReleaseAfter_lostConnectionInTransaction_otherConn() {

			$lockName = uniqid();


			$lock = new DbLock($lockName, 0, 10, 'other');


			DB::beginTransaction();

			$callCount = 0;

			try {
				$lock->releaseAfter(function () use (&$callCount) {

					// simulate connection loss
					try {

						DB::connection('other')->affectingStatement('KILL connection_id()');
					}
					catch (\PDOException $ex) {
						if ($ex->getCode() != 70100)
							throw $ex;
					}

					++$callCount;
				});

				$this->fail('Expected ' . DbLockException::class . ' was not thrown');
			}
			catch (DbLockException $ex) {
			}

			$this->assertSame(1, $callCount);
		}

		public function testGetName() {
			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10);

			$this->assertSame($lockName, $lock->getName());
		}

		public function testGetBoundConnection() {
			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 10, 'other');

			$this->assertSame('other', $lock->getBoundConnection());
		}

		public function testGetTtl() {
			$lockName = uniqid();

			$lock = new DbLock($lockName, 0, 5);

			$this->assertSame(5, $lock->getTtl());
		}

		public function testGetTimeout() {
			$lockName = uniqid();

			$lock = new DbLock($lockName, 6, 5);

			$this->assertEquals(6, $lock->getTimeout());
		}

		public function testGetCreatedAt() {
			$lockName = uniqid();

			$lock = new DbLock($lockName, 6, 5);

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

					$lock = new DbLock($lockName, 5, 10);

					$this->assertGreaterThan(1, $lock->getWaited());

				},
				function ($sh) use ($lockName) {
					// child

					$lock = new DbLock($lockName, 0, 10);

					$this->sendMessage('acquired', $sh);

					sleep(2);

					// explicit release
					$lock->release();

					sleep(1);
				}
			);
		}

	}

	class DbLockWithoutDestructRelease extends DbLock {

		public function __destruct() {
			// do not perform any cleanup on destruct
		}


	}