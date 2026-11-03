<?php

namespace Tests\Feature;

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

    public function test_catalogue_searches_name_and_description_and_applies_category_and_price_filters(): void
    {
        $proprietaire = User::factory()->proprietaire()->create();
        Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Boitier compact',
            'categorie' => 'photo',
            'description' => 'Appareil reflex professionnel',
            'prix_journalier' => '50.00',
        ]);
        Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Projecteur',
            'categorie' => 'video',
            'description' => 'Boitier lumineux',
            'prix_journalier' => '75.00',
        ]);
        Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Boitier ancien',
            'categorie' => 'photo',
            'prix_journalier' => '20.00',
        ]);
        Materiel::factory()->for($proprietaire, 'proprietaire')->create([
            'nom' => 'Boitier indisponible',
            'categorie' => 'photo',
            'prix_journalier' => '50.00',
            'disponible' => false,
        ]);

        $this->getJson('/api/materiels?q=Boitier&categorie=photo&prix_min=40&prix_max=60')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nom', 'Boitier compact')
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

        $this->getJson("/api/materiels/{$materiel->id}")
            ->assertOk()
            ->assertJsonPath('id', $materiel->id)
            ->assertJsonPath('proprietaire.name', $proprietaire->name)
            ->assertJsonPath('proprietaire.photo', 'profiles/proprietaire.jpg')
            ->assertJsonMissingPath('proprietaire.telephone');
    }

    public function test_public_reserved_periods_only_include_confirmed_locations_ending_today_or_later(): void
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
            'statut' => Location::STATUT_CONFIRMEE,
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
            ->assertJsonCount(2)
            ->assertExactJson([
                ['date_debut' => '2026-11-01T00:00:00.000000Z', 'date_fin' => '2026-11-03T00:00:00.000000Z'],
                ['date_debut' => '2026-11-04T00:00:00.000000Z', 'date_fin' => '2026-11-05T00:00:00.000000Z'],
            ]);
    }
}
