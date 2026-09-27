<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80);
            $table->string('suffix', 20)->nullable();
            $table->string('full_name', 255)->index(); // denormalized for search/sort
            $table->date('birth_date');
            $table->unsignedTinyInteger('age');         // stored at write time
            $table->string('sex', 10);                  // Male | Female | Other
            $table->string('civil_status', 20);         // Single | Married | Divorced | Widowed | Separated
            $table->string('occupation', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->foreignId('purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->foreignId('household_id')->nullable(); // FK added by households migration
            $table->string('status', 20)->default('Active')->index(); // Active | Archived
            $table->string('photo_path', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
