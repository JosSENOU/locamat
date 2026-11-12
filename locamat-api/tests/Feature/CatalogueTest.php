<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalogue_lists_only_available_materials_with_twelve_per_page(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        Materiel::factory()->for($proprietaire, 'proprietaire')->count(13)->create();
        Materiel::factory()->for($proprietaire, 'proprietaire')->create(['disponible' => false]);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonCount(12, 'data')
            ->assertJsonMissing(['disponible' => false]);

        $this->getJson('/api/materiels?page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 13)
            ->assertJsonCount(1, 'data');
    }

    public function test_public_categories_endpoint_lists_database_categories(): void
    {
        Category::factory()->create(['name' => 'Photo']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Photo']);
    }

    public function test_catalogue_returns_only_the_single_photo_published_for_a_materiel(): void
    {
        $materiel = Materiel::factory()->create();
        $materiel->photos()->create(['path' => 'materiels/unique.jpg', 'position' => 0]);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('data.0.id', $materiel->id)
            ->assertJsonPath('data.0.photo', 'materiels/unique.jpg')
            ->assertJsonPath('data.0.photos', ['materiels/unique.jpg']);
    }

    public function test_catalogue_hides_material_until_overdue_units_are_returned(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $fullyReserved = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Tous les exemplaires empruntés',
            'quantite' => 1,
        ]);
        Location::factory()->for($fullyReserved, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-03',
            'date_fin' => '2026-11-04',
        ]);
        $partiallyReserved = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Un exemplaire restant',
            'quantite' => 2,
        ]);
        Location::factory()->for($partiallyReserved, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-03',
            'date_fin' => '2026-11-04',
        ]);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $partiallyReserved->id)
            ->assertJsonPath('data.0.quantite', 2)
            ->assertJsonPath('data.0.quantite_disponible', 1);

        $this->getJson("/api/materiels/{$fullyReserved->id}")
            ->assertOk()
            ->assertJsonPath('quantite_disponible', 0);

        Location::query()
            ->where('materiel_id', $fullyReserved->id)
            ->update(['statut' => Location::STATUT_TERMINEE]);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.quantite_disponible', 1);
    }

    public function test_future_confirmed_rentals_reduce_catalogue_stock_immediately(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'quantite' => 3,
        ]);
        $demande = $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-12',
        ])->assertCreated();

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$demande->json('id')}/confirmer")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_CONFIRMEE);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.quantite', 3)
            ->assertJsonPath('data.0.quantite_disponible', 2);

        Location::factory()->for($materiel, 'materiel')->count(2)->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-20',
            'date_fin' => '2026-11-22',
        ]);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_catalogue_stock_is_restored_when_owner_confirms_the_return(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $locataire = User::factory()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'quantite' => 3,
        ]);
        $demande = $this->actingAs($locataire)->postJson('/api/locations', [
            'materiel_id' => $materiel->id,
            'date_debut' => '2026-11-05',
            'date_fin' => '2026-11-06',
        ])->assertCreated();
        $locationId = $demande->json('id');

        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$locationId}/confirmer")
            ->assertOk();

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('data.0.quantite_disponible', 2);

        $this->travelTo('2026-11-07 12:00:00');
        $this->actingAs($proprietaire)
            ->patchJson("/api/proprietaire/locations/{$locationId}/terminer")
            ->assertOk()
            ->assertJsonPath('statut', Location::STATUT_TERMINEE);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('data.0.quantite', 3)
            ->assertJsonPath('data.0.quantite_disponible', 3);

        $this->getJson('/api/proprietaire/materiels')
            ->assertOk()
            ->assertJsonPath('0.quantite_disponible', 3);
    }

    public function test_owner_can_increase_stock_after_buying_another_unit(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        $proprietaire = User::factory()->proprietaire()->create();
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'quantite' => 3,
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-05',
            'date_fin' => '2026-11-06',
        ]);

        $this->actingAs($proprietaire)
            ->putJson("/api/materiels/{$materiel->id}", [
                'nom' => $materiel->nom,
                'categorie' => $materiel->categorie->name,
                'prix_journalier' => $materiel->prix_journalier,
                'etat' => $materiel->etat,
                'quantite' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('quantite', 4)
            ->assertJsonPath('quantite_disponible', 3);

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('data.0.quantite', 4)
            ->assertJsonPath('data.0.quantite_disponible', 3);
    }

    public function test_catalogue_searches_name_and_description_and_applies_category_and_price_filters(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        $photo = Category::factory()->create(['name' => 'photo']);
        $video = Category::factory()->create(['name' => 'video']);
        $materielAvecPhotos = Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Boitier compact',
            'categorie_id' => $photo->id,
            'description' => 'Appareil reflex professionnel',
            'prix_journalier' => '50.00',
        ]);
        $materielAvecPhotos->photos()->createMany([
            ['path' => 'materiels/face.jpg', 'position' => 0],
            ['path' => 'materiels/profil.jpg', 'position' => 1],
        ]);
        Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Projecteur',
            'categorie_id' => $video->id,
            'description' => 'Boitier lumineux',
            'prix_journalier' => '75.00',
        ]);
        Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Boitier ancien',
            'categorie_id' => $photo->id,
            'prix_journalier' => '20.00',
        ]);
        Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Boitier indisponible',
            'categorie_id' => $photo->id,
            'prix_journalier' => '50.00',
            'disponible' => false,
        ]);

        $this->getJson('/api/materiels?q=Boitier&categorie=photo&prix_min=40&prix_max=60')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nom', 'Boitier compact')
            ->assertJsonPath('data.0.photo', 'materiels/face.jpg')
            ->assertJsonPath('data.0.photos.0', 'materiels/face.jpg')
            ->assertJsonPath('data.0.photos.1', 'materiels/profil.jpg')
            ->assertJsonPath('data.0.proprietaire.name', $proprietaire->name);

        $this->getJson('/api/materiels?q=reflex')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nom', 'Boitier compact');

        $this->getJson('/api/materiels?q=lumineux')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nom', 'Projecteur');
    }

    public function test_catalogue_rejects_an_invalid_price_range(): void
    {
        $this->getJson('/api/materiels?prix_min=100&prix_max=50')
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['prix_max']])
            ->assertJsonPath(
                'errors.prix_max.0',
                'Le champ prix maximum doit être supérieur ou égal à 100.',
            );
    }

    public function test_public_material_detail_includes_owner_name_but_not_private_phone(): void
    {
        $proprietaire = User::factory()->proprietaire()->create([
            'telephone' => '+33123456789',
            'photo' => 'profiles/proprietaire.jpg',
        ]);
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();
        $materiel->photos()->createMany([
            ['path' => 'materiels/face.jpg', 'position' => 0],
            ['path' => 'materiels/profil.jpg', 'position' => 1],
            ['path' => 'materiels/dos.jpg', 'position' => 2],
        ]);

        $this->getJson("/api/materiels/{$materiel->id}")
            ->assertOk()
            ->assertJsonPath('id', $materiel->id)
            ->assertJsonPath('proprietaire.name', $proprietaire->name)
            ->assertJsonPath('proprietaire.photo', 'profiles/proprietaire.jpg')
            ->assertJsonPath('photos', [
                'materiels/face.jpg',
                'materiels/profil.jpg',
                'materiels/dos.jpg',
            ])
            ->assertJsonMissingPath('proprietaire.telephone');
    }

    public function test_public_reserved_periods_exclude_locations_already_returned(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        $materiel = Materiel::factory()->create();
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-11-03',
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_CONFIRMEE,
            'date_debut' => '2026-11-04',
            'date_fin' => '2026-11-05',
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_TERMINEE,
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-11-02',
        ]);
        Location::factory()->for($materiel, 'materiel')->create([
            'statut' => Location::STATUT_EN_ATTENTE,
            'date_debut' => '2026-11-04',
            'date_fin' => '2026-11-05',
        ]);

        $this->getJson("/api/materiels/{$materiel->id}/periodes-reservees")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertExactJson([
                ['date_debut' => '2026-11-03', 'date_fin' => '2026-11-05'],
            ]);
    }

    public function test_materiel_exposes_the_public_localisation_of_its_owner_but_not_the_precise_address(): void
    {
        $proprietaire = User::factory()->proprietaire()->create([
            'telephone' => '+22997000000',
            'ville' => 'Cotonou',
            'quartier' => 'Haie Vive',
            'adresse' => 'Derrière la pharmacie',
        ]);
        $materiel = Materiel::factory()->for($proprietaire, 'proprietaire')->create();

        $this->getJson("/api/materiels/{$materiel->id}")
            ->assertOk()
            ->assertJsonPath('proprietaire.ville', 'Cotonou')
            ->assertJsonPath('proprietaire.quartier', 'Haie Vive')
            ->assertJsonMissingPath('proprietaire.adresse')
            ->assertJsonMissingPath('proprietaire.telephone');

        $this->getJson('/api/materiels')
            ->assertOk()
            ->assertJsonPath('data.0.proprietaire.ville', 'Cotonou')
            ->assertJsonMissingPath('data.0.proprietaire.adresse');
    }
}
