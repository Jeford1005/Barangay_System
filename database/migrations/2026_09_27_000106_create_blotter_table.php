<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blotter', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 30)->unique(); // BLTR-2026-0001
            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->string('incident_type', 100);
            $table->string('location', 255);
            $table->foreignId('purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->string('complainant_name', 150);
            $table->string('complainant_contact', 30)->nullable();
            $table->string('respondent_name', 150)->nullable();
            $table->string('respondent_contact', 30)->nullable();
            $table->text('narrative');
            $table->string('handling_officer', 150)->nullable();
            $table->string('arrest_made', 5)->default('No'); // Yes | No
            $table->string('status', 20)->default('Open');   // Open|Pending|Resolved|Dismissed
            $table->text('resolution_notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blotter');
    }
};
