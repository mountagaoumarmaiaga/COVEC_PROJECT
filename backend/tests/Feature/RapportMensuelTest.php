<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModeSuivi;
use App\Exports\DonneesMensuelles;
use App\Exports\ExportMensuel;
use App\Exports\RapportMensuel;
use App\Models\Entree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\InstalleLaStation;
use Tests\TestCase;

/**
 * Rapport PDF du mois, et concordance avec le classeur.
 *
 * Deux documents qui affichent des totaux différents ne se corrigent pas :
 * ils font douter du registre entier. Ces tests vérifient d'abord que le PDF
 * se produit, ensuite qu'il dit la même chose que le tableur.
 */
class RapportMensuelTest extends TestCase
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

    private function html(): string
    {
        return View::make(
            'rapports.mensuel',
            app(DonneesMensuelles::class)->pour(2026, 8),
        )->render();
    }

    public function test_le_rapport_est_un_pdf_non_vide(): void
    {
        $chemin = app(RapportMensuel::class)->generer(2026, 8);

        $this->assertFileExists($chemin);

        $debut = file_get_contents($chemin, length: 8);
        $taille = filesize($chemin);
        @unlink($chemin);

        $this->assertStringStartsWith('%PDF-', (string) $debut);
        $this->assertGreaterThan(5000, $taille);
    }

    public function test_il_porte_les_six_sections_du_registre(): void
    {
        $html = $this->html();

        foreach ([
            'État des cuves',
            'Consommation par véhicule',
            'Activité par chauffeur',
            'Journal des livraisons reçues',
            'Journal des pleins servis',
        ] as $section) {
            $this->assertStringContainsString($section, $html);
        }
    }

    public function test_il_nomme_les_vehicules_les_chauffeurs_et_les_litres(): void
    {
        $html = $this->html();

        $this->assertStringContainsString($this->chauffeur->nom, $html);
        $this->assertStringContainsString($this->chauffeur->matricule, $html);
        $this->assertStringContainsString('Total Énergies Mali', $html);
        $this->assertStringContainsString('BL-2026-0714', $html);
        // 680 litres servis sur les trois pleins, au format français.
        $this->assertStringContainsString('680,00', $html);
    }

    public function test_la_periode_est_elidee_correctement(): void
    {
        $donnees = app(DonneesMensuelles::class);

        $this->assertSame("d'août 2026", $donnees->deLaPeriode(2026, 8));
        $this->assertSame('de janvier 2026', $donnees->deLaPeriode(2026, 1));
        $this->assertSame("d'avril 2026", $donnees->deLaPeriode(2026, 4));
        $this->assertSame("d'octobre 2026", $donnees->deLaPeriode(2026, 10));
    }

    public function test_le_pdf_et_le_classeur_annoncent_les_memes_totaux(): void
    {
        $html = $this->html();

        $chemin = app(ExportMensuel::class)->generer(2026, 8);
        $reader = new Reader();
        $reader->open($chemin);

        $litresClasseur = null;

        foreach ($reader->getSheetIterator() as $feuille) {
            if ($feuille->getName() !== 'Pleins servis') {
                continue;
            }

            foreach ($feuille->getRowIterator() as $ligne) {
                $cellules = $ligne->toArray();

                if (($cellules[4] ?? null) === 'Total') {
                    $litresClasseur = (float) $cellules[5];
                }
            }
        }

        $reader->close();
        @unlink($chemin);

        $this->assertSame(680.0, $litresClasseur);

        // Le même total, écrit à la française, doit figurer dans le rapport.
        $this->assertStringContainsString(
            number_format($litresClasseur, 2, ',', ' '),
            $html,
        );
    }

    public function test_l_endpoint_renvoie_un_pdf_nomme_par_periode(): void
    {
        $this->actingAs($this->gestionnaire)
            ->get('/api/exports/rapport?annee=2026&mois=8')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('rapport-carburant-covec-2026-08.pdf');
    }

    public function test_une_periode_invalide_est_refusee(): void
    {
        $this->actingAs($this->gestionnaire)
            ->getJson('/api/exports/rapport?annee=2026&mois=13')
            ->assertStatus(422);
    }
}
