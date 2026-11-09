<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbstractTemplateAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_abstract_template_is_available_without_a_completed_rfq(): void
    {
        $this->actingAs($this->headOffice())
            ->get(route('head-office.abstracts.create'))
            ->assertOk()->assertSee('Blank Abstract Template')->assertSee('Proceed to Abstract Form')
            ->assertSee('ABSTRACT OF QUOTATIONS')->assertSee('id="abstractEditor"', false)
            ->assertSee('Add Row')->assertSee('Remove Empty Rows')->assertSee('Download Editable Template');

        $this->assertDatabaseCount('abstracts', 0);
        $this->assertDatabaseCount('rfqs', 0);
    }

    public function test_creating_an_abstract_still_requires_a_ready_rfq(): void
    {
        $this->actingAs($this->headOffice())->post(route('head-office.abstracts.store'), [
            'save_action' => 'draft', 'abstract_date' => '2026-11-09',
            'document_html' => '<p>Blank abstract</p>', 'document_text' => 'Blank abstract',
        ])->assertSessionHasErrors('source_rfq_id');

        $this->assertDatabaseCount('abstracts', 0);
    }

    private function headOffice(): User
    {
        $office = Office::create([
            'code' => 'ABS-TEST', 'name' => 'Abstract Test Office', 'type' => Office::TYPE_END_USER,
            'status' => Office::STATUS_ACTIVE, 'is_requesting_office' => true,
        ]);

        return User::factory()->create([
            'user_id' => 'ABS-HEAD', 'role' => User::ROLE_HEAD_OFFICE, 'office_id' => $office->id,
            'status' => User::STATUS_ACTIVE, 'must_change_password' => false,
        ]);
    }
}
