<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_code', 20)->unique();
            $table->string('title', 255)->nullable(false);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable(false);
            $table->enum('document_type', ['Certificate', 'Permit', 'Clearance', 'ID', 'Other'])->default('Certificate');
            $table->text('requirements')->nullable();
            $table->unsignedInteger('processing_days')->default(1);
            $table->decimal('fee', 10, 2)->default(0.00);
            $table->enum('status', ['Active', 'Inactive', 'Draft'])->default('Active');
            $table->string('template_path', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};