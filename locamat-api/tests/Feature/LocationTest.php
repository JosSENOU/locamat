<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationHistorique;
use App\Models\Materiel;
use App\Models\User;
use App\Notifications\NouvelleDemandeLocationNotification;
use App\Notifications\ProlongationDemandeeNotification;
use App\Notifications\ProlongationTraiteeNotification;
use App\Notifications\RappelLocationNotification;
use App\Services\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Dates figées : les scénarios utilisent des dates fixes et ne doivent pas dépendre du jour d'exécution.
        $this->travelTo('2026-11-03 12:00:00');
    }

    public function test_locataire_can_create_a_request_with_inclusive_dates_and_a_fixed_price(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
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
            'user_id' => $locataire->id,
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

    public function test_confirmed_locations_only_block_dates_when_all_stock_is_reserved(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['quantite' => 2]);
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-12',
            'date_fin' => '2026-11-13',
        ])->assertCreated();

        $secondRequest = Location::query()->where('statut', Location::STATUT_EN_ATTENTE)->firstOrFail();
        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$secondRequest->id}/confirmer")
            ->assertOk();

        $this->actingAs(User::factory()->create())->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-12',
            'date_fin' => '2026-11-13',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Ces dates chevauchent une location déjà confirmée.');
    }

    public function test_reserved_periods_only_mark_dates_with_no_stock_remaining(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $materiel = Materiel::factory()->create(['quantite' => 2]);
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-11',
            'date_fin' => '2026-11-13',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->getJson("/api/materiels/{$materiel->id}/periodes-reservees")
            ->assertOk()
            ->assertExactJson([
                ['date_debut' => '2026-11-11', 'date_fin' => '2026-11-12'],
            ]);
    }

    public function test_unreturned_overdue_rental_consumes_stock_until_return_confirmation(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        $materiel = Materiel::factory()->create(['quantite' => 1]);
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-11-03',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson("/api/materiels/{$materiel->id}/periodes-reservees")
            ->assertOk()
            ->assertExactJson([
                ['date_debut' => '2026-11-05', 'date_fin' => null],
            ]);

        $this->actingAs(User::factory()->create())->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-06',
            'date_fin' => '2026-11-07',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Ces dates chevauchent une location déjà confirmée.');

        $location->update(['statut' => Location::STATUT_TERMINEE]);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
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
        $proprietaire = User::factory()->create();
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

    public function test_administrators_cannot_rent_a_materiel(): void
    {
        $this->postJson('/api/locations')->assertUnauthorized();

        $admin = User::factory()->admin()->create();
        $materiel = Materiel::factory()->for(User::factory()->proprietaire()->create(), 'proprietaire')->create();

        $this->actingAs($admin)
            ->postJson('/api/locations', [
                'materiel_id' => $materiel->id,
                'date_debut' => '2026-11-10',
                'date_fin' => '2026-11-12',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_a_proprietaire_without_the_locataire_role_can_rent_another_owners_materiel(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for(User::factory()->proprietaire()->create(), 'proprietaire')->create();

        $this->actingAs($proprietaire)
            ->postJson('/api/locations', [
                'materiel_id' => $materiel->id,
                'date_debut' => '2026-11-10',
                'date_fin' => '2026-11-12',
            ])
            ->assertCreated();
    }

    public function test_proprietaire_who_is_also_locataire_can_rent_another_owners_materiel(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaireLocataire = User::factory()->proprietaire()->create();
        $proprietaireLocataire->syncRoles([User::ROLE_PROPRIETAIRE, User::ROLE_LOCATAIRE]);
        $autreProprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($autreProprietaire, 'proprietaire')->create();

        $this->actingAs($proprietaireLocataire)
            ->postJson('/api/locations', [
                'materiel_id' => $materiel->id,
                'date_debut' => '2026-11-10',
                'date_fin' => '2026-11-12',
            ])
            ->assertCreated()
            ->assertJsonPath('user_id', $proprietaireLocataire->id);

        $this->getJson('/api/locataire/locations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.materiel.id', $materiel->id);
    }

    public function test_locataire_can_request_and_owner_can_approve_a_paid_extension(): void
    {
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'prix_journalier' => '15.75',
        ]);
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'nb_jours' => 3,
            'prix_total' => '47.25',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)
            ->postJson("/api/locations/{$location->id}/prolongation", ['date_fin' => '2026-11-14'])
            ->assertOk()
            ->assertJsonPath('prolongation_statut', 'en_attente')
            ->assertJsonPath('prolongation_montant', '34.65');

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/prolongation/confirmer")
            ->assertOk()
            ->assertJsonPath('prolongation_statut', 'confirmee');

        $extendedLocation = $location->fresh();
        $this->assertSame('2026-11-14', $extendedLocation->date_fin->toDateString());
        $this->assertSame(5, $extendedLocation->nb_jours);
        $this->assertSame('81.90', $extendedLocation->prix_total);
        $this->assertSame('confirmee', $extendedLocation->prolongation_statut);
    }

    public function test_extension_request_is_sent_even_when_the_owner_must_check_availability(): void
    {
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-15',
            'date_fin' => '2026-11-17',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)
            ->postJson("/api/locations/{$location->id}/prolongation", ['date_fin' => '2026-11-15'])
            ->assertOk()
            ->assertJsonPath('prolongation_statut', 'en_attente');

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/prolongation/confirmer")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La période demandée chevauche une autre location confirmée.');

        $unchangedLocation = $location->fresh();
        $this->assertSame('2026-11-12', $unchangedLocation->date_fin->toDateString());
        $this->assertSame('en_attente', $unchangedLocation->prolongation_statut);
    }

    public function test_overdue_location_cannot_request_an_extension_after_the_deadline(): void
    {
        $this->travelTo('2026-11-14 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)
            ->postJson("/api/locations/{$location->id}/prolongation", ['date_fin' => '2026-11-13'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Une demande de prolongation doit être envoyée avant la date limite.');

        $this->assertNull($location->fresh()->prolongation_statut);
    }

    public function test_owner_approval_rechecks_extension_dates_against_new_confirmed_locations(): void
    {
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)
            ->postJson("/api/locations/{$location->id}/prolongation", ['date_fin' => '2026-11-15'])
            ->assertOk();

        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-14',
            'date_fin' => '2026-11-16',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/prolongation/confirmer")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La période demandée chevauche une autre location confirmée.');

        $unchangedLocation = $location->fresh();
        $this->assertSame('2026-11-12', $unchangedLocation->date_fin->toDateString());
        $this->assertSame('en_attente', $unchangedLocation->prolongation_statut);
    }

    public function test_owner_can_refuse_a_pending_extension_without_changing_the_location(): void
    {
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
            'prolongation_date_fin' => '2026-11-14',
            'prolongation_montant' => '20.00',
            'prolongation_statut' => 'en_attente',
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/prolongation/refuser")
            ->assertOk()
            ->assertJsonPath('prolongation_statut', 'refusee');

        $refusedLocation = $location->fresh();
        $this->assertSame('2026-11-12', $refusedLocation->date_fin->toDateString());
        $this->assertSame('refusee', $refusedLocation->prolongation_statut);
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
        $this->travelTo('2026-11-15 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
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

    public function test_proprietaire_can_terminate_a_confirmed_location_without_tenant_confirmation(): void
    {
        $this->travelTo('2026-11-15 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_TERMINEE);

        $this->assertSame(Location::STATUT_TERMINEE, $location->fresh()->statut);
        $this->assertNotNull($location->fresh()->terminee_at);
    }

    public function test_only_the_owner_can_confirm_return_and_overdue_penalty_is_calculated(): void
    {
        $this->travelTo('2026-11-15 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'prix_journalier' => '15.75',
        ]);
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'nb_jours' => 3,
            'prix_total' => '47.25',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertForbidden();

        $this->actingAs($locataire)
            ->patchJson("/api/locations/{$location->id}/confirmer-retour")
            ->assertNotFound();

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/locations')
            ->assertOk()
            ->assertJsonPath('0.penalite_jours', 3)
            ->assertJsonPath('0.penalite_montant', '51.98')
            ->assertJsonPath('0.montant_total_a_payer', '99.23');

        $this->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_TERMINEE)
            ->assertJsonPath('penalite_jours', 3)
            ->assertJsonPath('penalite_montant', '51.98')
            ->assertJsonPath('montant_total_a_payer', '99.23');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'penalite_jours' => 3,
            'penalite_montant' => '51.98',
        ]);

        $this->getJson('/api/proprietaire/stats')
            ->assertOk()
            ->assertJsonPath('revenus_mois', '99.23');
    }

    public function test_owner_cannot_confirm_return_on_the_due_date(): void
    {
        $this->travelTo('2026-11-12 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'prix_journalier' => '15.75',
        ]);
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La confirmation du retour sera possible à partir du lendemain de la date limite.');

        $this->assertSame(Location::STATUT_CONFIRMEE, $location->fresh()->statut);
    }

    public function test_owner_can_confirm_return_after_the_due_date_and_penalty_is_calculated(): void
    {
        $this->travelTo('2026-11-13 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'prix_journalier' => '15.75',
        ]);
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertOk()
            ->assertJsonPath('penalite_jours', 1)
            ->assertJsonPath('penalite_montant', '17.33');
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

        $this->travelTo('2026-11-06 12:00:00');
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

    public function test_tenant_sees_the_precise_owner_address_only_once_the_rental_is_confirmed(): void
    {
        $proprietaire = User::factory()->proprietaire()->create([
            'ville' => 'Cotonou',
            'quartier' => 'Haie Vive',
            'adresse' => 'Derrière la pharmacie',
        ]);
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $enAttente = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
        $confirmee = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $locations = collect(
            $this->actingAs($locataire)->getJson('/api/locataire/locations')->assertOk()->json(),
        )->keyBy('id');

        $attente = $locations[$enAttente->id]['materiel']['proprietaire'];
        $this->assertSame('Cotonou', $attente['ville']);
        $this->assertSame('Haie Vive', $attente['quartier']);
        $this->assertNull($attente['adresse']);

        $this->assertSame(
            'Derrière la pharmacie',
            $locations[$confirmee->id]['materiel']['proprietaire']['adresse'],
        );
    }

    public function test_a_stay_from_the_6th_to_the_10th_counts_five_days_and_keeps_its_dates_unshifted(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'prix_journalier' => '10.00',
        ]);

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-06',
            'date_fin' => '2026-11-10',
        ])->assertCreated()
            ->assertJsonPath('date_debut', '2026-11-06')
            ->assertJsonPath('date_fin', '2026-11-10')
            ->assertJsonPath('nb_jours', 5)
            ->assertJsonPath('prix_total', '50.00');
    }

    public function test_proprietaire_is_notified_when_a_rental_request_is_created(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ])->assertCreated();

        Notification::assertSentTo($proprietaire, NouvelleDemandeLocationNotification::class);
        Notification::assertNotSentTo($locataire, NouvelleDemandeLocationNotification::class);
    }

    public function test_a_pending_request_whose_start_date_has_passed_cannot_be_confirmed_but_can_be_refused(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);
        $this->travelTo('2026-11-11 09:00:00');

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/confirmer")
            ->assertUnprocessable();

        $this->assertSame(Location::STATUT_EN_ATTENTE, $location->fresh()->statut);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/refuser")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_REFUSEE);
    }

    public function test_confirmed_extension_keeps_the_initial_return_date_and_notifies_both_parties(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'nb_jours' => 3,
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)
            ->postJson("/api/locations/{$location->id}/prolongation", ['date_fin' => '2026-11-14'])
            ->assertOk();
        Notification::assertSentTo($proprietaire, ProlongationDemandeeNotification::class);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/prolongation/confirmer")
            ->assertOk()
            ->assertJsonPath('date_fin', '2026-11-14')
            ->assertJsonPath('date_fin_initiale', '2026-11-12');
        Notification::assertSentTo($locataire, ProlongationTraiteeNotification::class);

        $this->actingAs($locataire)
            ->postJson("/api/locations/{$location->id}/prolongation", ['date_fin' => '2026-11-16'])
            ->assertOk();
        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/prolongation/confirmer")
            ->assertOk()
            ->assertJsonPath('date_fin', '2026-11-16')
            ->assertJsonPath('date_fin_initiale', '2026-11-12');
    }

    public function test_reminder_command_notifies_before_handover_before_return_and_after_a_missed_return(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-09 08:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['quantite' => 5]);
        $creer = fn (string $debut, string $fin, string $statut = Location::STATUT_CONFIRMEE) => Location::factory()
            ->for($materiel, 'materiel')
            ->for($locataire, 'locataire')
            ->create(['date_debut' => $debut, 'date_fin' => $fin, 'statut' => $statut]);
        $creer('2026-11-12', '2026-11-20');
        $creer('2026-11-01', '2026-11-12');
        $creer('2026-11-01', '2026-11-08');
        $creer('2026-11-12', '2026-11-12', Location::STATUT_EN_ATTENTE);
        $creer('2026-11-20', '2026-11-25');

        $this->artisan('locations:envoyer-rappels')
            ->expectsOutput('4 rappel(s) de location envoyé(s).')
            ->assertSuccessful();

        Notification::assertSentToTimes($locataire, RappelLocationNotification::class, 3);
        Notification::assertSentToTimes($proprietaire, RappelLocationNotification::class, 4);
    }

    public function test_the_tenant_alone_gets_a_return_reminder_the_day_before_the_return_date(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-09 08:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-05',
            'date_fin' => '2026-11-10',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->artisan('locations:envoyer-rappels')
            ->expectsOutput('1 rappel(s) de location envoyé(s).')
            ->assertSuccessful();

        Notification::assertSentToTimes($locataire, RappelLocationNotification::class, 1);
        Notification::assertNotSentTo($proprietaire, RappelLocationNotification::class);
    }

    public function test_the_owner_is_asked_to_confirm_the_return_after_the_return_date(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-11 08:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-05',
            'date_fin' => '2026-11-10',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->artisan('locations:envoyer-rappels')
            ->expectsOutput('2 rappel(s) de location envoyé(s).')
            ->assertSuccessful();

        Notification::assertSentToTimes($proprietaire, RappelLocationNotification::class, 2);
        Notification::assertSentToTimes($locataire, RappelLocationNotification::class, 1);
        Notification::assertSentTo(
            $proprietaire,
            RappelLocationNotification::class,
            fn (RappelLocationNotification $notification): bool => $notification->toArray($proprietaire)['type'] === 'rappel_confirmation',
        );
    }

    public function test_owner_cannot_confirm_return_before_the_due_date(): void
    {
        $this->travelTo('2026-11-11 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->create([
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/terminer")
            ->assertUnprocessable();

        $this->assertSame(Location::STATUT_CONFIRMEE, $location->fresh()->statut);
    }

    public function test_materiel_list_counts_only_completed_rentals(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['quantite' => 5]);
        foreach ([
            Location::STATUT_TERMINEE,
            Location::STATUT_TERMINEE,
            Location::STATUT_CONFIRMEE,
            Location::STATUT_REFUSEE,
            Location::STATUT_ANNULEE,
            Location::STATUT_EN_ATTENTE,
        ] as $statut) {
            Location::factory()->for($materiel, 'materiel')->create(['statut' => $statut]);
        }

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/materiels')
            ->assertOk()
            ->assertJsonPath('0.locations_count', 2)
            ->assertJsonPath('0.locations_confirmees_count', 1);
    }

    public function test_catalogue_exposes_the_number_of_completed_rentals_of_each_materiel(): void
    {
        $materiel = Materiel::factory()->create(['quantite' => 5]);
        foreach ([
            Location::STATUT_TERMINEE,
            Location::STATUT_TERMINEE,
            Location::STATUT_CONFIRMEE,
            Location::STATUT_REFUSEE,
        ] as $statut) {
            Location::factory()->for($materiel, 'materiel')->create(['statut' => $statut]);
        }

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('data.0.locations_count', 2);

        $this->getJson("/api/materiels/{$materiel->id}")
            ->assertOk()
            ->assertJsonPath('locations_count', 2);
    }

    public function test_locataire_can_request_several_units_and_the_price_is_multiplied(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'prix_journalier' => '10.00',
            'quantite' => 5,
        ]);

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'quantite' => 3,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-11',
        ])->assertCreated()
            ->assertJsonPath('quantite', 3)
            ->assertJsonPath('nb_jours', 2)
            ->assertJsonPath('prix_total', '60.00');

        $this->assertDatabaseHas('locations', ['materiel_id' => $materiel->id, 'quantite' => 3]);
    }

    public function test_the_quantity_defaults_to_one_and_cannot_exceed_the_stock(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['quantite' => 2]);
        $payload = ['materiel_id' => $materiel->id, 'date_debut' => '2026-11-10', 'date_fin' => '2026-11-11'];

        $this->actingAs($locataire)->postJson('/api/locations', $payload)
            ->assertCreated()
            ->assertJsonPath('quantite', 1);

        $this->actingAs($locataire)->postJson('/api/locations', $payload + ['quantite' => 3])
            ->assertUnprocessable();

        $this->actingAs($locataire)->postJson('/api/locations', $payload + ['quantite' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantite');
    }

    public function test_the_requested_quantity_must_be_free_on_the_whole_period(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['quantite' => 5]);
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'quantite' => 3,
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        $payload = ['materiel_id' => $materiel->id, 'date_debut' => '2026-11-11', 'date_fin' => '2026-11-13'];

        $this->actingAs($locataire)->postJson('/api/locations', $payload + ['quantite' => 3])
            ->assertUnprocessable();

        $this->actingAs($locataire)->postJson('/api/locations', $payload + ['quantite' => 2])
            ->assertCreated();
    }

    public function test_confirming_a_request_checks_its_whole_quantity_against_the_remaining_stock(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['quantite' => 4]);
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'quantite' => 3,
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        $demande = Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'quantite' => 2,
            'statut' => Location::STATUT_EN_ATTENTE,
        ]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$demande->id}/confirmer")
            ->assertUnprocessable();

        $demande->update(['quantite' => 1]);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$demande->id}/confirmer")
            ->assertOk();
    }

    public function test_the_catalogue_stock_counts_confirmed_units_not_rentals(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $materiel = Materiel::factory()->create(['quantite' => 5]);
        Location::factory()->for($materiel, 'materiel')->create([
            'date_debut' => '2026-11-03',
            'date_fin' => '2026-11-12',
            'quantite' => 4,
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/materiels/{$materiel->id}")
            ->assertOk()
            ->assertJsonPath('quantite_disponible', 1);
    }

    public function test_every_status_change_is_recorded_in_the_location_history(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();

        $id = $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ])->assertCreated()->json('id');

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$id}/confirmer")
            ->assertOk();
        $this->actingAs($locataire)
            ->postJson("/api/locations/{$id}/prolongation", ['date_fin' => '2026-11-14'])
            ->assertOk();
        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$id}/prolongation/confirmer")
            ->assertOk();
        $this->travelTo('2026-11-15 12:00:00');
        $this->patchJson("/api/proprietaire/locations/{$id}/terminer")->assertOk();

        $this->assertSame(
            ['creation', 'confirmation', 'prolongation_demandee', 'prolongation_acceptee', 'terminee'],
            LocationHistorique::query()->where('location_id', $id)->orderBy('id')->pluck('evenement')->all(),
        );

        $this->getJson('/api/proprietaire/demandes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonCount(5, 'data.0.historiques')
            ->assertJsonPath('data.0.historiques.0.evenement', LocationHistorique::CREATION)
            ->assertJsonPath('data.0.historiques.0.statut', Location::STATUT_EN_ATTENTE)
            ->assertJsonPath('data.0.historiques.0.acteur.name', $locataire->name)
            ->assertJsonPath('data.0.historiques.4.evenement', LocationHistorique::TERMINEE)
            ->assertJsonPath('data.0.historiques.4.statut', Location::STATUT_TERMINEE)
            ->assertJsonPath('data.0.historiques.4.acteur.name', $proprietaire->name);
    }

    public function test_refusal_and_cancellation_are_recorded_with_their_reason_and_author(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $attributs = [
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_EN_ATTENTE,
        ];
        $aRefuser = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create($attributs);
        $aAnnuler = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create($attributs);

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$aRefuser->id}/refuser", ['motif_refus' => 'En maintenance.'])
            ->assertOk();
        $this->actingAs($locataire)
            ->patchJson("/api/locations/{$aAnnuler->id}/annuler")
            ->assertOk();

        $this->assertDatabaseHas('location_historiques', [
            'location_id' => $aRefuser->id,
            'user_id' => $proprietaire->id,
            'evenement' => LocationHistorique::REFUS,
            'statut' => Location::STATUT_REFUSEE,
            'note' => 'En maintenance.',
        ]);
        $this->assertDatabaseHas('location_historiques', [
            'location_id' => $aAnnuler->id,
            'user_id' => $locataire->id,
            'evenement' => LocationHistorique::ANNULATION,
            'statut' => Location::STATUT_ANNULEE,
        ]);
    }

    public function test_the_tenant_sees_the_history_of_a_refused_extension(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)
            ->postJson("/api/locations/{$location->id}/prolongation", ['date_fin' => '2026-11-14'])
            ->assertOk();
        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/prolongation/refuser")
            ->assertOk();

        $this->actingAs($locataire)
            ->getJson('/api/locataire/locations')
            ->assertOk()
            ->assertJsonPath('0.historiques.0.evenement', LocationHistorique::PROLONGATION_DEMANDEE)
            ->assertJsonPath('0.historiques.0.note', 'Nouvelle date de retour demandée : 14/11/2026.')
            ->assertJsonPath('0.historiques.1.evenement', LocationHistorique::PROLONGATION_REFUSEE)
            ->assertJsonPath('0.historiques.1.acteur.name', $proprietaire->name);
    }

    public function test_owner_can_filter_demands_by_materiel_to_read_its_history(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $premier = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $second = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($premier, 'materiel')->count(2)->create(['statut' => Location::STATUT_TERMINEE]);
        Location::factory()->for($second, 'materiel')->create(['statut' => Location::STATUT_REFUSEE]);

        $this->actingAs($proprietaire)
            ->getJson("/api/proprietaire/demandes?materiel_id={$premier->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_the_day_before_reminder_tells_the_tenant_the_material_is_not_returned_yet(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-09 08:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['nom' => 'Perceuse Pro']);
        Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-05',
            'date_fin' => '2026-11-10',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->artisan('locations:envoyer-rappels')->assertSuccessful();

        Notification::assertSentTo(
            $locataire,
            RappelLocationNotification::class,
            function (RappelLocationNotification $notification) use ($locataire): bool {
                $donnees = $notification->toArray($locataire);

                return $notification->via($locataire) === ['database', 'mail']
                    && $donnees['titre'] === 'Date limite de retour demain'
                    && str_contains($donnees['message'], 'Perceuse Pro')
                    && str_contains($donnees['message'], 'pas encore enregistré comme rendu')
                    && $donnees['lien'] === '/mes-locations';
            },
        );
    }

    public function test_no_day_before_reminder_is_sent_once_the_material_is_returned(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-09 08:00:00');
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for(User::factory()->proprietaire()->create(), 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-05',
            'date_fin' => '2026-11-10',
            'statut' => Location::STATUT_TERMINEE,
        ]);

        $this->artisan('locations:envoyer-rappels')
            ->expectsOutput('0 rappel(s) de location envoyé(s).')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_reminder_is_stored_in_the_app_and_never_sent_twice_on_the_same_day(): void
    {
        $this->travelTo('2026-11-09 08:00:00');
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for(User::factory()->proprietaire()->create(), 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-05',
            'date_fin' => '2026-11-10',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->artisan('locations:envoyer-rappels')
            ->expectsOutput('1 rappel(s) de location envoyé(s).')
            ->assertSuccessful();

        $this->travelTo('2026-11-09 14:00:00');

        $this->artisan('locations:envoyer-rappels')
            ->expectsOutput('0 rappel(s) de location envoyé(s).')
            ->assertSuccessful();

        $this->assertSame(1, $locataire->notifications()->count());
        $this->assertSame('rappel_retour', $locataire->notifications()->first()->data['type']);
    }
}
