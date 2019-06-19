<?php


	namespace MehrIt\LaraMySqlLocks\Exception;


	use RuntimeException;
	use Throwable;

	class DbLockException extends RuntimeException
	{
		protected $lockName;


		/**
		 * SharedLockException constructor.
		 * @param string $lockName The lock name
		 * @param string $message [optional] The Exception message to throw.
		 * @param int $code [optional] The Exception code.
		 * @param Throwable $previous [optional] The previous throwable used for the exception chaining.
		 */
		public function __construct($lockName, $message = "", $code = 0, Throwable $previous = null) {

			$this->lockName = $lockName;

			parent::__construct($message, $code, $previous);
		}

		/**
		 * Gets the lock name
		 * @return string The lock name
		 */
		public function getLockName() {
			return $this->lockName;
		}


	}