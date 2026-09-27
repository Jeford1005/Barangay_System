<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Create households first without the foreign key to residents
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('household_code', 20)->unique();
            $table->string('sitio', 100)->nullable();
            $table->string('street', 150)->nullable();
            $table->unsignedBigInteger('purok_id')->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('municipality', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->enum('house_type', ['Single', 'Duplex', 'Apartment', 'Townhouse', 'Other'])->default('Single');
            $table->string('lot_area', 50)->nullable();
            $table->string('floor_area', 50)->nullable();
            $table->unsignedInteger('year_built')->nullable();
            $table->enum('ownership', ['Owned', 'Rented', 'Leased', 'Occupied'])->default('Owned');
            $table->unsignedInteger('num_members')->default(1);
            $table->unsignedBigInteger('head_of_household_id')->nullable();
            $table->enum('status', ['Occupied', 'Vacant', 'Under Construction'])->default('Occupied');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('purok_id')->references('id')->on('puroks')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('purok_id');
            $table->index('status');
            $table->index('created_at');
        });

        // Create residents with all required fields from controller validation
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100)->nullable(false);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable(false);
            $table->string('suffix', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birthplace', 150)->nullable();
            $table->enum('sex', ['Male', 'Female', 'Other'])->nullable(false);
            $table->enum('civil_status', ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'])->nullable(false);
            $table->string('nationality', 50)->default('Filipino');
            $table->string('religion', 100)->nullable();
            $table->string('education_level', 100)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('spouse_name', 100)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('phone_number', 15)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('residency_status', 50)->nullable();
            $table->boolean('voter_status')->default(false);
            $table->boolean('is_household_head')->default(false);
            $table->string('photo', 255)->nullable();
            $table->enum('status', ['Active', 'Archived'])->default('Active');
            $table->unsignedBigInteger('purok_id')->nullable();
            $table->unsignedBigInteger('household_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('purok_id')->references('id')->on('puroks')->onDelete('set null');
            $table->foreign('household_id')->references('id')->on('households')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('last_name');
            $table->index('purok_id');
            $table->index('household_id');
            $table->index('status');
            $table->index('created_at');
        });

        // Now add the foreign key from households to residents
        Schema::table('households', function (Blueprint $table) {
            $table->foreign('head_of_household_id')->references('id')->on('residents')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropForeign(['head_of_household_id']);
        });

        Schema::dropIfExists('residents');
        Schema::dropIfExists('households');
    }
};