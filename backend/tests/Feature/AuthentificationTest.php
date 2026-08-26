<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Connexion par matricule.
 *
 * Ce fichier n'utilise pas le trait d'installation : celui-ci connecte
 * d'office un gestionnaire, ce qui rendrait indétectable un défaut de
 * protection des routes.
 */
class AuthentificationTest extends TestCase
{
    use RefreshDatabase;

    private function compte(array $attributs = []): User
    {
        return User::query()->create(array_merge([
            'nom' => 'Gestionnaire du dépôt',
            'matricule' => 'ADMIN',
            'role' => Role::Gestionnaire,
            'password' => 'motdepasse1',
            'actif' => true,
        ], $attributs));
    }

    public function test_une_route_protegee_refuse_un_visiteur_non_connecte(): void
    {
        $this->getJson('/api/stock')->assertStatus(401);
        $this->getJson('/api/vehicules')->assertStatus(401);
        $this->postJson('/api/sorties', [])->assertStatus(401);
    }

    public function test_la_connexion_reussit_avec_le_bon_matricule(): void
    {
        $this->compte();

        $this->postJson('/api/connexion', [
            'matricule' => 'ADMIN',
            'password' => 'motdepasse1',
        ])
            ->assertOk()
            ->assertJsonPath('data.matricule', 'ADMIN')
            ->assertJsonPath('data.role', 'gestionnaire')
            ->assertJsonPath('data.peut_gerer', true);

        $this->assertAuthenticated();
    }

    public function test_le_mot_de_passe_n_est_jamais_renvoye(): void
    {
        $this->compte();

        $reponse = $this->postJson('/api/connexion', [
            'matricule' => 'ADMIN',
            'password' => 'motdepasse1',
        ]);

        $this->assertArrayNotHasKey('password', $reponse->json('data'));
    }

    public function test_un_mauvais_mot_de_passe_est_refuse(): void
    {
        $this->compte();

        $this->postJson('/api/connexion', ['matricule' => 'ADMIN', 'password' => 'faux'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('matricule');

        $this->assertGuest();
    }

    public function test_un_matricule_inconnu_donne_le_meme_message_qu_un_mot_de_passe_faux(): void
    {
        $this->compte();

        $inconnu = $this->postJson('/api/connexion', ['matricule' => 'FANTOME', 'password' => 'x']);
        $mauvais = $this->postJson('/api/connexion', ['matricule' => 'ADMIN', 'password' => 'x']);

        // Un message distinct permettrait de deviner quels matricules existent.
        $this->assertSame(
            $inconnu->json('errors.matricule.0'),
            $mauvais->json('errors.matricule.0'),
        );
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $this->compte(['actif' => false]);

        $reponse = $this->postJson('/api/connexion', [
            'matricule' => 'ADMIN',
            'password' => 'motdepasse1',
        ]);

        $reponse->assertStatus(422);
        $this->assertStringContainsString('désactivé', $reponse->json('errors.matricule.0'));
        $this->assertGuest();
    }

    public function test_la_deconnexion_ferme_la_session(): void
    {
        $compte = $this->compte();
        $this->actingAs($compte, 'sanctum');

        $this->postJson('/api/deconnexion')->assertNoContent();
    }

    public function test_le_compte_connecte_est_interrogeable(): void
    {
        $compte = $this->compte(['nom' => 'Fatoumata Diarra', 'matricule' => 'POMPE-01', 'role' => Role::Pompiste]);
        $this->actingAs($compte, 'sanctum');

        $this->getJson('/api/moi')
            ->assertOk()
            ->assertJsonPath('data.nom', 'Fatoumata Diarra')
            ->assertJsonPath('data.peut_servir', true)
            ->assertJsonPath('data.peut_gerer', false);
    }

    public function test_un_utilisateur_change_son_mot_de_passe(): void
    {
        $compte = $this->compte();
        $this->actingAs($compte, 'sanctum');

        $this->putJson('/api/moi/mot-de-passe', [
            'actuel' => 'motdepasse1',
            'nouveau' => 'nouveaumotdepasse',
            'nouveau_confirmation' => 'nouveaumotdepasse',
        ])->assertOk();

        $this->assertTrue(Hash::check('nouveaumotdepasse', $compte->fresh()->password));
    }

    public function test_le_changement_exige_le_mot_de_passe_actuel(): void
    {
        $compte = $this->compte();
        $this->actingAs($compte, 'sanctum');

        $this->putJson('/api/moi/mot-de-passe', [
            'actuel' => 'faux',
            'nouveau' => 'nouveaumotdepasse',
            'nouveau_confirmation' => 'nouveaumotdepasse',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('actuel');

        $this->assertTrue(Hash::check('motdepasse1', $compte->fresh()->password));
    }

    public function test_un_mot_de_passe_trop_court_est_refuse(): void
    {
        $compte = $this->compte();
        $this->actingAs($compte, 'sanctum');

        $this->putJson('/api/moi/mot-de-passe', [
            'actuel' => 'motdepasse1',
            'nouveau' => 'court',
            'nouveau_confirmation' => 'court',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nouveau');
    }
}
