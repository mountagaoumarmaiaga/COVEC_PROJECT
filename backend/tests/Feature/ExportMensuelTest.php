<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModeSuivi;
use App\Exports\ExportMensuel;
use App\Models\Entree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\InstalleLaStation;
use Tests\TestCase;

/** Export Excel des totaux du mois (§4). */
class ExportMensuelTest extends TestCase
{
    use InstalleLaStation;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15 09:00:00');
        $this->installerLaStation(capaciteCuve: 10000, prix: 945);

        Entree::query()->create([
            'date_entree' => '2026-08-03 08:30:00',
            'carburant_id' => $this->gasoil->id,
            'fournisseur' => 'Total Énergies Mali',
            'quantite_litres' => 4000,
            'prix_unitaire' => 940,
            'reference_bon' => 'BL-2026-0714',
        ]);

        $vehicule = $this->vehicule(ModeSuivi::Kilometrage, capacite: 300);

        foreach ([[200, 50000], [200, 50500], [280, 51000]] as [$litres, $index]) {
            $this->servir($vehicule, [
                'litres_servis' => $litres,
                'index_compteur' => $index,
            ])->assertCreated();

            $this->travel(1)->hours();
        }
    }

    /** @return list<array<int, mixed>> */
    private function lignes(string $chemin, string $onglet): array
    {
        $lignes = [];
        $reader = new Reader();
        $reader->open($chemin);

        foreach ($reader->getSheetIterator() as $feuille) {
            if ($feuille->getName() !== $onglet) {
                continue;
            }

            foreach ($feuille->getRowIterator() as $ligne) {
                $lignes[] = $ligne->toArray();
            }
        }

        $reader->close();

        return $lignes;
    }

    public function test_le_classeur_contient_les_quatre_onglets_attendus(): void
    {
        $chemin = app(ExportMensuel::class)->generer(2026, 8);

        $this->assertFileExists($chemin);

        $onglets = [];
        $reader = new Reader();
        $reader->open($chemin);

        foreach ($reader->getSheetIterator() as $feuille) {
            $onglets[] = $feuille->getName();
        }

        $reader->close();
        @unlink($chemin);

        $this->assertSame(
            ['Synthèse', 'Entrées', 'Sorties', 'Consommation par véhicule'],
            $onglets,
        );
    }

    public function test_l_onglet_des_sorties_reprend_les_pleins_du_mois(): void
    {
        $chemin = app(ExportMensuel::class)->generer(2026, 8);
        $lignes = $this->lignes($chemin, 'Sorties');
        @unlink($chemin);

        // En-tête, trois pleins, ligne de total.
        $this->assertCount(5, $lignes);
        $this->assertSame('Date et heure', $lignes[0][0]);
        $this->assertSame('Gasoil', $lignes[1][3]);

        // Le troisième plein consomme 280 L sur 500 km contre 200 L sur les
        // 500 km précédents : +40 %, donc signalé jusque dans l'export.
        $this->assertSame('OUI', $lignes[3][15]);
        $this->assertSame(680.0, (float) $lignes[4][5]);

        // 680 L à 945 F : le montant suit les prix réellement enregistrés.
        $this->assertSame(642600.0, (float) $lignes[4][7]);
    }

    public function test_l_onglet_de_synthese_detaille_chaque_carburant(): void
    {
        $this->carburant('essence', 'Essence', 875, 3000);

        $chemin = app(ExportMensuel::class)->generer(2026, 8);
        $lignes = $this->lignes($chemin, 'Synthèse');
        @unlink($chemin);

        $aplat = array_map(
            fn (array $l) => implode(' | ', array_map(fn ($v) => (string) $v, $l)),
            $lignes,
        );
        $texte = implode("\n", $aplat);

        $this->assertStringContainsString('Gasoil', $texte);
        $this->assertStringContainsString('Essence', $texte);
        $this->assertStringContainsString('Prix du litre en vigueur', $texte);
    }

    public function test_l_endpoint_renvoie_un_fichier_xlsx_nomme_par_periode(): void
    {
        $reponse = $this->get('/api/exports/mensuel?annee=2026&mois=8');

        $reponse->assertOk();
        $reponse->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringContainsString(
            'carburant-covec-2026-08.xlsx',
            $reponse->headers->get('content-disposition'),
        );
    }

    public function test_une_periode_invalide_est_refusee(): void
    {
        $this->getJson('/api/exports/mensuel?annee=2026&mois=13')
            ->assertStatus(422)
            ->assertJsonValidationErrors('mois');
    }
}
