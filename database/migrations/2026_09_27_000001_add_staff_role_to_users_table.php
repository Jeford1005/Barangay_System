<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Postgres rejects an inline CHECK inside ALTER COLUMN ... TYPE, which
            // is exactly what ->enum()->change() compiles to there. The column is
            // already varchar(255) -- that is how ->enum() is represented on
            // Postgres -- so the type, nullability and default are all correct
            // already and only the allowed values need replacing. Skipping the
            // constraint instead would leave 'staff' rejected by the database.
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

    public function down(): void
    {
        // Do not silently demote staff accounts if this migration is rolled back.
        if (DB::table('users')->where('user_type', 'staff')->exists()) {
            throw new RuntimeException('Cannot remove the staff role while staff accounts exist.');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "users" drop constraint if exists "users_user_type_check"');
            DB::statement('alter table "users" add constraint "users_user_type_check" check ("user_type" in (\'admin\', \'resident\'))');

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('user_type', ['admin', 'resident'])
                ->default('admin')
                ->change();
        });
    }
};
