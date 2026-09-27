<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('household_number', 20)->unique(); // HH-001
            $table->string('address', 255);
            $table->foreignId('purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->foreignId('head_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('house_type', 20)->default('Single');   // Single|Duplex|Apartment|Townhouse|Other
            $table->string('ownership', 20)->default('Owned');      // Owned|Rented|Leased|Occupied
            $table->string('status', 20)->default('Occupied');      // Occupied|Vacant|Under Construction
            $table->unsignedSmallInteger('member_count')->default(0);
            $table->timestamps();

            $table->index('purok_id');
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->foreign('household_id')
                ->references('id')->on('households')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
        });

        Schema::dropIfExists('households');
    }
};
