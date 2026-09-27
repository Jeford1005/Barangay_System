<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Catalog of certificates / permits / clearances the barangay issues.
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();      // CLR | COR | IND …
            $table->string('title', 150);
            $table->string('description', 255)->nullable();
            $table->string('document_type', 20)->default('Certificate'); // Certificate|Permit|Clearance|ID|Other
            $table->decimal('fee', 8, 2)->default(0);
            $table->string('status', 20)->default('Active');            // Active|Inactive|Draft
            $table->timestamps();
        });

        // Yearly atomic sequences: <CODE>-YYYY-#### (CLR-2026-0007, BLTR-2026-0001).
        Schema::create('sequence_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_counters');
        Schema::dropIfExists('documents');
    }
};
