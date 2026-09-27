<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('officials', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100)->nullable(false);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable(false);
            $table->string('suffix', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('sex', ['Male', 'Female', 'Other'])->nullable(false);
            $table->string('office', 100)->nullable(false);
            $table->string('position', 100)->nullable(false);
            $table->string('barangay', 100)->nullable();
            $table->string('municipality', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->string('phone_number', 15)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('sign_image', 255)->nullable();
            $table->date('term_start')->nullable(false);
            $table->date('term_end')->nullable(false);
            $table->enum('status', ['Active', 'Inactive', 'Appointed', 'Elected'])->default('Active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->index('position');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officials');
    }
};