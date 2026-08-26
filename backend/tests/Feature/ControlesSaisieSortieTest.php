<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModeSuivi;
use App\Models\Sortie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InstalleLaStation;
use Tests\TestCase;

/**
 * Les trois contrôles obligatoires du §5 du cahier des charges.
 *
 * « Sans ces trois contrôles, l'application ne fait que recopier le cahier
 * papier et n'apporte aucune valeur de gestion. » Ces tests sont donc la
 * partie de la suite qui protège la raison d'être du produit.
 *
 * Les pleins sont espacés d'une minute par `travel` : l'ordre de la chaîne se
 * lit maintenant sur l'horodatage, et des pleins tous posés à la même seconde
 * ne testeraient que le départage par identifiant.
 */
class ControlesSaisieSortieTest extends TestCase
{
    use InstalleLaStation;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installerLaStation();
    }

    // ---------------------------------------------------------------
    // Contrôle n°1 — index compteur inférieur au précédent : refusé
    // ---------------------------------------------------------------

    public function test_un_index_inferieur_au_precedent_est_refuse(): void
    {
        $vehicule = $this->vehicule();

        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 50000])->assertCreated();
        $this->travel(1)->minutes();

        $reponse = $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 49500]);

        $reponse->assertStatus(422)->assertJsonValidationErrors('index_compteur');
        $this->assertStringContainsString(
            'Saisie refusée',
            $reponse->json('errors.index_compteur.0'),
        );
        $this->assertSame(1, Sortie::query()->count(), 'La sortie refusée ne doit pas être enregistrée.');
    }

    public function test_un_index_egal_au_precedent_est_accepte_sans_consommation(): void
    {
        $vehicule = $this->vehicule();

        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 50000])->assertCreated();
        $this->travel(1)->minutes();

        // Le cahier des charges refuse un index « inférieur », pas un index
        // identique : un engin peut prendre du carburant sans avoir tourné.
        $reponse = $this->servir($vehicule, ['litres_servis' => 40, 'index_compteur' => 50000]);

        $reponse->assertCreated();
        $this->assertNull($reponse->json('data.consommation'));
        $this->assertFalse($reponse->json('data.anomalie'));
    }

    public function test_un_index_superieur_au_plein_suivant_est_refuse_en_modification(): void
    {
        $vehicule = $this->vehicule();

        $premier = $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 50000])
            ->json('data.id');
        $this->travel(1)->minutes();

        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 50500])->assertCreated();

        // Remonter le premier plein au-dessus du second ferait reculer le
        // compteur vu depuis le second : même compteur qui recule, même refus.
        $reponse = $this->putJson("/api/sorties/{$premier}", [
            'vehicule_id' => $vehicule->id,
            'chauffeur_id' => $this->chauffeur->id,
            'litres_servis' => 50,
            'index_compteur' => 51000,
        ]);

        $reponse->assertStatus(422)->assertJsonValidationErrors('index_compteur');
    }

    // ---------------------------------------------------------------
    // Contrôle n°2 — litres servis > capacité du réservoir : refusé
    // ---------------------------------------------------------------

    public function test_des_litres_superieurs_a_la_capacite_du_reservoir_sont_refuses(): void
    {
        $vehicule = $this->vehicule(capacite: 80);

        $reponse = $this->servir($vehicule, ['litres_servis' => 80.01, 'index_compteur' => 50000]);

        $reponse->assertStatus(422)->assertJsonValidationErrors('litres_servis');
        $this->assertStringContainsString(
            'capacité du réservoir',
            $reponse->json('errors.litres_servis.0'),
        );
        $this->assertSame(0, Sortie::query()->count());
    }

    public function test_des_litres_egaux_a_la_capacite_du_reservoir_sont_acceptes(): void
    {
        $vehicule = $this->vehicule(capacite: 80);

        $this->servir($vehicule, ['litres_servis' => 80, 'index_compteur' => 50000])->assertCreated();
    }

    // ---------------------------------------------------------------
    // Contrôle n°3 — consommation > +30 % de la moyenne : signalé en rouge
    // ---------------------------------------------------------------

    public function test_un_plein_depassant_de_plus_de_30_pourcent_la_moyenne_est_signale(): void
    {
        $vehicule = $this->vehicule(capacite: 100);

        // Repère : aucune consommation calculable sur le premier plein.
        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10000])->assertCreated();
        $this->travel(1)->minutes();

        // 50 L pour 500 km, soit 10 L/100 km : la moyenne de référence.
        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10500])->assertCreated();
        $this->travel(1)->minutes();

        // 70 L pour 500 km, soit 14 L/100 km : +40 % sur une moyenne de 10.
        $reponse = $this->servir($vehicule, ['litres_servis' => 70, 'index_compteur' => 11000]);

        $reponse->assertCreated();
        $this->assertTrue($reponse->json('data.anomalie'), 'Le plein aurait dû être signalé en rouge.');
        $this->assertSame(14.0, (float) $reponse->json('data.consommation'));
        $this->assertSame(10.0, (float) $reponse->json('data.moyenne_reference'));
        $this->assertSame(40.0, (float) $reponse->json('data.ecart_pourcentage'));
    }

    public function test_un_plein_juste_sous_le_seuil_de_30_pourcent_n_est_pas_signale(): void
    {
        $vehicule = $this->vehicule(capacite: 100);

        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10000])->assertCreated();
        $this->travel(1)->minutes();
        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10500])->assertCreated();
        $this->travel(1)->minutes();

        // 64 L pour 500 km, soit 12,8 L/100 km : +28 %, sous le seuil.
        $reponse = $this->servir($vehicule, ['litres_servis' => 64, 'index_compteur' => 11000]);

        $reponse->assertCreated();
        $this->assertFalse($reponse->json('data.anomalie'));
        $this->assertSame(28.0, (float) $reponse->json('data.ecart_pourcentage'));
    }

    public function test_un_plein_signale_est_enregistre_et_non_refuse(): void
    {
        $vehicule = $this->vehicule(capacite: 100);

        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10000]);
        $this->travel(1)->minutes();
        $this->servir($vehicule, ['litres_servis' => 50, 'index_compteur' => 10500]);
        $this->travel(1)->minutes();
        $this->servir($vehicule, ['litres_servis' => 90, 'index_compteur' => 11000]);

        // Le cahier des charges demande un signalement, pas un blocage :
        // les litres sont bel et bien sortis de la cuve, l'historique doit
        // les porter même quand ils sont suspects.
        $this->assertSame(3, Sortie::query()->count());
        $this->assertSame(1, Sortie::query()->where('anomalie', true)->count());
    }

    public function test_l_anomalie_se_mesure_en_heures_pour_les_engins(): void
    {
        $engin = $this->vehicule(ModeSuivi::Heures, capacite: 400);

        $this->servir($engin, ['litres_servis' => 180, 'index_compteur' => 100]);
        $this->travel(1)->minutes();

        // 180 L pour 10 h, soit 18 L/h.
        $this->servir($engin, ['litres_servis' => 180, 'index_compteur' => 110])->assertCreated();
        $this->travel(1)->minutes();

        // 250 L pour 10 h, soit 25 L/h : +38,9 % sur une moyenne de 18.
        $reponse = $this->servir($engin, ['litres_servis' => 250, 'index_compteur' => 120]);

        $reponse->assertCreated();
        $this->assertSame(25.0, (float) $reponse->json('data.consommation'));
        $this->assertSame(18.0, (float) $reponse->json('data.moyenne_reference'));
        $this->assertTrue($reponse->json('data.anomalie'));
    }
}
