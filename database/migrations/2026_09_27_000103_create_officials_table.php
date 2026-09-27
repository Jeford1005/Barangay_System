<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('officials', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('position', 100); // Punong Barangay, Kagawad, …
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->string('contact', 30)->nullable();
            $table->string('status', 20)->default('Active'); // Active | Inactive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officials');
    }
};
