<?php


	namespace MehrItLaraMySqlLocksTest\Cases\Unit\Provider;


	use MehrIt\LaraMySqlLocks\DbLockFactory;
	use MehrItLaraMySqlLocksTest\Cases\Unit\TestCase;

	class DbLockServiceProvider extends TestCase
	{

		protected function setUp() {
			parent::setUp();

			$this->refreshApplication();
		}


		public function testDbLockFactoryRegistration() {

			$resolved = app(DbLockFactory::class);

			$this->assertInstanceOf(DbLockFactory::class, $resolved);
			$this->assertSame($resolved, app(DbLockFactory::class));

		}

	}