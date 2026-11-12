<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaterielTest extends TestCase
{
    use RefreshDatabase;

    public function test_proprietaire_can_list_its_materiels_with_location_counts(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->count(2)->create(['statut' => Location::STATUT_TERMINEE]);
        Location::factory()->for($materiel, 'materiel')->create(['statut' => Location::STATUT_CONFIRMEE]);
        Location::factory()->for($materiel, 'materiel')->create(['statut' => Location::STATUT_REFUSEE]);
        Location::factory()->for($materiel, 'materiel')->create(['statut' => Location::STATUT_EN_ATTENTE]);
        Materiel::factory()->create();

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/materiels')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.locations_count', 2)
            ->assertJsonPath('0.locations_confirmees_count', 1);
    }

    public function test_only_authenticated_proprietaires_can_access_the_module(): void
    {
        $this->getJson('/api/proprietaire/materiels')
            ->assertUnauthorized()
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath('message', 'Authentification requise.');

        $locataire = User::factory()->create();

        $this->actingAs($locataire)
            ->getJson('/api/proprietaire/materiels')
            ->assertForbidden();
    }

    public function test_proprietaire_can_create_a_materiel_and_store_its_photos(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();
        $anotherProprietaire = User::factory()->proprietaire()->create();

        $response = $this->actingAs($proprietaire)->postJson('/api/materiels', [
            'nom' => 'Videoprojecteur',
            'categorie' => 'Video',
            'description' => 'Projecteur portable',
            'prix_journalier' => '35.50',
            'quantite' => 3,
            'etat' => 'bon',
            'etats_exemplaires' => ['neuf', 'bon', 'usé'],
            'proprietaire_id' => $anotherProprietaire->id,
            'photos' => [
                UploadedFile::fake()->image('projecteur.png'),
                UploadedFile::fake()->image('projecteur-profil.png'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('nom', 'Videoprojecteur')
            ->assertJsonPath('user_id', $proprietaire->id)
            ->assertJsonPath('quantite', 3)
            ->assertJsonCount(2, 'photos');

        $materiel = Materiel::where('nom', 'Videoprojecteur')->with('photos')->firstOrFail();
        $this->assertSame($proprietaire->id, $materiel->user_id);
        $this->assertSame(3, $materiel->quantite);
        $this->assertCount(2, $materiel->photos);
        foreach ($materiel->photos as $photo) {
            Storage::disk('public')->assertExists($photo->path);
        }
    }

    public function test_proprietaire_can_publish_up_to_three_photos_for_a_materiel(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();

        $response = $this->actingAs($proprietaire)->post('/api/materiels', [
            'nom' => 'Appareil photo',
            'categorie' => 'Photo',
            'prix_journalier' => '20.00',
            'etat' => 'bon',
            'etats_exemplaires' => ['bon'],
            'photos' => [
                UploadedFile::fake()->image('face.jpg'),
                UploadedFile::fake()->image('profil.jpg'),
                UploadedFile::fake()->image('dos.jpg'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(3, 'photos');

        $materiel = Materiel::findOrFail($response->json('id'));
        $this->assertCount(3, $materiel->photos);

        foreach ($materiel->photos as $photo) {
            Storage::disk('public')->assertExists($photo->path);
        }
    }

    public function test_proprietaire_can_update_its_materiel(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();

        $this->actingAs($proprietaire)
            ->putJson("/api/materiels/{$materiel->id}", [
                'nom' => 'Appareil photo',
                'categorie' => 'Photo',
                'description' => 'Reflex',
                'prix_journalier' => '42.00',
                'quantite' => 4,
                'etat' => 'neuf',
                'etats_exemplaires' => ['neuf', 'bon', 'usé', 'neuf'],
            ])
            ->assertOk()
            ->assertJsonPath('nom', 'Appareil photo')
            ->assertJsonPath('prix_journalier', '42.00')
            ->assertJsonPath('quantite', 4);

        $this->assertDatabaseHas('materiels', [
            'id' => $materiel->id,
            'nom' => 'Appareil photo',
            'prix_journalier' => 42,
            'quantite' => 4,
        ]);
        $this->assertSame(['neuf', 'bon', 'usé', 'neuf'], $materiel->fresh()->etats_exemplaires);
    }

    public function test_each_materiel_copy_must_have_a_valid_condition(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();
        $payload = [
            'nom' => 'Projecteurs',
            'categorie' => 'Video',
            'prix_journalier' => '20.00',
            'quantite' => 3,
            'etat' => 'neuf',
            'photos' => [UploadedFile::fake()->image('projecteurs.jpg')],
        ];

        $this->actingAs($proprietaire)
            ->postJson('/api/materiels', $payload + ['etats_exemplaires' => ['neuf', 'bon']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etats_exemplaires');

        $this->postJson('/api/materiels', $payload + ['etats_exemplaires' => ['neuf', 'bon', 'inconnu']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etats_exemplaires.2');

        $this->postJson('/api/materiels', $payload + ['etats_exemplaires' => ['neuf', 'bon', 'usé']])
            ->assertCreated()
            ->assertJsonPath('etats_exemplaires', ['neuf', 'bon', 'usé']);
    }

    public function test_owner_cannot_reduce_stock_below_simultaneous_confirmed_rentals(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['quantite' => 2]);
        Location::factory()->for($materiel, 'materiel')->count(2)->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ]);

        $this->actingAs($proprietaire)
            ->putJson("/api/materiels/{$materiel->id}", [
                'nom' => $materiel->nom,
                'categorie' => $materiel->categorie->name,
                'prix_journalier' => $materiel->prix_journalier,
                'etat' => $materiel->etat,
                'quantite' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Le stock ne peut pas être inférieur au nombre de locations simultanées déjà confirmées.',
            );

        $this->assertSame(2, $materiel->fresh()->quantite);
    }

    public function test_overdue_unreturned_rental_counts_against_future_confirmed_stock(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create(['quantite' => 2]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-11-03',
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ]);

        $this->actingAs($proprietaire)
            ->putJson("/api/materiels/{$materiel->id}", [
                'nom' => $materiel->nom,
                'categorie' => $materiel->categorie->name,
                'prix_journalier' => $materiel->prix_journalier,
                'etat' => $materiel->etat,
                'quantite' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Le stock ne peut pas être inférieur au nombre de locations simultanées déjà confirmées.',
            );
    }

    public function test_proprietaire_can_replace_a_materiel_photo(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();
        $oldPhoto = UploadedFile::fake()->image('ancienne.png')->store('materiels', 'public');
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $materiel->photos()->create(['path' => $oldPhoto, 'position' => 0]);

        $this->actingAs($proprietaire)
            ->put("/api/materiels/{$materiel->id}", [
                'nom' => $materiel->nom,
                'categorie' => $materiel->categorie->name,
                'prix_journalier' => $materiel->prix_journalier,
                'etat' => $materiel->etat,
                'photos' => [
                    UploadedFile::fake()->image('nouvelle-face.webp'),
                    UploadedFile::fake()->image('nouveau-profil.webp'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $materiel->refresh()->load('photos');
        $this->assertNotSame($oldPhoto, $materiel->photos->first()->path);
        $this->assertCount(2, $materiel->photos);
        Storage::disk('public')->assertExists($materiel->photos->first()->path);
        Storage::disk('public')->assertMissing($oldPhoto);
    }

    public function test_owner_can_update_availability(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();

        $this->actingAs($proprietaire)
            ->patchJson("/api/materiels/{$materiel->id}/disponibilite", ['disponible' => false])
            ->assertOk()
            ->assertJsonPath('disponible', false);

        $this->assertDatabaseHas('materiels', [
            'id' => $materiel->id,
            'disponible' => false,
        ]);
    }

    public function test_proprietaire_cannot_modify_another_proprietaires_materiel(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->create();
        $originalName = $materiel->nom;

        $this->actingAs($proprietaire)
            ->putJson("/api/materiels/{$materiel->id}", [
                'nom' => 'Tentative',
                'categorie' => 'Video',
                'prix_journalier' => '42.00',
                'etat' => 'bon',
            ])
            ->assertForbidden();

        $this->putJson("/api/materiels/{$materiel->id}", [])
            ->assertForbidden();

        $this->deleteJson("/api/materiels/{$materiel->id}")->assertForbidden();

        $this->patchJson("/api/materiels/{$materiel->id}/disponibilite", ['disponible' => false])
            ->assertForbidden();
        $this->patchJson("/api/materiels/{$materiel->id}/disponibilite", [])
            ->assertForbidden();

        $this->assertDatabaseHas('materiels', [
            'id' => $materiel->id,
            'nom' => $originalName,
            'disponible' => true,
        ]);
    }

    public function test_proprietaire_cannot_upload_a_photo_larger_than_two_megabytes(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();

        $this->actingAs($proprietaire)
            ->postJson('/api/materiels', [
                'nom' => 'Materiel',
                'categorie' => 'Photo',
                'prix_journalier' => '10.00',
                'etat' => 'bon',
                'photos' => [
                    UploadedFile::fake()->image('photo.png')->size(2049),
                    UploadedFile::fake()->image('photo-profil.png'),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photos.0');

        $this->assertDatabaseCount('materiels', 0);
    }

    public function test_proprietaire_cannot_upload_a_non_image_photo(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();

        $this->actingAs($proprietaire)
            ->postJson('/api/materiels', [
                'nom' => 'Materiel',
                'categorie' => 'Photo',
                'prix_journalier' => '10.00',
                'etat' => 'bon',
                'photos' => [
                    UploadedFile::fake()->create('photo.jpg', 10, 'text/plain'),
                    UploadedFile::fake()->image('photo-profil.jpg'),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photos.0');

        $this->assertDatabaseCount('materiels', 0);
    }

    #[DataProvider('activeLocationStatuses')]
    public function test_proprietaire_cannot_delete_materiel_with_an_active_or_upcoming_location(string $statut): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => $statut,
            'date_fin' => today()->addDay(),
        ]);

        $this->actingAs($proprietaire)
            ->deleteJson("/api/materiels/{$materiel->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ce matériel ne peut pas être supprimé car il possède une location en cours ou à venir.');

        $this->assertModelExists($materiel);
    }

    public static function activeLocationStatuses(): array
    {
        return [
            'en attente' => [Location::STATUT_EN_ATTENTE],
            'confirmée' => [Location::STATUT_CONFIRMEE],
        ];
    }

    public function test_proprietaire_cannot_delete_materiel_with_past_locations(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_TERMINEE,
            'date_fin' => today()->subDay(),
        ]);

        $this->actingAs($proprietaire)
            ->deleteJson("/api/materiels/{$materiel->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ce matériel ne peut pas être supprimé car il possède un historique de locations.');

        $this->assertModelExists($materiel);
    }

    public function test_proprietaire_can_delete_materiel_without_locations_softly_and_keep_its_photo(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();
        $photo = UploadedFile::fake()->image('photo.webp');
        $path = $photo->store('materiels', 'public');
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $materiel->photos()->create(['path' => $path, 'position' => 0]);

        $this->actingAs($proprietaire)
            ->deleteJson("/api/materiels/{$materiel->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($materiel);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_requires_the_image_mime_type_and_permitted_extension(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();

        $this->actingAs($proprietaire)
            ->postJson('/api/materiels', [
                'nom' => 'Materiel',
                'categorie' => 'Photo',
                'prix_journalier' => '10.00',
                'etat' => 'bon',
                'photos' => [
                    UploadedFile::fake()->image('photo.gif'),
                    UploadedFile::fake()->image('photo-profil.gif'),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photos.0');
    }

    public function test_proprietaire_can_publish_a_materiel_with_one_photo(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();

        $response = $this->actingAs($proprietaire)
            ->post('/api/materiels', [
                'nom' => 'Materiel avec une photo',
                'categorie' => 'Photo',
                'prix_journalier' => '10.00',
                'etat' => 'bon',
                'etats_exemplaires' => ['bon'],
                'photos' => [UploadedFile::fake()->image('unique.jpg')],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(1, 'photos')
            ->assertJsonPath('photo', fn (?string $photo): bool => is_string($photo));

        $this->assertDatabaseCount('materiels', 1);
        Storage::disk('public')->assertExists($response->json('photo'));
    }
}
