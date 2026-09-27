<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_type', 20)->default('user')->after('user_id');
            $table->unsignedBigInteger('actor_id')->nullable()->after('actor_type');
            $table->string('actor_email', 150)->nullable()->after('actor_id');
            $table->string('subject_type', 50)->nullable()->after('actor_email');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->string('subject_label')->nullable()->after('subject_id');
            $table->index(['actor_type', 'actor_id']);
            $table->index(['subject_type', 'subject_id']);
        });

        DB::table('audit_logs')->update([
            'actor_type' => DB::raw('CASE WHEN user_id IS NULL THEN \'guest\' ELSE \'user\' END'),
            'actor_id' => DB::raw('user_id'),
            'actor_email' => DB::raw('user_email'),
        ]);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['actor_type', 'actor_id']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn([
                'actor_type',
                'actor_id',
                'actor_email',
                'subject_type',
                'subject_id',
                'subject_label',
            ]);
        });
    }
};
