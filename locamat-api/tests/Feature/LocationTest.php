<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_locataire_can_create_a_request_with_inclusive_dates_and_a_fixed_price(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create(['role' => User::ROLE_LOCATAIRE]);
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'prix_journalier' => '15.75',
        ]);

        $response = $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'message' => 'J’en ai besoin pour une présentation.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('statut', Location::STATUT_EN_ATTENTE)
            ->assertJsonPath('nb_jours', 3)
            ->assertJsonPath('prix_total', '47.25')
            ->assertJsonPath('message', 'J’en ai besoin pour une présentation.');

        $this->assertDatabaseHas('locations', [
            'materiel_id' => $materiel->id,
            'locataire_id' => $locataire->id,
            'nb_jours' => 3,
            'prix_total' => 47.25,
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);

        $materiel->update(['prix_journalier' => '30.00']);
        $this->assertDatabaseHas('locations', [
            'id' => $response->json('id'),
            'prix_total' => 47.25,
        ]);
    }

    public function test_pending_requests_do_not_block_the_same_dates(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ])->assertCreated();

        $this->assertDatabaseCount('locations', 2);
    }

    public function test_confirmed_location_blocks_overlapping_dates_including_shared_endpoints(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-12',
            'date_fin' => '2026-11-13',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Ces dates chevauchent une location déjà confirmée.');

        $this->assertDatabaseCount('locations', 1);
    }

    public function test_unavailable_materiel_cannot_receive_a_request(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'disponible' => false,
        ]);

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Ce matériel est indisponible.')
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath('errors', []);

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_locataire_cannot_request_its_own_materiel(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->create(['role' => User::ROLE_LOCATAIRE]);
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();

        $this->actingAs($proprietaire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Vous ne pouvez pas louer votre propre matériel.');

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_invalid_date_ranges_are_rejected(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->create();

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-02',
            'date_fin' => '2026-11-03',
        ])->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['date_debut']])
            ->assertJsonPath(
                'errors.date_debut.0',
                'La date de début doit être aujourd’hui ou une date ultérieure.',
            );

        $this->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-12',
            'date_fin' => '2026-11-10',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('date_fin');

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_only_authenticated_locataires_can_create_a_request(): void
    {
        $this->postJson('/api/locations')->assertUnauthorized();

        $proprietaire = User::factory()->proprietaire()->create();

        $this->actingAs($proprietaire)->postJson('/api/locations')->assertForbidden();
    }

    public function test_proprietaire_can_confirm_a_pending_request(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/confirmer")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_CONFIRMEE);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
    }

    public function test_confirmation_is_rejected_when_another_confirmed_request_overlaps(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $pendingLocation = Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
        $confirmedLocation = Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-12',
            'date_fin' => '2026-11-14',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        $this->assertDatabaseHas('locations', [
            'id' => $confirmedLocation->id,
            'materiel_id' => $materiel->id,
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        $this->assertDatabaseCount('locations', 2);
        $this->assertFalse(app(LocationService::class)->datesLibres(
            (int) $materiel->id,
            '2026-11-10',
            '2026-11-12',
            (int) $pendingLocation->id,
        ));

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$pendingLocation->id}/confirmer")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ces dates chevauchent une location déjà confirmée.');

        $this->assertDatabaseHas('locations', [
            'id' => $pendingLocation->id,
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
    }

    public function test_non_owner_gets_403_when_confirming_a_request(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $otherProprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create();

        $this->actingAs($otherProprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/confirmer")
            ->assertForbidden();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
    }

    public function test_non_owner_gets_403_when_refusing_a_request(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $otherProprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create();

        $this->actingAs($otherProprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/refuser")
            ->assertForbidden();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
    }

    public function test_proprietaire_can_refuse_a_pending_request_with_a_reason(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create();

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/refuser", [
                'motif_refus' => 'Le matériel est en maintenance.',
            ])
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_REFUSEE)
            ->assertJsonPath('motif_refus', 'Le matériel est en maintenance.');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_REFUSEE,
            'motif_refus' => 'Le matériel est en maintenance.',
        ]);
    }

    public function test_proprietaire_cannot_refuse_a_request_that_is_not_pending(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/refuser", [
                'motif_refus' => 'Trop tard',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_CONFIRMEE,
            'motif_refus' => null,
        ]);
    }

    public function test_proprietaire_can_terminate_a_confirmed_request(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_TERMINEE);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_TERMINEE,
        ]);
    }

    public function test_proprietaire_cannot_terminate_a_request_that_is_not_confirmed(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create();

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertUnprocessable();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
    }

    public function test_non_owner_gets_403_when_terminating_a_request(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $otherProprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($otherProprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertForbidden();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
    }

    public function test_locataire_can_cancel_its_pending_request(): void
    {
        $locataire = User::factory()->create();
        $location = Location::factory()->for($locataire, 'locataire')->create();

        $this->actingAs($locataire)
            ->patchJson("/api/locations/{$location->id}/annuler")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_ANNULEE);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_ANNULEE,
        ]);
    }

    public function test_locataire_gets_403_when_cancelling_another_locataires_request(): void
    {
        $locataire = User::factory()->create();
        $otherLocataire = User::factory()->create();
        $location = Location::factory()->for($otherLocataire, 'locataire')->create();

        $this->actingAs($locataire)
            ->patchJson("/api/locations/{$location->id}/annuler")
            ->assertForbidden();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
    }

    public function test_locataire_cannot_cancel_a_confirmed_request(): void
    {
        $locataire = User::factory()->create();
        $location = Location::factory()->for($locataire, 'locataire')->create([
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)
            ->patchJson("/api/locations/{$location->id}/annuler")
            ->assertUnprocessable();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
    }

    public function test_owner_and_tenant_lists_only_contain_their_own_requests(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $otherProprietaire = User::factory()->proprietaire()->create();
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $otherMateriel = Materiel::factory()->for($otherProprietaire, 'proprietaire')->create();
        $ownerLocation = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create();
        Location::factory()->for($otherMateriel, 'materiel')->create();
        Location::factory()->for($materiel, 'materiel')->create();

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/locations')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $ownerLocation->id]);

        $this->actingAs($locataire)
            ->getJson('/api/locataire/locations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $ownerLocation->id);
    }

    public function test_tenant_location_list_hides_owner_phone_until_confirmed_or_completed(): void
    {
        $this->travelTo('2026-06-15 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create([
            'telephone' => '+33123456789',
            'photo' => 'profiles/proprietaire.jpg',
        ]);
        $locataire = User::factory()->create(['photo' => 'profiles/locataire.jpg']);
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $enAttente = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
        $confirmee = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        $terminee = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'statut' => Location::STATUT_TERMINEE,
        ]);

        $response = $this->actingAs($locataire)
            ->getJson('/api/locataire/locations')
            ->assertOk();
        $locationsById = collect($response->json())->keyBy('id');

        $this->assertSame(
            null,
            $locationsById[$enAttente->id]['materiel']['proprietaire']['telephone'],
        );
        $this->assertSame(
            'profiles/proprietaire.jpg',
            $locationsById[$enAttente->id]['materiel']['proprietaire']['photo'],
        );
        $this->assertSame(
            '+33123456789',
            $locationsById[$confirmee->id]['materiel']['proprietaire']['telephone'],
        );
        $this->assertSame(
            '+33123456789',
            $locationsById[$terminee->id]['materiel']['proprietaire']['telephone'],
        );

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/demandes')
            ->assertOk()
            ->assertJsonPath('data.0.locataire.photo', 'profiles/locataire.jpg');
    }

    public function test_owner_can_filter_and_paginate_demands(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()
            ->for($materiel, 'materiel')
            ->count(11)
            ->create(['statut' => Location::STATUT_EN_ATTENTE]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        $otherProprietaire = User::factory()->proprietaire()->create();
        $otherMateriel = Materiel::factory()->for($otherProprietaire, 'proprietaire')->create();
        Location::factory()->for($otherMateriel, 'materiel')->create([
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/demandes?statut=en_attente')
            ->assertOk()
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.statut', Location::STATUT_EN_ATTENTE);

        $this->getJson('/api/proprietaire/demandes?statut=en_attente&page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 11)
            ->assertJsonCount(1, 'data');
    }

    public function test_owner_demand_filter_rejects_an_unknown_status(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/demandes?statut=inconnu')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut');
    }

    public function test_owner_stats_count_pending_active_and_monthly_completed_locations(): void
    {
        $this->travelTo('2026-06-15 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();

        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-06-15',
            'date_fin' => '2026-06-15',
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-06-16',
            'date_fin' => '2026-06-17',
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_TERMINEE,
            'prix_total' => '25.25',
            'terminee_at' => '2026-06-01 10:00:00',
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_TERMINEE,
            'prix_total' => '40.00',
            'terminee_at' => '2026-06-15 11:00:00',
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_TERMINEE,
            'prix_total' => '999.00',
            'terminee_at' => '2026-05-31 23:59:59',
        ]);

        $otherProprietaire = User::factory()->proprietaire()->create();
        $otherMateriel = Materiel::factory()->for($otherProprietaire, 'proprietaire')->create();
        Location::factory()->for($otherMateriel, 'materiel')->create([
            'statut' => Location::STATUT_TERMINEE,
            'prix_total' => '1000.00',
            'terminee_at' => '2026-06-10 10:00:00',
        ]);

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/stats')
            ->assertOk()
            ->assertExactJson([
                'demandes_en_attente' => 1,
                'locations_en_cours' => 1,
                'revenus_mois' => '65.25',
            ]);
    }

    public function test_proprietaire_routes_allow_the_short_status_action_paths(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $aConfirmer = Location::factory()->for($materiel, 'materiel')->create();
        $aRefuser = Location::factory()->for($materiel, 'materiel')->create();

        $this->actingAs($proprietaire)
            ->patchJson("/api/locations/{$aConfirmer->id}/confirmer")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_CONFIRMEE);

        $this->patchJson("/api/locations/{$aConfirmer->id}/terminer")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_TERMINEE);

        $this->patchJson("/api/locations/{$aRefuser->id}/refuser", ['motif_refus' => 'Indisponible'])
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_REFUSEE);

        $this->assertDatabaseHas('locations', [
            'id' => $aConfirmer->id,
            'statut' => Location::STATUT_TERMINEE,
        ]);
        $this->assertNotNull($aConfirmer->fresh()->terminee_at);
        $this->assertDatabaseHas('locations', [
            'id' => $aRefuser->id,
            'statut' => Location::STATUT_REFUSEE,
            'motif_refus' => 'Indisponible',
        ]);
    }

    public function test_only_proprietaires_can_read_demands_and_stats(): void
    {
        $this->getJson('/api/proprietaire/demandes')->assertUnauthorized();
        $this->getJson('/api/proprietaire/stats')->assertUnauthorized();

        $locataire = User::factory()->create();

        $this->actingAs($locataire)->getJson('/api/proprietaire/demandes')->assertForbidden();
        $this->getJson('/api/proprietaire/stats')->assertForbidden();
    }
}
