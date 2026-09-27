<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->useCurrent();
            $table->unsignedBigInteger('user_id')->nullable(); // null = unknown/guest email
            $table->string('user_email', 150)->nullable();     // denormalized: survives user deletion
            $table->string('event', 50);                       // password_reset.code_requested, etc.
            $table->string('ip_address', 45)->nullable();      // IPv6-ready
            $table->string('user_agent', 500)->nullable();
            $table->json('properties')->nullable();            // event-specific details
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('event');
            $table->index('occurred_at');
            $table->index('user_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
