<?php


	namespace MehrIt\LaraMySqlLocks\Exception;


	use Throwable;

	class DbLockRemainingTTLException extends DbLockException
	{
		protected $ttl;

		/**
		 * SharedLockException constructor.
		 * @param string $lockName The lock name
		 * @param int $ttl The remaining ttl which could not by reached
		 * @param string $message [optional] The Exception message to throw.
		 * @param int $code [optional] The Exception code.
		 * @param Throwable $previous [optional] The previous throwable used for the exception chaining.
		 */
		public function __construct($lockName, $ttl, $message = "", $code = 0, Throwable $previous = null) {
			$this->ttl = $ttl;

			if (empty($message))
				$message = 'Lock "' . $lockName . '" is not to live for ' . $ttl . ' seconds anymore';

			parent::__construct($lockName, $message, $code, $previous);
		}

		/**
		 * @return int
		 */
		public function getTTL() {
			return $this->ttl;
		}

	}