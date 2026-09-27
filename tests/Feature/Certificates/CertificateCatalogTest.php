<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateIssuance;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_governed_document_type(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.certificate-types.store'), [
                'code' => 'BRGY',
                'title' => 'Barangay Certificate',
                'description' => 'A governed certificate.',
                'category' => 'Certificate',
                'document_type' => 'Certificate',
                'requirements' => 'Valid ID',
                'fee' => 0,
                'status' => 'Active',
            ])
            ->assertRedirect(route('admin.certificate-types.index'));

        $this->assertDatabaseHas('documents', ['code' => 'BRGY', 'status' => 'Active']);
    }

    public function test_admin_can_review_and_update_the_certificate_catalog(): void
    {
        $admin = User::factory()->admin()->create();
        $document = Document::where('code', 'CLR')->first();

        $this->actingAs($admin)
            ->get(route('admin.certificate-types.index'))
            ->assertOk()
            ->assertSee('Certificate catalog')
            ->assertSee('CLR');

        $this->actingAs($admin)
            ->put(route('admin.certificate-types.update', $document), [
                'code' => 'CLR',
                'title' => 'Updated Barangay Clearance',
                'description' => $document->description,
                'category' => $document->category,
                'document_type' => 'Clearance',
                'requirements' => 'Valid ID',
                'fee' => 50,
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.certificate-types.index'));

        $this->assertSame('Inactive', $document->fresh()->status);
        $this->assertSame('Valid ID', $document->fresh()->requirements);
        $this->assertDatabaseHas('audit_logs', ['event' => 'document.updated']);
    }

    public function test_issued_document_code_cannot_be_renamed(): void
    {
        $admin = User::factory()->admin()->create();
        $document = Document::where('code', 'CLR')->first();
        CertificateIssuance::factory()->create(['document_id' => $document->id]);

        $this->actingAs($admin)
            ->put(route('admin.certificate-types.update', $document), [
                'code' => 'NEW',
                'title' => $document->title,
                'description' => $document->description,
                'category' => $document->category,
                'document_type' => $document->document_type,
                'requirements' => $document->requirements,
                'fee' => $document->fee,
                'status' => $document->status,
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame('CLR', $document->fresh()->code);
    }

    public function test_residents_cannot_manage_the_catalog(): void
    {
        $resident = User::factory()->resident()->create();
        $document = Document::where('code', 'CLR')->first();

        $this->actingAs($resident)
            ->get(route('admin.certificate-types.index'))
            ->assertRedirect(route('dashboard'));
    }
}
