<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function creerDemande(User $locataire, User $proprietaire): void
    {
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['nom' => 'Caméra 4K']);

        $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ])->assertCreated();
    }

    public function test_a_rental_request_creates_an_in_app_notification_for_the_owner_only(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();

        $this->creerDemande($locataire, $proprietaire);

        $this->assertCount(1, $proprietaire->notifications);
        $this->assertCount(0, $locataire->notifications);

        $data = $proprietaire->notifications->first()->data;
        $this->assertSame('nouvelle_demande', $data['type']);
        $this->assertSame('/proprietaire/demandes?statut=en_attente', $data['lien']);
        $this->assertStringContainsString('Caméra 4K', $data['message']);
        $this->assertStringContainsString($locataire->name, $data['message']);
    }

    public function test_the_owner_can_list_notifications_with_the_unread_count(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $this->creerDemande($locataire, $proprietaire);

        $this->actingAs($proprietaire)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'nouvelle_demande')
            ->assertJsonPath('data.0.titre', 'Nouvelle demande de location')
            ->assertJsonPath('data.0.lu', false);

        $this->actingAs($proprietaire)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertExactJson(['unread_count' => 1]);

        $this->actingAs($locataire)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_a_notification_can_be_marked_as_read(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $this->creerDemande($locataire, $proprietaire);
        $id = $proprietaire->notifications->first()->id;

        $this->actingAs($proprietaire)
            ->patchJson("/api/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('data.lu', true);

        $this->assertSame(0, $proprietaire->unreadNotifications()->count());
    }

    public function test_all_notifications_can_be_marked_as_read(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $this->creerDemande($locataire, $proprietaire);
        $this->creerDemande($locataire, $proprietaire);

        $this->actingAs($proprietaire)
            ->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertExactJson(['unread_count' => 0]);

        $this->assertSame(0, $proprietaire->unreadNotifications()->count());
        $this->assertCount(2, $proprietaire->notifications);
    }

    public function test_a_user_cannot_read_someone_elses_notification(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $this->creerDemande($locataire, $proprietaire);
        $id = $proprietaire->notifications->first()->id;

        $this->actingAs($locataire)
            ->patchJson("/api/notifications/{$id}/read")
            ->assertNotFound();

        $this->assertSame(1, $proprietaire->unreadNotifications()->count());
    }

    public function test_the_tenant_is_notified_when_the_owner_confirms_or_refuses_a_request(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $this->creerDemande($locataire, $proprietaire);
        $location = Location::query()->firstOrFail();

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$location->id}/confirmer")
            ->assertOk();

        $this->assertSame('demande_confirmee', $locataire->fresh()->notifications->first()->data['type']);

        $autreLocataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $this->actingAs($autreLocataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-20',
            'date_fin' => '2026-11-21',
        ])->assertCreated();
        $autreLocation = Location::query()->where('user_id', $autreLocataire->id)->firstOrFail();

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$autreLocation->id}/refuser", ['motif_refus' => 'Indisponible'])
            ->assertOk();

        $this->assertSame('demande_refusee', $autreLocataire->fresh()->notifications->first()->data['type']);
        $this->assertCount(0, $proprietaire->fresh()->notifications->where('data.type', 'demande_confirmee'));
    }

    public function test_reminders_appear_in_the_app_once_per_rental_without_any_scheduler_and_without_email(): void
    {
        $this->travelTo('2026-11-09 23:40:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $location = Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-05',
            'date_fin' => '2026-11-11',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $rappel = $locataire->notifications()->first();
        $this->assertSame('rappel_retour', $rappel->data['type']);
        $this->assertSame($location->id, $rappel->data['location_id']);
        $this->assertSame(['database'], (new \App\Notifications\RappelLocationNotification($location, 'retour', false, 2, avecMail: false))->via($locataire));

        // Le propriétaire reçoit aussi son rappel, une seule fois.
        $this->actingAs($proprietaire)->getJson('/api/notifications')->assertOk();
        $this->assertSame(1, $proprietaire->notifications()->where('data->type', 'rappel_retour')->count());

        // Rouvrir l'application ne recrée rien, même plus tard.
        \Illuminate\Support\Facades\Cache::flush();
        $this->travelTo('2026-11-10 09:00:00');
        $this->actingAs($locataire)->getJson('/api/notifications/unread-count')->assertJsonPath('unread_count', 1);
        $this->assertSame(1, $locataire->notifications()->count());
    }

    public function test_a_missed_return_is_caught_up_when_the_app_is_next_opened(): void
    {
        $this->travelTo('2026-11-15 07:00:00');
        $locataire = User::factory()->create();
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-11-10',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);
        Location::factory()->for($materiel, 'materiel')->for($locataire, 'locataire')->create([
            'date_debut' => '2026-11-30',
            'date_fin' => '2026-12-05',
            'statut' => Location::STATUT_CONFIRMEE,
        ]);

        $this->actingAs($locataire)->getJson('/api/notifications/unread-count')->assertJsonPath('unread_count', 1);
        $this->assertSame('rappel_retard', $locataire->notifications()->first()->data['type']);

        $this->actingAs($proprietaire)->getJson('/api/notifications/unread-count')->assertJsonPath('unread_count', 1);
        $this->assertSame('rappel_confirmation', $proprietaire->notifications()->first()->data['type']);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->patchJson('/api/notifications/read-all')->assertUnauthorized();
    }
}
