<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Sortie;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InstalleLaStation;
use Tests\TestCase;

/**
 * La matrice des permissions.
 *
 * Le point qui compte : un pompiste enregistre ses pleins mais ne les corrige
 * pas. Sans cette séparation, les trois contrôles du §5 ne protégeraient plus
 * rien — une saisie refusée se contournerait en retouchant le plein précédent.
 */
class RolesTest extends TestCase
{
    use InstalleLaStation;
    use RefreshDatabase;

    private Vehicule $vehicule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installerLaStation();

        // Le référentiel est posé par le gestionnaire connecté par le trait.
        $this->vehicule = $this->vehicule(capacite: 200);
    }

    private function commeSortie(Role $role): User
    {
        $compte = $this->compte($role, strtoupper($role->value).'-01');
        $this->actingAs($compte, 'sanctum');

        return $compte;
    }

    private function servirUnPlein(float $index = 1000): int
    {
        return $this->servir($this->vehicule, [
            'litres_servis' => 50,
            'index_compteur' => $index,
        ])->json('data.id');
    }

    // ---------------------------------------------------------------
    // Pompiste
    // ---------------------------------------------------------------

    public function test_un_pompiste_enregistre_un_plein(): void
    {
        $this->commeSortie(Role::Pompiste);

        $this->servir($this->vehicule, ['litres_servis' => 50, 'index_compteur' => 1000])
            ->assertCreated();
    }

    public function test_un_pompiste_ne_corrige_pas_un_plein(): void
    {
        $id = $this->servirUnPlein();
        $this->commeSortie(Role::Pompiste);

        $reponse = $this->putJson("/api/sorties/{$id}", [
            'vehicule_id' => $this->vehicule->id,
            'chauffeur_id' => $this->chauffeur->id,
            'litres_servis' => 80,
            'index_compteur' => 1000,
        ]);

        $reponse->assertStatus(403);
        $this->assertStringContainsString('Pompiste', $reponse->json('message'));
        $this->assertSame(50.0, Sortie::query()->find($id)->litres_servis);
    }

    public function test_un_pompiste_ne_supprime_pas_un_plein(): void
    {
        $id = $this->servirUnPlein();
        $this->commeSortie(Role::Pompiste);

        $this->deleteJson("/api/sorties/{$id}")->assertStatus(403);
        $this->assertDatabaseHas('sorties', ['id' => $id]);
    }

    public function test_un_pompiste_ne_touche_pas_au_referentiel(): void
    {
        $this->commeSortie(Role::Pompiste);

        $this->postJson('/api/vehicules', [
            'code' => 'X-1',
            'designation' => 'Refusé',
            'carburant_id' => $this->gasoil->id,
            'mode_suivi' => 'km',
            'capacite_reservoir' => 50,
        ])->assertStatus(403);

        $this->postJson('/api/chauffeurs', ['nom' => 'Refusé', 'matricule' => 'X-1'])
            ->assertStatus(403);

        $this->putJson("/api/carburants/{$this->gasoil->id}", [
            'libelle' => 'Gasoil',
            'prix_par_defaut' => 1,
            'cuve' => ['nom' => 'X', 'capacite' => 1],
        ])->assertStatus(403);
    }

    public function test_un_pompiste_n_enregistre_pas_de_livraison(): void
    {
        $this->commeSortie(Role::Pompiste);

        $this->postJson('/api/entrees', [
            'carburant_id' => $this->gasoil->id,
            'fournisseur' => 'Refusé',
            'quantite_litres' => 100,
            'prix_unitaire' => 945,
        ])->assertStatus(403);
    }

    public function test_un_pompiste_ne_gere_pas_les_comptes(): void
    {
        $this->commeSortie(Role::Pompiste);

        $this->getJson('/api/utilisateurs')->assertStatus(403);
        $this->postJson('/api/utilisateurs', [
            'nom' => 'Refusé',
            'matricule' => 'X-1',
            'role' => 'gestionnaire',
            'password' => 'motdepasse1',
        ])->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Consultation
    // ---------------------------------------------------------------

    public function test_la_consultation_lit_et_exporte_mais_ne_sert_pas(): void
    {
        $this->commeSortie(Role::Consultation);

        $this->getJson('/api/stock')->assertOk();
        $this->getJson('/api/sorties')->assertOk();
        $this->get('/api/exports/mensuel?annee=2026&mois=8')->assertOk();

        $this->servir($this->vehicule, ['litres_servis' => 50, 'index_compteur' => 1000])
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Gestionnaire — et le garde-fou du dernier d'entre eux
    // ---------------------------------------------------------------

    public function test_un_gestionnaire_corrige_et_supprime(): void
    {
        $id = $this->servirUnPlein();

        $this->putJson("/api/sorties/{$id}", [
            'vehicule_id' => $this->vehicule->id,
            'chauffeur_id' => $this->chauffeur->id,
            'litres_servis' => 80,
            'index_compteur' => 1000,
        ])->assertOk();

        $this->deleteJson("/api/sorties/{$id}")->assertNoContent();
    }

    public function test_le_dernier_gestionnaire_actif_ne_peut_pas_etre_supprime(): void
    {
        $autre = $this->compte(Role::Pompiste, 'POMPE-09');
        $this->actingAs($autre, 'sanctum');

        // Un pompiste n'aurait de toute façon pas le droit ; on repasse
        // gestionnaire pour éprouver le garde-fou lui-même.
        $this->actingAs($this->gestionnaire, 'sanctum');

        $reponse = $this->deleteJson("/api/utilisateurs/{$this->gestionnaire->id}");

        $reponse->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $this->gestionnaire->id]);
    }

    public function test_le_dernier_gestionnaire_actif_ne_peut_pas_etre_retrograde(): void
    {
        $this->putJson("/api/utilisateurs/{$this->gestionnaire->id}", [
            'nom' => $this->gestionnaire->nom,
            'matricule' => $this->gestionnaire->matricule,
            'role' => 'pompiste',
        ])->assertStatus(409);

        $this->assertSame(Role::Gestionnaire, $this->gestionnaire->fresh()->role);
    }

    public function test_un_gestionnaire_peut_etre_retrograde_s_il_en_reste_un_autre(): void
    {
        $this->compte(Role::Gestionnaire, 'ADMIN-2');

        $this->putJson("/api/utilisateurs/{$this->gestionnaire->id}", [
            'nom' => $this->gestionnaire->nom,
            'matricule' => $this->gestionnaire->matricule,
            'role' => 'pompiste',
        ])->assertOk();

        $this->assertSame(Role::Pompiste, $this->gestionnaire->fresh()->role);
    }

    public function test_un_gestionnaire_ne_supprime_pas_son_propre_compte(): void
    {
        $this->compte(Role::Gestionnaire, 'ADMIN-2');

        $this->deleteJson("/api/utilisateurs/{$this->gestionnaire->id}")
            ->assertStatus(409);
    }

    public function test_un_gestionnaire_cree_un_compte_et_reattribue_un_mot_de_passe(): void
    {
        $id = $this->postJson('/api/utilisateurs', [
            'nom' => 'Pompiste de service',
            'matricule' => 'POMPE-02',
            'role' => 'pompiste',
            'password' => 'motdepasse1',
        ])->assertCreated()->json('data.id');

        // Sans adresse électronique, c'est ici que se réattribue un mot de
        // passe oublié.
        $this->putJson("/api/utilisateurs/{$id}", [
            'nom' => 'Pompiste de service',
            'matricule' => 'POMPE-02',
            'role' => 'pompiste',
            'password' => 'nouveaumotdepasse',
        ])->assertOk();

        $this->postJson('/api/deconnexion');

        $this->postJson('/api/connexion', [
            'matricule' => 'POMPE-02',
            'password' => 'nouveaumotdepasse',
        ])->assertOk();
    }
}
