<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModeSuivi;
use App\Models\Chauffeur;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InstalleLaStation;
use Tests\TestCase;

/**
 * Recherche textuelle et pagination des listes.
 *
 * Sans pagination, un dépôt en service finit par renvoyer des milliers de
 * lignes à chaque ouverture d'écran ; sans recherche, il devient impossible
 * d'y retrouver un véhicule.
 */
class RechercheEtPaginationTest extends TestCase
{
    use InstalleLaStation;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installerLaStation();
    }

    private function parc(int $nombre): void
    {
        for ($i = 1; $i <= $nombre; $i++) {
            Vehicule::query()->create([
                'code' => sprintf('VL-%03d', $i),
                'designation' => 'Toyota Hilux '.$i,
                'carburant_id' => $this->gasoil->id,
                'mode_suivi' => ModeSuivi::Kilometrage,
                'capacite_reservoir' => 80,
                'actif' => true,
            ]);
        }
    }

    public function test_la_liste_des_vehicules_est_paginee(): void
    {
        $this->parc(30);

        $reponse = $this->actingAs($this->gestionnaire)->getJson('/api/vehicules');

        $reponse->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_on_peut_demander_la_page_suivante(): void
    {
        $this->parc(30);

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/vehicules?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_les_selecteurs_obtiennent_le_parc_entier(): void
    {
        // Un formulaire de saisie doit proposer tous les véhicules, pas les
        // vingt-cinq premiers : « tous=1 » désactive la pagination.
        $this->parc(30);

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/vehicules?tous=1')
            ->assertOk()
            ->assertJsonCount(30, 'data');
    }

    public function test_la_recherche_filtre_sur_le_code_et_la_designation(): void
    {
        Vehicule::query()->create([
            'code' => 'CM-001', 'designation' => 'Mercedes Actros',
            'carburant_id' => $this->gasoil->id, 'mode_suivi' => ModeSuivi::Kilometrage,
            'capacite_reservoir' => 300, 'actif' => true,
        ]);
        Vehicule::query()->create([
            'code' => 'GE-001', 'designation' => 'Groupe électrogène',
            'carburant_id' => $this->gasoil->id, 'mode_suivi' => ModeSuivi::Heures,
            'capacite_reservoir' => 400, 'actif' => true,
        ]);

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/vehicules?recherche=actros')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CM-001');

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/vehicules?recherche=GE-')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'GE-001');
    }

    public function test_la_recherche_ignore_la_casse(): void
    {
        // « like » distingue la casse sur PostgreSQL : chercher « hilux » doit
        // trouver « Toyota Hilux » en production comme en test.
        $this->parc(1);

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/vehicules?recherche=HILUX')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_une_recherche_vide_ne_filtre_rien(): void
    {
        $this->parc(3);

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/vehicules?recherche=')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_les_chauffeurs_se_cherchent_par_nom_et_matricule(): void
    {
        // Le socle installe déjà un chauffeur : celui-ci porte un nom distinct
        // pour que la recherche puisse être vérifiée sans ambiguïté.
        Chauffeur::query()->create(['nom' => 'Oumou Sangaré', 'matricule' => 'CH-042', 'actif' => true]);

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/chauffeurs?recherche=sangaré')
            ->assertOk()
            ->assertJsonPath('data.0.matricule', 'CH-042');

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/chauffeurs?recherche=CH-042')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_la_taille_de_page_est_plafonnee(): void
    {
        // Sans plafond, « par_page=999999 » chargerait la table entière.
        $this->parc(30);

        $this->actingAs($this->gestionnaire)
            ->getJson('/api/vehicules?par_page=999999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 200);
    }
}
