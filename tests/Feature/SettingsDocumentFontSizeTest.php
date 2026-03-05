<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsDocumentFontSizeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $role): User
    {
        $roleRow = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleRow);

        return $user;
    }

    public function test_superadmin_can_update_global_document_default_font_size(): void
    {
        $superAdmin = $this->makeUserWithRole('SuperAdmin');

        $response = $this->actingAs($superAdmin)->post(route('settings.update'), [
            'company_name' => 'ICPOS',
            'company_email' => 'hello@example.com',
            'company_phone' => '08123',
            'company_address' => 'Surabaya',
            'mail_username_policy' => 'default_email',
            'documents_default_font_size_px' => 15,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('15', Setting::get('documents.default_font_size_px'));
    }
}
