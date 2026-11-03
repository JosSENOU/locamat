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
        Location::factory()->for($materiel, 'materiel')->count(2)->create();
        Materiel::factory()->create();

        $this->actingAs($proprietaire)
            ->getJson('/api/proprietaire/materiels')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.locations_count', 2);
    }

    public function test_only_authenticated_proprietaires_can_access_the_module(): void
    {
        $this->getJson('/api/proprietaire/materiels')
            ->assertUnauthorized()
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath('message', 'Authentification requise.');

        $locataire = User::factory()->create(['role' => User::ROLE_LOCATAIRE]);

        $this->actingAs($locataire)
            ->getJson('/api/proprietaire/materiels')
            ->assertForbidden();
    }

    public function test_proprietaire_can_create_a_materiel_and_store_its_photo(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();
        $anotherProprietaire = User::factory()->proprietaire()->create();

        $response = $this->actingAs($proprietaire)->postJson('/api/materiels', [
            'nom' => 'Videoprojecteur',
            'categorie' => 'Video',
            'description' => 'Projecteur portable',
            'prix_journalier' => '35.50',
            'etat' => 'bon',
            'proprietaire_id' => $anotherProprietaire->id,
            'photo' => UploadedFile::fake()->image('projecteur.png'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('nom', 'Videoprojecteur')
            ->assertJsonPath('proprietaire_id', $proprietaire->id);

        $materiel = Materiel::where('nom', 'Videoprojecteur')->firstOrFail();
        $this->assertSame($proprietaire->id, $materiel->proprietaire_id);
        $this->assertNotNull($materiel->photo);
        Storage::disk('public')->assertExists($materiel->photo);
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
                'etat' => 'neuf',
            ])
            ->assertOk()
            ->assertJsonPath('nom', 'Appareil photo')
            ->assertJsonPath('prix_journalier', '42.00');

        $this->assertDatabaseHas('materiels', [
            'id' => $materiel->id,
            'nom' => 'Appareil photo',
            'prix_journalier' => 42,
        ]);
    }

    public function test_proprietaire_can_replace_a_materiel_photo(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();
        $oldPhoto = UploadedFile::fake()->image('ancienne.png')->store('materiels', 'public');
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'photo' => $oldPhoto,
        ]);

        $this->actingAs($proprietaire)
            ->put("/api/materiels/{$materiel->id}", [
                'nom' => $materiel->nom,
                'categorie' => $materiel->categorie,
                'prix_journalier' => $materiel->prix_journalier,
                'etat' => $materiel->etat,
                'photo' => UploadedFile::fake()->image('nouvelle.webp'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $materiel->refresh();
        $this->assertNotSame($oldPhoto, $materiel->photo);
        Storage::disk('public')->assertExists($materiel->photo);
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
                'photo' => UploadedFile::fake()->image('photo.png')->size(2049),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

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
                'photo' => UploadedFile::fake()->create('photo.jpg', 10, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

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

    public function test_proprietaire_can_delete_materiel_without_locations_and_remove_its_photo(): void
    {
        Storage::fake('public');
        $proprietaire = User::factory()->proprietaire()->create();
        $photo = UploadedFile::fake()->image('photo.webp');
        $path = $photo->store('materiels', 'public');
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'photo' => $path,
        ]);

        $this->actingAs($proprietaire)
            ->deleteJson("/api/materiels/{$materiel->id}")
            ->assertNoContent();

        $this->assertModelMissing($materiel);
        Storage::disk('public')->assertMissing($path);
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
                'photo' => UploadedFile::fake()->image('photo.gif'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }
}
