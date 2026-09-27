<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The documents table was scaffolded but never used. Reshape it into a
        // small catalog of the certificate types this barangay issues.
        Schema::table('documents', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->after('id');
            $table->decimal('fee', 10, 2)->default(0.00)->change();
        });

        // Backfill a stable code for any legacy rows, then enforce uniqueness.
        DB::table('documents')->whereNull('code')->get()->each(function ($doc) {
            DB::table('documents')->where('id', $doc->id)->update([
                'code' => 'DOC-'.str_pad((string) $doc->id, 3, '0', STR_PAD_LEFT),
            ]);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('code', 40)->nullable(false)->unique()->change();
            $table->dropUnique('documents_document_code_unique');
            $table->dropColumn(['document_code', 'template_path', 'processing_days']);
        });

        // One row per certificate actually issued to a resident.
        Schema::create('certificate_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('control_number', 30)->unique();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->string('purpose', 255);
            $table->unsignedTinyInteger('copies')->default(1);
            $table->decimal('fee', 10, 2)->default(0.00);
            $table->enum('status', ['Issued', 'Voided'])->default('Issued');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('issued_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('voided_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'created_at']);
            $table->index('resident_id');
        });

        // Seed the three certificate types the barangay issues at the counter.
        $now = now();
        foreach ([
            [
                'code' => 'CLR',
                'title' => 'Barangay Clearance',
                'description' => 'Certifies the holder is a resident of good standing with no pending case at the barangay level.',
                'category' => 'Clearance',
                'document_type' => 'Clearance',
                'requirements' => "Valid ID\nCommunity Tax Certificate",
                'fee' => 50.00,
            ],
            [
                'code' => 'COR',
                'title' => 'Certificate of Residency',
                'description' => 'Certifies that the holder is a bona fide resident of the barangay.',
                'category' => 'Certificate',
                'document_type' => 'Certificate',
                'requirements' => "Valid ID\nProof of address",
                'fee' => 30.00,
            ],
            [
                'code' => 'IND',
                'title' => 'Certificate of Indigency',
                'description' => 'Certifies that the holder belongs to an indigent family, for medical, educational, or legal assistance.',
                'category' => 'Certificate',
                'document_type' => 'Certificate',
                'requirements' => "Valid ID\nInterview with barangay official",
                'fee' => 0.00,
            ],
        ] as $doc) {
            DB::table('documents')->updateOrInsert(
                ['code' => $doc['code']],
                array_merge($doc, [
                    'status' => 'Active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_issuances');

        Schema::table('documents', function (Blueprint $table) {
            $table->string('document_code', 20)->nullable();
            $table->string('template_path', 255)->nullable();
            $table->unsignedInteger('processing_days')->default(1);
        });

        DB::table('documents')->get()->each(function ($doc) {
            DB::table('documents')->where('id', $doc->id)->update([
                'document_code' => $doc->code ?? 'DOC-'.str_pad((string) $doc->id, 3, '0', STR_PAD_LEFT),
                'processing_days' => 1,
            ]);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('document_code', 20)->nullable(false)->unique()->change();
            $table->dropUnique('documents_code_unique');
            $table->dropColumn('code');
        });
    }
};
