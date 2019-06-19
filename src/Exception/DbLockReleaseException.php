<?php


	namespace MehrIt\LaraMySqlLocks\Exception;


	use Throwable;

	class DbLockReleaseException extends DbLockException
	{

		/**
		 * @param string $lockName The lock name
		 * @param string $message [optional] The Exception message to throw.
		 * @param int $code [optional] The Exception code.
		 * @param Throwable $previous [optional] The previous throwable used for the exception chaining.
		 */
		public function __construct($lockName, $message = "", $code = 0, Throwable $previous = null) {

			if (empty($message))
				$message = 'Releasing lock "' . $lockName . '" failed';

			parent::__construct($lockName, $message, $code, $previous);
		}
	}