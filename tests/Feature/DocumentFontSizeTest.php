<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentFontSizeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $role): User
    {
        $roleRow = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleRow);

        return $user;
    }

    private function makeCustomerOwnedBy(User $user): Customer
    {
        return Customer::query()->create([
            'name' => 'Customer A',
            'created_by' => $user->id,
            'sales_user_id' => $user->id,
        ]);
    }

    public function test_document_create_form_uses_global_default_font_size(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $this->makeCustomerOwnedBy($sales);

        Setting::setMany([
            'documents.default_font_size_px' => '14',
        ]);

        $response = $this->actingAs($sales)->get(route('documents.create'));

        $response->assertOk();
        $response->assertSee('name="default_font_size_px"', false);
        $response->assertSee('value="14"', false);
    }

    public function test_document_store_and_update_persist_default_font_size(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $customer = $this->makeCustomerOwnedBy($sales);

        $storeResponse = $this->actingAs($sales)->post(route('documents.store'), [
            'title' => 'Test Font',
            'document_date' => now()->toDateString(),
            'body' => '<p>Body</p>',
            'customer_id' => $customer->id,
            'sales_signer_user_id' => 'director',
            'default_font_size_px' => 18,
        ]);
        $storeResponse->assertRedirect();

        $document = Document::query()->latest('id')->firstOrFail();
        $this->assertSame(18, $document->default_font_size_px);

        $updateResponse = $this->actingAs($sales)->put(route('documents.update', $document), [
            'title' => 'Test Font Updated',
            'document_date' => now()->toDateString(),
            'body' => '<p>Body Updated</p>',
            'customer_id' => $customer->id,
            'sales_signer_user_id' => 'director',
            'default_font_size_px' => 20,
        ]);
        $updateResponse->assertRedirect(route('documents.show', $document));

        $document->refresh();
        $this->assertSame(20, $document->default_font_size_px);
    }

    public function test_show_and_pdf_use_resolved_font_size_when_document_value_is_null(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $customer = $this->makeCustomerOwnedBy($sales);

        Setting::setMany([
            'documents.default_font_size_px' => '13',
        ]);

        $document = Document::query()->create([
            'title' => 'Fallback Font',
            'document_date' => now()->toDateString(),
            'body_html' => '<p>Body</p>',
            'default_font_size_px' => null,
            'customer_id' => $customer->id,
            'contact_id' => null,
            'customer_snapshot' => ['name' => 'Customer A'],
            'contact_snapshot' => null,
            'created_by_user_id' => $sales->id,
            'sales_signer_user_id' => null,
            'status' => Document::STATUS_DRAFT,
            'sales_signature_position' => null,
        ]);

        $showResponse = $this->actingAs($sales)->get(route('documents.show', $document));
        $showResponse->assertOk();
        $showResponse->assertSee('font-size: 13px;', false);

        $pdfResponse = $this->actingAs($sales)->get(route('documents.pdf', $document));
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('Content-Type', 'application/pdf');

        $pdfHtml = view('documents.pdf', [
            'document' => $document->fresh(),
            'bodyFontSizePx' => 13,
            'letterheadPath' => null,
            'stampPath' => null,
            'directorSignaturePath' => null,
        ])->render();
        $this->assertStringContainsString('font-size: 13px;', $pdfHtml);
    }
}
