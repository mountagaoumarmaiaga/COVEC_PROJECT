<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Carburant;
use App\Models\Entree;
use App\Models\Sortie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InstalleLaStation;
use Tests\TestCase;

/**
 * Horodatage automatique et prix repris du carburant.
 *
 * Le pompiste ne saisit ni l'heure ni le prix : l'un vient de l'horloge du
 * serveur, l'autre du référentiel des carburants. C'est ce qui rend la saisie
 * courte — quatre champs au lieu de six — et ce qui garantit que deux pleins
 * du même jour sont valorisés au même tarif.
 */
class HorodatageEtPrixTest extends TestCase
{
    use InstalleLaStation;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installerLaStation(prix: 945);
    }

    public function test_l_horodatage_d_un_plein_est_pose_par_le_serveur(): void
    {
        Carbon::setTestNow('2026-08-26 14:37:12');

        $reponse = $this->servir($this->vehicule(), [
            'litres_servis' => 50,
            'index_compteur' => 10000,
        ]);

        $reponse->assertCreated();
        $this->assertSame('2026-08-26 14:37:12', $reponse->json('data.date_sortie'));
    }

    public function test_un_horodatage_envoye_par_le_poste_de_saisie_est_ignore(): void
    {
        Carbon::setTestNow('2026-08-26 14:37:12');

        // Une horloge de poste déréglée — ou un poste malveillant — ne doit pas
        // pouvoir antidater un plein : l'ordre de la chaîne en dépend.
        $reponse = $this->servir($this->vehicule(), [
            'date_sortie' => '2020-01-01 03:00:00',
            'litres_servis' => 50,
            'index_compteur' => 10000,
        ]);

        $reponse->assertCreated();
        $this->assertSame('2026-08-26 14:37:12', $reponse->json('data.date_sortie'));
    }

    public function test_l_horodatage_reste_corrigeable_en_modification(): void
    {
        Carbon::setTestNow('2026-08-26 14:37:12');

        $id = $this->servir($this->vehicule(), ['litres_servis' => 50, 'index_compteur' => 10000])
            ->json('data.id');

        $vehicule = Sortie::query()->find($id)->vehicule;

        $this->putJson("/api/sorties/{$id}", [
            'date_sortie' => '2026-08-26 09:15:00',
            'vehicule_id' => $vehicule->id,
            'chauffeur_id' => $this->chauffeur->id,
            'litres_servis' => 50,
            'index_compteur' => 10000,
        ])
            ->assertOk()
            ->assertJsonPath('data.date_sortie', '2026-08-26 09:15:00');
    }

    public function test_le_prix_du_plein_est_repris_du_carburant_du_vehicule(): void
    {
        $reponse = $this->servir($this->vehicule(), [
            'litres_servis' => 50,
            'index_compteur' => 10000,
        ]);

        $reponse->assertCreated();
        $this->assertSame(945.0, (float) $reponse->json('data.prix_unitaire'));
        $this->assertSame(47250.0, (float) $reponse->json('data.montant'));
    }

    public function test_un_vehicule_a_essence_prend_le_prix_de_l_essence(): void
    {
        $essence = $this->carburant('essence', 'Essence', 875, 3000);

        $reponse = $this->servir($this->vehicule(carburant: $essence), [
            'litres_servis' => 40,
            'index_compteur' => 5000,
        ]);

        $reponse->assertCreated();
        $this->assertSame(875.0, (float) $reponse->json('data.prix_unitaire'));
        $this->assertSame(35000.0, (float) $reponse->json('data.montant'));
    }

    public function test_un_prix_envoye_par_le_poste_de_saisie_est_ignore(): void
    {
        $reponse = $this->servir($this->vehicule(), [
            'litres_servis' => 50,
            'prix_unitaire' => 1,
            'index_compteur' => 10000,
        ]);

        $reponse->assertCreated();
        $this->assertSame(945.0, (float) $reponse->json('data.prix_unitaire'));
    }

    public function test_le_prix_enregistre_ne_bouge_plus_quand_le_tarif_change(): void
    {
        $id = $this->servir($this->vehicule(), ['litres_servis' => 50, 'index_compteur' => 10000])
            ->json('data.id');

        // Le carburant renchérit le mois suivant.
        Carburant::query()->find($this->gasoil->id)->update(['prix_par_defaut' => 1010]);

        // Le plein déjà servi garde son prix : c'est un fait, pas une estimation.
        $this->assertSame(945.0, Sortie::query()->find($id)->prix_unitaire);

        // Le plein suivant, lui, part au nouveau tarif.
        $this->travel(1)->minutes();
        $reponse = $this->servir(Sortie::query()->find($id)->vehicule, [
            'litres_servis' => 50,
            'index_compteur' => 10500,
        ]);

        $this->assertSame(1010.0, (float) $reponse->json('data.prix_unitaire'));
    }

    public function test_une_livraison_est_horodatee_automatiquement(): void
    {
        Carbon::setTestNow('2026-08-26 08:05:41');

        $reponse = $this->postJson('/api/entrees', [
            'carburant_id' => $this->gasoil->id,
            'fournisseur' => 'Total Énergies Mali',
            'quantite_litres' => 5000,
            'prix_unitaire' => 945,
        ]);

        $reponse->assertCreated();
        $this->assertSame('2026-08-26 08:05:41', $reponse->json('data.date_entree'));
        $this->assertSame(4725000.0, (float) $reponse->json('data.montant'));
    }

    public function test_une_livraison_exige_son_carburant(): void
    {
        $this->postJson('/api/entrees', [
            'fournisseur' => 'Total Énergies Mali',
            'quantite_litres' => 5000,
            'prix_unitaire' => 945,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('carburant_id');

        $this->assertSame(0, Entree::query()->count());
    }

    public function test_le_prix_d_une_livraison_reste_saisissable(): void
    {
        // Contrairement au plein, la livraison porte le prix du bon du
        // fournisseur, qui ne suit pas forcément le tarif de la station.
        $reponse = $this->postJson('/api/entrees', [
            'carburant_id' => $this->gasoil->id,
            'fournisseur' => 'Ola Énergie',
            'quantite_litres' => 1000,
            'prix_unitaire' => 902.5,
        ]);

        $reponse->assertCreated();
        $this->assertSame(902.5, (float) $reponse->json('data.prix_unitaire'));
    }
}
