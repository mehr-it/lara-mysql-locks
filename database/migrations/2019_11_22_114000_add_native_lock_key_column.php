<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	class AddNativeLockKeyColumn extends Migration
	{
		/**
		 * Run the migrations.
		 *
		 * @return void
		 */
		public function up() {
			Schema::table('database_locks', function (Blueprint $table) {
				$table->string('native_lock_key', 128)->nullable();
			});

			\Illuminate\Support\Facades\DB::table('database_locks')->update([
				'native_lock_key' => new \Illuminate\Database\Query\Expression('concat(\'l\', name)')
			]);

			Schema::table('database_locks', function (Blueprint $table) {
				$table->string('native_lock_key', 128)->nullable(false)->change();
			});
		}

		/**
		 * Reverse the migrations.
		 *
		 * @return void
		 */
		public function down() {
			Schema::table('database_locks', function (Blueprint $table) {
				$table->dropColumn('native_lock_key');
			});
		}
	}