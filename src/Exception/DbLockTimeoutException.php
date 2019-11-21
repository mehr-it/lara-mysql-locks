<?php


	namespace MehrIt\LaraMySqlLocks\Exception;


	use Throwable;

	class DbLockTimeoutException extends DbLockAcquireException
	{

		protected $timeout;

		protected $remainingTtl;

		/**
		 * SharedLockException constructor.
		 * @param string $lockName The lock name
		 * @param int $timeout The timeout which exceeded
		 * @param int $remainingTtl The number of seconds remaining for the existing lock
		 * @param string $message [optional] The Exception message to throw.
		 * @param int $code [optional] The Exception code.
		 * @param Throwable $previous [optional] The previous throwable used for the exception chaining.
		 */
		public function __construct($lockName, $timeout, $remainingTtl, $message = "", $code = 0, Throwable $previous = null) {
			$this->timeout = $timeout;
			$this->remainingTtl = $remainingTtl;

			if (empty($message))
				$message = 'Acquiring lock "' . $lockName . '" timed out after ' . $timeout . ' seconds. The lock times out in ' . $remainingTtl . ' seconds. Should be retried later.';

			parent::__construct($lockName, $message, $code, $previous);
		}

		/**
		 * @return int
		 */
		public function getTimeout() {
			return $this->timeout;
		}

		/**
		 * @return int
		 */
		public function getRemainingTtl() {
			return $this->remainingTtl;
		}

	}