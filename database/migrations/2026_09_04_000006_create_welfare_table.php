<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('welfare', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beneficiary_id')->nullable();
            $table->string('beneficiary_name', 255)->nullable(false);
            $table->string('beneficiary_address', 255)->nullable();
            $table->string('beneficiary_phone', 15)->nullable();
            $table->enum('assistance_type', ['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other'])->nullable(false);
            $table->string('program_name', 255)->nullable(false);
            $table->text('program_description')->nullable();
            $table->decimal('requested_amount', 12, 2)->default(0.00);
            $table->decimal('approved_amount', 12, 2)->default(0.00);
            $table->enum('status', ['Requested', 'Under Review', 'Approved', 'Denied', 'Released'])->default('Requested');
            $table->date('request_date')->nullable(false);
            $table->date('approval_date')->nullable();
            $table->date('release_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('beneficiary_id')->references('id')->on('residents')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welfare');
    }
};