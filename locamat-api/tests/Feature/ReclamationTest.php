<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Location;
use App\Models\Materiel;
use App\Models\Reclamation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclamationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_can_report_the_owner_from_their_rental_and_only_admins_can_review_it(): void
    {
        $tenant = User::factory()->create(['name' => 'Locataire test']);
        $owner = User::factory()->proprietaire()->create(['name' => 'Propriétaire test']);
        $materiel = Materiel::factory()->for($owner, 'proprietaire')->create(['nom' => 'Perceuse']);
        $location = Location::factory()
            ->for($materiel, 'materiel')
            ->for($tenant, 'locataire')
            ->create();

        $response = $this->actingAs($tenant)
            ->postJson('/api/locataire/reclamations', [
                'location_id' => $location->id,
                'motif' => 'materiel_non_conforme',
                'description' => 'Le matériel reçu ne correspondait pas à l’annonce.',
            ]);
        $response->assertCreated()
            ->assertJsonPath('statut', Reclamation::STATUT_NOUVELLE)
            ->assertJsonPath('location_id', $location->id)
            ->assertJsonMissingPath('proprietaire.email');

        $this->assertDatabaseHas('reclamations', [
            'location_id' => $location->id,
            'locataire_id' => $tenant->id,
            'proprietaire_id' => $owner->id,
            'statut' => Reclamation::STATUT_NOUVELLE,
        ]);
        $this->assertDatabaseHas('admin_actions', [
            'action' => AdminAction::RECLAMATION_CREEE,
            'cible_id' => $response->json('id'),
            'concerne_user_id' => $owner->id,
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->getJson('/api/admin/reclamations')
            ->assertOk()
            ->assertJsonPath('data.0.locataire.name', 'Locataire test')
            ->assertJsonPath('data.0.proprietaire.name', 'Propriétaire test')
            ->assertJsonPath('data.0.location.materiel.nom', 'Perceuse');
    }

    public function test_a_tenant_cannot_report_a_location_belonging_to_another_tenant(): void
    {
        $tenant = User::factory()->create();
        $owner = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($owner, 'proprietaire')->create();
        $otherTenant = User::factory()->create();
        $location = Location::factory()
            ->for($materiel, 'materiel')
            ->for($otherTenant, 'locataire')
            ->create();

        $this->actingAs($tenant)
            ->postJson('/api/locataire/reclamations', [
                'location_id' => $location->id,
                'motif' => 'autre',
                'description' => 'Je ne suis pas lié à cette location.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('reclamations', 0);
    }

    public function test_admin_can_update_a_complaint_and_the_tenant_can_see_the_reply(): void
    {
        $tenant = User::factory()->create();
        $owner = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($owner, 'proprietaire')->create();
        $location = Location::factory()
            ->for($materiel, 'materiel')
            ->for($tenant, 'locataire')
            ->create();
        $reclamation = Reclamation::query()->create([
            'location_id' => $location->id,
            'locataire_id' => $tenant->id,
            'proprietaire_id' => $owner->id,
            'motif' => 'autre',
            'description' => 'Un problème a été constaté lors de la location.',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/reclamations/{$reclamation->id}", [
                'statut' => Reclamation::STATUT_TRAITEE,
                'note_admin' => 'Votre signalement a été examiné.',
            ])
            ->assertOk()
            ->assertJsonPath('statut', Reclamation::STATUT_TRAITEE)
            ->assertJsonPath('note_admin', 'Votre signalement a été examiné.');
        $this->assertDatabaseHas('admin_actions', [
            'action' => AdminAction::RECLAMATION_MISE_A_JOUR,
            'cible_id' => $reclamation->id,
            'concerne_user_id' => $tenant->id,
        ]);

        $this->actingAs($tenant)
            ->getJson('/api/locataire/reclamations')
            ->assertOk()
            ->assertJsonPath('0.statut', Reclamation::STATUT_TRAITEE)
            ->assertJsonPath('0.note_admin', 'Votre signalement a été examiné.')
            ->assertJsonMissingPath('0.proprietaire.email');
    }

    public function test_only_admins_can_access_the_complaint_dashboard(): void
    {
        $tenant = User::factory()->create();
        $owner = User::factory()->proprietaire()->create();

        $this->actingAs($tenant)->getJson('/api/admin/reclamations')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/admin/reclamations')->assertForbidden();
    }
}
