# MySQL based locks for Laravel

This package implements MySQL based locks for distributed systems with following features:
	
* named logs without prior initialisation
* wait timeout for obtaining locks
* TTL (time to live) for locks
* database session is terminated when lock expired and taken over by another process
* if not released, locks are automatically released when process ends or dies unexpectedly
* waiting for locks uses blocking database requests, not polling
* locks released before TTL can immediately be acquired by other processes 

## Requirements

* PHP >= 7.1
* MySQL >= 5.7.5 (before 5.7.5 only one named lock per connection can be acquired)

**This package only works with MySQL database connections!**

## Install

	composer require mehr-it/lara-mysql-locks
	
This package uses Laravel's package auto-discovery, so the service provider and aliases will 
be loaded automatically.

## Usage

	$lock = DbLock::lock('my-lock', 5, 10);
	
	// do some work
	
	$lock->release();

The `lock()` method expects the lock name, the time to wait (before timeout) and the maximum
time to live for the lock (both in seconds).

When the lock cannot be obtained within the given timeout, a `DbLockTimeoutException` is
thrown.

The `release()` method releases the lock. It fails, if the database transaction is lost
or the lock's TTL has expired **and** another process acquired the lock. In such cases
a `DbLockReleaseException` is thrown, indicating that the lock was not acquired anymore.

### Using callbacks

The `release()` method should always be called. Therefore you usually should acquire locks
using the `withLock()` method and pass a callback. `withLock()` ensures that the lock is
released even if an error is thrown:

	$return = DbLock::withLock(
			function($lock) {
			
			// do some work
				
			}, 'my-lock', 5, 10
		);
	
**The `withLock()` method executes the callback within a database transaction.** The
transaction is rolled back on any thrown or lock errors.

You may pass a different database connection to create the lock and the transaction in,
by passing it's name to withLock:

	DbLock::withLock(function($lock) { }, 'my-lock', 5, 10, 'my-connection');

**The other connection must target the same MySQL instance, as the default connection!
Otherwise the lock is immediately released!**

## Locks and database connections

Locks are always bound to database connections. If no other connection is passed when
creating the lock, they are bound to the default connection.

**The database connection the lock is bound to, will be terminated when the lock's TTL
is expired *and* another process acquires the lock!**

This has to be considered when working with transactions. Because any SQL queries
after "lock loss" within a transaction will fail. As mostly intended (you usually
don't want to commit anything when lock TTL exceeded) there might be cases when this
behaviour is unwanted. In such cases you must pass another database connection to
create the lock on:

	DbLock::lock('my-lock', 5, 10, 'other-connection');
	
**This other connection must target the same MySQL instance, as the default connection!
Otherwise the lock is immediately released!**

If not working with transactions, laravel will gracefully reconnect to the database 
and your program will continue as expected in these cases. 

	
## Verify if lock is still acquired

Sometimes you might have to check if the lock is still acquired. This usually happens
if performing operations affecting other resources not covered by database transactions.

Following example checks that the lock is acquired for at least 5 more seconds. If not
a `DbLockRemainingTTLException` is thrown:

	$lock->assertAcquiredFor(5);
	
If you don't want an exception, you my use `remainsAcquiredFor()`:

	if (!$lock->remainsAcquiredFor(5)) {
		// handle lock timeout
	}

## Limitations
MySQL has a maximum length of lock names. Therefore you must not use lock names with more than
50 characters.
