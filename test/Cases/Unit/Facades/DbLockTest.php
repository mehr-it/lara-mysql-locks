<?php


	namespace MehrItLaraMySqlLocksTest\Cases\Unit\Facades;


	use MehrIt\LaraMySqlLocks\DbLockFactory;
	use MehrIt\LaraMySqlLocks\Facades\DbLock;
	use MehrItLaraMySqlLocksTest\Cases\Unit\TestCase;

	class DbLockTest extends TestCase
	{
		public function testAncestorCall() {
			// mock ancestor
			$mock = $this->mockAppSingleton(DbLockFactory::class, DbLockFactory::class);
			$mock->expects($this->once())
				->method('lock')
				->with('l1', 5, 8);

			DbLock::lock('l1', 5, 8);
		}
	}