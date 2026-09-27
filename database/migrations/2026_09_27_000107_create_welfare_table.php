<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('welfare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->string('assistance_type', 30); // Financial|Food|Medical|Educational|Housing|Other
            $table->decimal('requested_amount', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable(); // granted amount
            $table->string('status', 20)->default('Requested'); // Requested|Under Review|Approved|Denied|Released
            $table->date('request_date');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welfare');
    }
};
