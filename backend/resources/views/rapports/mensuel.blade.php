@php
    /**
     * Rapport mensuel imprimable.
     *
     * Pensé pour le papier : A4 paysage, parce que le journal des pleins porte
     * une douzaine de colonnes et qu'un tableau coupé en deux ne se lit pas.
     * Les couleurs restent lisibles en noir et blanc — c'est un filet ou un
     * aplat qui distingue une ligne, jamais la seule teinte.
     */
    $francs = fn (float|int|null $v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' F';
    $litres = fn (float|int|null $v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ');
    $nombre = fn (float|int|null $v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Suivi du carburant COVEC — {{ $periode }}</title>
    <style>
        @page { size: A4 landscape; margin: 14mm 12mm 16mm 12mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #1f2937;
            margin: 0;
        }

        /* En-tête de première page */
        .bandeau { border-bottom: 2pt solid #1F3864; padding-bottom: 6pt; margin-bottom: 12pt; }
        .bandeau .sigle { font-size: 20pt; font-weight: bold; color: #1F3864; letter-spacing: 2pt; }
        .bandeau .objet { font-size: 10pt; color: #374151; margin-top: 2pt; }
        .bandeau .edition { font-size: 7.5pt; color: #6b7280; margin-top: 3pt; }

        h2 {
            font-size: 10pt;
            color: #1F3864;
            border-bottom: 1pt solid #C7D0E3;
            padding-bottom: 3pt;
            margin: 14pt 0 6pt 0;
            text-transform: uppercase;
            letter-spacing: 0.6pt;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3.2pt 4pt; text-align: left; }
        thead th {
            background: #1F3864;
            color: #ffffff;
            font-size: 7.2pt;
            font-weight: bold;
            border: 0.4pt solid #ffffff;
        }
        tbody td { border-bottom: 0.4pt solid #dfe3ea; }
        tbody tr.paire td { background: #f5f7fb; }
        .droite { text-align: right; }
        .centre { text-align: center; }

        tfoot td {
            border-top: 1pt solid #1F3864;
            background: #E7EBF3;
            font-weight: bold;
        }

        /* Un plein signalé : aplat rose et texte sombre, lisible même imprimé
           en niveaux de gris. */
        tbody tr.signale td { background: #FFC7CE; color: #9C0006; }

        /* Bilans de cuve, côte à côte : dompdf ne connaît pas flexbox, donc un
           tableau sans bordure fait la mise en page. */
        .cuves td { vertical-align: top; width: 50%; padding: 0 6pt 0 0; border: none; }
        .cuve { border: 0.6pt solid #C7D0E3; }
        .cuve .titre {
            background: #E7EBF3;
            color: #1F3864;
            font-weight: bold;
            padding: 4pt 6pt;
            font-size: 9pt;
            letter-spacing: 0.5pt;
        }
        .cuve table td { border-bottom: 0.4pt solid #eef1f6; padding: 2.6pt 6pt; }
        .cuve .valeur { text-align: right; font-weight: bold; }

        .note { font-size: 7pt; color: #6b7280; margin-top: 5pt; font-style: italic; }
        .saut { page-break-before: always; }

        .pied {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            font-size: 7pt;
            color: #9ca3af;
            border-top: 0.4pt solid #dfe3ea;
            padding-top: 3pt;
        }
        .pied .droite { float: right; }
    </style>
</head>
<body>

<div class="pied">
    COVEC — registre du carburant · {{ $periode }}
    <span class="droite">Édité le {{ $editee_le }}</span>
</div>

<div class="bandeau">
    <div class="sigle">COVEC</div>
    <div class="objet">Suivi du carburant — {{ $periode }}</div>
    <div class="edition">Station dépôt · document édité le {{ $editee_le }}</div>
</div>

<h2>État des cuves</h2>

<table class="cuves">
    <tr>
        @foreach ($synthese['carburants'] as $bilan)
            <td>
                <div class="cuve">
                    <div class="titre">{{ mb_strtoupper($bilan['carburant']['libelle']) }}</div>
                    <table>
                        <tr><td>{{ $bilan['cuve']['nom'] }}</td>
                            <td class="valeur">{{ $nombre($bilan['cuve']['capacite']) }} L de capacité</td></tr>
                        <tr><td>Stock actuel</td>
                            <td class="valeur">{{ $litres($bilan['stock_actuel']) }} L
                                @if ($bilan['taux_remplissage'] !== null)
                                    ({{ number_format($bilan['taux_remplissage'], 1, ',', ' ') }} %)
                                @endif
                            </td></tr>
                        <tr><td>Prix du litre en vigueur</td>
                            <td class="valeur">{{ $francs($bilan['carburant']['prix_par_defaut']) }}</td></tr>
                        <tr><td>Prix moyen pondéré des achats</td>
                            <td class="valeur">{{ $francs($bilan['prix_moyen_pondere']) }}</td></tr>
                        <tr><td>Reçu depuis l'origine</td>
                            <td class="valeur">{{ $litres($bilan['total_entrees']) }} L</td></tr>
                        <tr><td>Servi depuis l'origine</td>
                            <td class="valeur">{{ $litres($bilan['total_sorties']) }} L</td></tr>
                        <tr><td>Pleins signalés</td>
                            <td class="valeur">{{ $bilan['nombre_pleins_anormaux'] }}</td></tr>
                    </table>
                </div>
            </td>
        @endforeach
    </tr>
</table>

<h2>Mouvements {{ $de_la_periode }}</h2>

<table>
    <thead>
        <tr>
            <th>Carburant</th>
            <th class="droite">Livraisons</th>
            <th class="droite">Reçu (L)</th>
            <th class="droite">Montant des achats</th>
            <th class="droite">Pleins</th>
            <th class="droite">Servi (L)</th>
            <th class="droite">Montant des pleins</th>
            <th class="droite">Signalés</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($totaux['carburants'] as $i => $ligne)
            <tr class="{{ $i % 2 ? 'paire' : '' }}">
                <td>{{ $ligne['carburant']['libelle'] }}</td>
                <td class="droite">{{ $ligne['entrees']['nombre'] }}</td>
                <td class="droite">{{ $litres($ligne['entrees']['litres']) }}</td>
                <td class="droite">{{ $francs($ligne['entrees']['montant']) }}</td>
                <td class="droite">{{ $ligne['sorties']['nombre'] }}</td>
                <td class="droite">{{ $litres($ligne['sorties']['litres']) }}</td>
                <td class="droite">{{ $francs($ligne['sorties']['montant']) }}</td>
                <td class="droite">{{ $ligne['sorties']['nombre_anomalies'] ?: '—' }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Ensemble</td>
            <td class="droite">{{ $totaux['ensemble']['entrees']['nombre'] }}</td>
            <td class="droite">—</td>
            <td class="droite">{{ $francs($totaux['ensemble']['entrees']['montant']) }}</td>
            <td class="droite">{{ $totaux['ensemble']['sorties']['nombre'] }}</td>
            <td class="droite">—</td>
            <td class="droite">{{ $francs($totaux['ensemble']['sorties']['montant']) }}</td>
            <td class="droite">{{ $totaux['ensemble']['sorties']['nombre_anomalies'] ?: '—' }}</td>
        </tr>
    </tfoot>
</table>

<p class="note">
    Les litres ne s'additionnent qu'à l'intérieur d'un carburant : la cuve de gasoil
    et la cuve d'essence sont deux réservoirs distincts. Seuls les montants se
    totalisent toutes cuves confondues.
</p>

<h2>Consommation par véhicule</h2>

<table>
    <thead>
        <tr>
            <th>Code</th>
            <th>Désignation</th>
            <th>Carburant</th>
            <th class="centre">Pleins</th>
            <th class="droite">Litres servis</th>
            <th class="droite">Montant</th>
            <th class="droite">Distance</th>
            <th class="droite">Consommation moyenne</th>
            <th class="centre">Signalés</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($par_vehicule as $i => $ligne)
            <tr class="{{ $i % 2 ? 'paire' : '' }}">
                <td>{{ $ligne['vehicule']['code'] }}</td>
                <td>{{ $ligne['vehicule']['designation'] }}</td>
                <td>{{ $ligne['vehicule']['carburant']['libelle'] ?? '—' }}</td>
                <td class="centre">{{ $ligne['nombre_pleins'] }}</td>
                <td class="droite">{{ $litres($ligne['litres_servis']) }}</td>
                <td class="droite">{{ $francs($ligne['montant']) }}</td>
                <td class="droite">
                    {{ $nombre($ligne['distance_totale']) }} {{ $ligne['vehicule']['unite_index'] }}
                </td>
                <td class="droite">
                    @if ($ligne['moyenne_consommation'] === null)
                        —
                    @else
                        {{ $litres($ligne['moyenne_consommation']) }} {{ $ligne['vehicule']['unite_consommation'] }}
                    @endif
                </td>
                <td class="centre">{{ $ligne['nombre_anomalies'] ?: '' }}</td>
            </tr>
        @empty
            <tr><td colspan="9">Aucun plein sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Activité par chauffeur</h2>

<table>
    <thead>
        <tr>
            <th>Chauffeur</th>
            <th>Matricule</th>
            <th class="centre">Pleins</th>
            <th class="droite">Litres servis</th>
            <th class="droite">Montant</th>
            <th>Véhicules conduits</th>
            <th class="centre">Signalés</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($par_chauffeur as $i => $ligne)
            <tr class="{{ $i % 2 ? 'paire' : '' }}">
                <td>{{ $ligne['chauffeur']['nom'] }}</td>
                <td>{{ $ligne['chauffeur']['matricule'] }}</td>
                <td class="centre">{{ $ligne['nombre_pleins'] }}</td>
                <td class="droite">{{ $litres($ligne['litres_servis']) }}</td>
                <td class="droite">{{ $francs($ligne['montant']) }}</td>
                <td>{{ implode(', ', $ligne['vehicules']) }}</td>
                <td class="centre">{{ $ligne['nombre_anomalies'] ?: '' }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Aucun plein sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="saut"></div>

<h2>Journal des livraisons reçues</h2>

<table>
    <thead>
        <tr>
            <th>Date et heure</th>
            <th>Carburant</th>
            <th>Fournisseur</th>
            <th>N° de bon</th>
            <th class="droite">Quantité (L)</th>
            <th class="droite">Prix du litre</th>
            <th class="droite">Montant</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($entrees as $i => $entree)
            <tr class="{{ $i % 2 ? 'paire' : '' }}">
                <td>{{ $entree->date_entree->format('d/m/Y H:i') }}</td>
                <td>{{ $entree->carburant?->libelle ?? '—' }}</td>
                <td>{{ $entree->fournisseur }}</td>
                <td>{{ $entree->reference_bon ?? '—' }}</td>
                <td class="droite">{{ $litres($entree->quantite_litres) }}</td>
                <td class="droite">{{ $francs($entree->prix_unitaire) }}</td>
                <td class="droite">{{ $francs($entree->montant) }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Aucune livraison sur la période.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">{{ $totaux_entrees['nombre'] }} livraison(s)</td>
            <td class="droite">{{ $litres($totaux_entrees['litres']) }}</td>
            <td></td>
            <td class="droite">{{ $francs($totaux_entrees['montant']) }}</td>
        </tr>
    </tfoot>
</table>

<h2>Journal des pleins servis</h2>

<table>
    <thead>
        <tr>
            <th>Date et heure</th>
            <th>Code</th>
            <th>Véhicule</th>
            <th>Chauffeur</th>
            <th class="droite">Litres</th>
            <th class="droite">Prix</th>
            <th class="droite">Montant</th>
            <th class="droite">Index</th>
            <th class="droite">Distance</th>
            <th class="droite">Consommation</th>
            <th class="droite">Écart</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($sorties as $i => $sortie)
            @php $mode = $sortie->vehicule->mode_suivi; @endphp
            <tr class="{{ $sortie->anomalie ? 'signale' : ($i % 2 ? 'paire' : '') }}">
                <td>{{ $sortie->date_sortie->format('d/m/Y H:i') }}</td>
                <td>{{ $sortie->vehicule->code }}</td>
                <td>{{ $sortie->vehicule->designation }}</td>
                <td>{{ $sortie->chauffeur->nom }}</td>
                <td class="droite">{{ $litres($sortie->litres_servis) }}</td>
                <td class="droite">{{ $francs($sortie->prix_unitaire) }}</td>
                <td class="droite">{{ $francs($sortie->montant) }}</td>
                <td class="droite">{{ $nombre($sortie->index_compteur) }} {{ $mode->uniteIndex() }}</td>
                <td class="droite">{{ $nombre($sortie->distance_parcourue) }}</td>
                <td class="droite">
                    @if ($sortie->consommation === null)
                        —
                    @else
                        {{ $litres($sortie->consommation) }} {{ $mode->uniteConsommation() }}
                    @endif
                </td>
                <td class="droite">
                    @if ($sortie->ecart_pourcentage === null)
                        —
                    @else
                        {{ ($sortie->ecart_pourcentage > 0 ? '+' : '').number_format($sortie->ecart_pourcentage, 1, ',', ' ') }} %
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="11">Aucun plein sur la période.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">{{ $totaux_sorties['nombre'] }} plein(s), dont {{ $totaux_sorties['anomalies'] }} signalé(s)</td>
            <td class="droite">{{ $litres($totaux_sorties['litres']) }}</td>
            <td></td>
            <td class="droite">{{ $francs($totaux_sorties['montant']) }}</td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>

<p class="note">
    Une ligne sur fond rose signale un plein dont la consommation s'écarte de plus
    de 30 % de la moyenne du véhicule. Le signalement n'est pas une erreur : il
    demande une vérification.
</p>

</body>
</html>
