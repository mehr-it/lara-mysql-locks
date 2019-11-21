<?php


	namespace MehrItLaraMySqlLocksTest\Cases\Unit;


	use MehrIt\LaraMySqlLocks\DbLock;
	use MehrIt\LaraMySqlLocks\DbLockFactory;
	use MehrIt\LaraMySqlLocks\DbWait;
	use MehrIt\LaraMySqlLocks\Exception\DbLockReleaseException;

	class DbLockFactoryTest extends TestCase
	{

		public function testLock() {

			$factory = new DbLockFactory();

			$lock = $factory->lock('l1', 5, 2, 'other');

			$this->assertInstanceOf(DbLock::class, $lock);
			$this->assertTrue($lock->acquired());
			$this->assertSame('l1', $lock->getName());
			$this->assertSame(5.0, $lock->getTimeout());
			$this->assertSame(2, $lock->getTtl());
			$this->assertSame('other', $lock->getBoundConnection());

		}

		public function testWithLock() {

			$factory = new DbLockFactory();

			$ret       = new \stdClass();
			$callCount = 0;

			$this->assertSame($ret,$factory->withLock(function($lock) use (&$callCount, $ret) {
				/** @var DbLock $lock */
				++$callCount;

				$this->assertInstanceOf(DbLock::class, $lock);
				$this->assertTrue($lock->acquired());
				$this->assertSame('l1', $lock->getName());
				$this->assertSame(5.0, $lock->getTimeout());
				$this->assertSame(2, $lock->getTtl());
				$this->assertSame('other', $lock->getBoundConnection());

				return $ret;

			},'l1', 5, 2, 'other'));

		}
	}