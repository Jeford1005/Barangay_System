<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The official tier signs in on the office tab alongside administrators
        // and staff, so `user_type` has to accept the fourth value. Postgres
        // needs the CHECK constraint replaced rather than the column rewritten
        // for the same reason the staff migration documents: ->enum()->change()
        // compiles to ALTER COLUMN ... TYPE with an inline CHECK, which it
        // rejects.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "users" drop constraint if exists "users_user_type_check"');
            DB::statement('alter table "users" add constraint "users_user_type_check" check ("user_type" in (\'admin\', \'resident\', \'staff\', \'official\'))');

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('user_type', ['admin', 'resident', 'staff', 'official'])
                ->default('admin')
                ->change();
        });
    }

    public function down(): void
    {
        // Do not silently demote official accounts if this migration is rolled back.
        if (DB::table('users')->where('user_type', 'official')->exists()) {
            throw new RuntimeException('Cannot remove the official role while official accounts exist.');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "users" drop constraint if exists "users_user_type_check"');
            DB::statement('alter table "users" add constraint "users_user_type_check" check ("user_type" in (\'admin\', \'resident\', \'staff\'))');

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('user_type', ['admin', 'resident', 'staff'])
                ->default('admin')
                ->change();
        });
    }
};
