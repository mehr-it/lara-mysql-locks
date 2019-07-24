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

		public function testWait() {

			$factory = new DbLockFactory();

			$wait = $factory->wait('l1', 5, 'other');

			$this->assertInstanceOf(DbWait::class, $wait);
			$this->assertSame('l1', $wait->getName());
			$this->assertSame(5.0, $wait->getTimeout());
			$this->assertSame('other', $wait->getBoundConnection());

		}

		public function testAwaitRelease() {

			$factory = new DbLockFactory();


			$wait = $factory->awaitRelease('l2', 5, 'other');

			$this->assertInstanceOf(DbWait::class, $wait);
			$this->assertSame('l2', $wait->getName());
			$this->assertSame(5.0, $wait->getTimeout());
			$this->assertSame('other', $wait->getBoundConnection());

			$this->expectException(DbLockReleaseException::class);

			$wait->release();

		}

		public function testWithWait() {

			$factory = new DbLockFactory();

			$ret       = new \stdClass();
			$callCount = 0;

			$this->assertSame($ret, $factory->withWait(function ($wait) use (&$callCount, $ret) {
				/** @var DbWait $wait */
				++$callCount;

				$this->assertInstanceOf(DbWait::class, $wait);
				$this->assertSame('l3', $wait->getName());
				$this->assertSame(5.0, $wait->getTimeout());
				$this->assertSame('other', $wait->getBoundConnection());

				return $ret;

			}, 'l3', 5,  'other'));

		}
	}