<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	class CreateDatabaseLocksTable extends Migration
	{
		/**
		 * Run the migrations.
		 *
		 * @return void
		 */
		public function up() {
			Schema::create('database_locks', function (Blueprint $table) {
				$table->string('name', 128);
				$table->unsignedBigInteger('connection_id');
				$table->unsignedBigInteger('created');
				$table->unsignedBigInteger('ttl');
				$table->boolean('lock_acquired');
				$table->primary('name');
			});
		}

		/**
		 * Reverse the migrations.
		 *
		 * @return void
		 */
		public function down() {
			Schema::dropIfExists('database_locks');
		}
	}