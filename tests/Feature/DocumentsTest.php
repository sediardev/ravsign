<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_see_the_example_documents()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/Index')
                ->has('documents', 4)
                ->where('documents.0.name', 'Acuerdo de servicios — Nómada Studio.pdf')
                ->where('documents.0.status', 'borrador')
                ->where('documents.2.status', 'completado'));
    }
}
