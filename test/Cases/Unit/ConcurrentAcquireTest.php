<?php


	namespace MehrItLaraMySqlLocksTest\Cases\Unit;


	class ConcurrentAcquireTest extends TestCase
	{
		use TestsParallelProcesses;


		protected function forkLocking($count) {

			$this->fork(
				function() use ($count) {
					if ($count > 1)
						$this->forkLocking($count - 1);
					else
						sleep(2);
				},
				function() use ($count) {

					$GLOBALS['count'] = $count;

					$lock = \MehrIt\LaraMySqlLocks\Facades\DbLock::lock('my-lock', 10, 10);

					echo "Acquired {$GLOBALS['count']}" . '  ' . microtime(true) . "\n";

					usleep(500000);

					$lock->release();

					echo "Released {$GLOBALS['count']}" . '  ' . microtime(true) . "\n";
				}
			);

			$this->expectNotToPerformAssertions();
		}


		public function testDeadlocks() {

			$this->markTestSkipped('Test must be manually run to debug deadlocks');

			for ($i = 0; $i < 10; ++$i) {
				$this->forkLocking(10);
			}

		}

	}