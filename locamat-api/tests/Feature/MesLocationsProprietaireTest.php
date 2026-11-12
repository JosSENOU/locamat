<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MesLocationsProprietaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_proprietaire_can_open_my_rentals_page_and_the_owner_pages(): void
    {
        $jeton = User::factory()->proprietaire()->create()->createToken('test')->plainTextToken;

        foreach (['/api/locataire/locations', '/api/locataire/reclamations', '/api/proprietaire/demandes', '/api/proprietaire/stats'] as $chemin) {
            $this->withToken($jeton)->getJson($chemin)->assertOk();
        }
    }

    public function test_an_administrator_still_cannot_use_the_tenant_pages(): void
    {
        $jeton = User::factory()->admin()->create()->createToken('test')->plainTextToken;

        $this->withToken($jeton)->getJson('/api/locataire/locations')->assertForbidden();
    }
}
