<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModeSuivi;
use App\Models\Carburant;
use App\Models\Entree;
use App\Models\Sortie;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InstalleLaStation;
use Tests\TestCase;

/** Résultats attendus du §4 : stock par carburant, unités, historique. */
class StockEtConsommationTest extends TestCase
{
    use InstalleLaStation;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installerLaStation(capaciteCuve: 10000, prix: 945);
    }

    /** @param  array<string, mixed>  $attributs */
    private function livrer(array $attributs = []): Entree
    {
        return Entree::query()->create(array_merge([
            'date_entree' => now(),
            'carburant_id' => $this->gasoil->id,
            'fournisseur' => 'Total Énergies Mali',
            'quantite_litres' => 5000,
            'prix_unitaire' => 945,
        ], $attributs));
    }

    /** Bilan d'un carburant tel que le renvoie l'écran de stock. */
    private function bilan(string $code): array
    {
        return collect($this->getJson('/api/stock')->json('data.synthese.carburants'))
            ->firstWhere('carburant.code', $code);
    }

    public function test_le_stock_est_la_difference_entre_entrees_et_sorties(): void
    {
        $this->livrer();

        // Réservoir de 200 L : le contrôle n°2 refuserait des pleins de 150 L
        // sur le réservoir de 80 L pris par défaut.
        $vehicule = $this->vehicule(capacite: 200);
        $this->servir($vehicule, ['litres_servis' => 150, 'index_compteur' => 1000])->assertCreated();
        $this->travel(1)->minutes();
        $this->servir($vehicule, ['litres_servis' => 120, 'index_compteur' => 1800])->assertCreated();

        $this->assertSame(4730.0, app(StockService::class)->stockActuel($this->gasoil));

        $bilan = $this->bilan('gasoil');
        $this->assertSame(4730.0, (float) $bilan['stock_actuel']);
        $this->assertSame(5000.0, (float) $bilan['total_entrees']);
        $this->assertSame(270.0, (float) $bilan['total_sorties']);
    }

    public function test_les_stocks_de_deux_carburants_ne_se_melangent_pas(): void
    {
        $essence = $this->carburant('essence', 'Essence', 875, 3000);

        $this->livrer(['quantite_litres' => 5000]);
        $this->livrer(['carburant_id' => $essence->id, 'quantite_litres' => 1000, 'prix_unitaire' => 875]);

        // Un plein de gasoil ne doit rien retirer à la cuve d'essence.
        $this->servir($this->vehicule(), ['litres_servis' => 60, 'index_compteur' => 1000]);

        $this->assertSame(4940.0, (float) $this->bilan('gasoil')['stock_actuel']);
        $this->assertSame(1000.0, (float) $this->bilan('essence')['stock_actuel']);
    }

    public function test_le_taux_de_remplissage_rapporte_le_stock_a_la_capacite(): void
    {
        // 2 500 L dans une cuve de 10 000 L.
        $this->livrer(['quantite_litres' => 2500]);

        $this->assertSame(25.0, (float) $this->bilan('gasoil')['taux_remplissage']);
    }

    public function test_la_consommation_est_en_litres_aux_cent_kilometres_pour_un_vehicule(): void
    {
        $vehicule = $this->vehicule(ModeSuivi::Kilometrage, capacite: 100);

        $this->servir($vehicule, ['litres_servis' => 60, 'index_compteur' => 20000]);
        $this->travel(1)->minutes();
        $reponse = $this->servir($vehicule, ['litres_servis' => 60, 'index_compteur' => 20400]);

        // 60 L pour 400 km = 15 L/100 km.
        $this->assertSame(15.0, (float) $reponse->json('data.consommation'));
        $this->assertSame('L/100 km', $reponse->json('data.vehicule.unite_consommation'));
        $this->assertSame(400.0, (float) $reponse->json('data.distance_parcourue'));
    }

    public function test_la_consommation_est_en_litres_par_heure_pour_un_engin(): void
    {
        $engin = $this->vehicule(ModeSuivi::Heures, capacite: 400);

        $this->servir($engin, ['litres_servis' => 200, 'index_compteur' => 500]);
        $this->travel(1)->minutes();
        $reponse = $this->servir($engin, ['litres_servis' => 200, 'index_compteur' => 510]);

        // 200 L pour 10 h = 20 L/h.
        $this->assertSame(20.0, (float) $reponse->json('data.consommation'));
        $this->assertSame('L/h', $reponse->json('data.vehicule.unite_consommation'));
    }

    public function test_le_premier_plein_ne_produit_aucune_consommation(): void
    {
        $reponse = $this->servir($this->vehicule(), ['litres_servis' => 60, 'index_compteur' => 20000]);

        $reponse->assertCreated();
        $this->assertNull($reponse->json('data.consommation'));
        $this->assertNull($reponse->json('data.distance_parcourue'));
        $this->assertNull($reponse->json('data.moyenne_reference'));
    }

    public function test_supprimer_un_plein_recalcule_la_chaine_du_vehicule(): void
    {
        $vehicule = $this->vehicule(ModeSuivi::Kilometrage, capacite: 100);

        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10000]);
        $this->travel(1)->minutes();
        $intermediaire = $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10500])
            ->json('data.id');
        $this->travel(1)->minutes();
        $dernierId = $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 11000])
            ->json('data.id');

        // Avant suppression : 50 L pour les 500 km séparant les deux derniers pleins.
        $this->assertSame(500.0, Sortie::query()->find($dernierId)->distance_parcourue);

        $this->deleteJson("/api/sorties/{$intermediaire}")->assertNoContent();

        // Le dernier plein se raccroche désormais au premier : 1 000 km parcourus
        // pour les mêmes 50 L, soit une consommation divisée par deux.
        $dernier = Sortie::query()->find($dernierId);
        $this->assertSame(1000.0, $dernier->distance_parcourue);
        $this->assertSame(5.0, $dernier->consommation);
    }

    /**
     * Corriger l'heure d'un plein le déplace dans la chaîne, et le contrôle
     * n°1 suit ce déplacement : c'est la position dans le temps qui décide
     * quel index doit être inférieur à quel autre.
     */
    public function test_corriger_l_heure_d_un_plein_le_deplace_dans_la_chaine(): void
    {
        $vehicule = $this->vehicule(ModeSuivi::Kilometrage, capacite: 100);

        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10000]);
        $this->travel(1)->hours();
        $milieu = $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10600])
            ->json('data.id');
        $this->travel(1)->hours();
        $dernierId = $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 11000])
            ->json('data.id');

        $this->assertSame(400.0, Sortie::query()->find($dernierId)->distance_parcourue);

        $corps = [
            'vehicule_id' => $vehicule->id,
            'chauffeur_id' => $this->chauffeur->id,
            'litres_servis' => 50,
            'index_compteur' => 11000,
        ];

        // Ramener le dernier plein avant celui du milieu placerait un index de
        // 11 000 devant un index de 10 600 : le compteur reculerait.
        $this->putJson("/api/sorties/{$dernierId}", $corps + [
            'date_sortie' => Sortie::query()->find($milieu)->date_sortie->copy()->subMinutes(10)->format('Y-m-d H:i:s'),
        ])->assertStatus(422)->assertJsonValidationErrors('index_compteur');

        // Le décaler d'une demi-heure sans changer son rang est accepté, et la
        // chaîne reste intacte.
        $this->putJson("/api/sorties/{$dernierId}", $corps + [
            'date_sortie' => now()->addMinutes(30)->format('Y-m-d H:i:s'),
        ])->assertOk();

        $this->assertSame(400.0, Sortie::query()->find($dernierId)->distance_parcourue);
    }

    public function test_l_historique_est_consultable_vehicule_par_vehicule(): void
    {
        $premier = $this->vehicule();
        $second = $this->vehicule();

        $this->servir($premier, ['litres_servis' => 50, 'index_compteur' => 1000]);
        $this->travel(1)->minutes();
        $this->servir($premier, ['litres_servis' => 50, 'index_compteur' => 1500]);
        $this->servir($second, ['litres_servis' => 60, 'index_compteur' => 3000]);

        $this->getJson("/api/vehicules/{$premier->id}/historique")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_vehicule_avec_historique_ne_peut_pas_etre_supprime(): void
    {
        $vehicule = $this->vehicule();
        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 1000]);

        $this->deleteJson("/api/vehicules/{$vehicule->id}")->assertStatus(409);
        $this->assertDatabaseHas('vehicules', ['id' => $vehicule->id]);
    }

    public function test_le_carburant_et_sa_cuve_se_modifient_ensemble(): void
    {
        $this->putJson("/api/carburants/{$this->gasoil->id}", [
            'libelle' => 'Gasoil',
            'prix_par_defaut' => 960,
            'cuve' => ['nom' => 'Cuve rénovée', 'capacite' => 25000],
        ])
            ->assertOk()
            ->assertJsonPath('data.prix_par_defaut', 960)
            ->assertJsonPath('data.cuve.capacite', 25000)
            ->assertJsonPath('data.cuve.nom', 'Cuve rénovée');

        $this->assertSame(960.0, Carburant::query()->find($this->gasoil->id)->prix_par_defaut);
    }
}
