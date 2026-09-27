<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blotter', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 20)->unique();
            $table->unsignedBigInteger('complainant_id')->nullable();
            $table->string('complainant_name', 255)->nullable(false);
            $table->string('complainant_address', 255)->nullable();
            $table->string('complainant_phone', 15)->nullable();
            $table->unsignedBigInteger('accused_id')->nullable();
            $table->string('accused_name', 255)->nullable(false);
            $table->string('accused_address', 255)->nullable();
            $table->string('accused_phone', 15)->nullable();
            $table->string('complaint_type', 100)->nullable(false);
            $table->string('complaint_subtype', 100)->nullable();
            $table->date('complaint_date')->nullable(false);
            $table->time('complaint_time')->nullable();
            $table->text('alleged_offense')->nullable(false);
            $table->enum('status', ['Open', 'Pending', 'Resolved', 'Dismissed'])->default('Open');
            $table->text('disposition')->nullable();
            $table->date('disposition_date')->nullable();
            $table->enum('arrest_made', ['Yes', 'No'])->default('No');
            $table->string('investigator', 255)->nullable();
            $table->unsignedBigInteger('officer_id')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('complainant_id')->references('id')->on('residents')->onDelete('set null');
            $table->foreign('accused_id')->references('id')->on('residents')->onDelete('set null');
            $table->foreign('officer_id')->references('id')->on('officials')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('status');
            $table->index('complaint_date');
            $table->index('case_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blotter');
    }
};