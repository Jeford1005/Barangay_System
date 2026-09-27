<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certificate_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('control_number', 30)->unique(); // CLR-2026-0007
            $table->foreignId('document_id')->constrained('documents');
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->string('purpose', 255);
            $table->decimal('fee', 8, 2)->default(0);
            $table->unsignedTinyInteger('copies')->default(1);
            $table->json('recipient_snapshot'); // frozen recipient data for printing
            $table->string('status', 20)->default('Issued'); // Issued | Voided
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents');
            $table->string('purpose', 255);
            $table->string('status', 20)->default('Pending'); // Pending|Approved|Rejected|Cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->foreignId('issuance_id')->nullable()->constrained('certificate_issuances')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_requests');
        Schema::dropIfExists('certificate_issuances');
    }
};
