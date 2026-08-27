import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, FileSpreadsheet, FileText } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

import {
  Badge,
  Carte,
  Cellule,
  Chargement,
  Chiffre,
  EnteteColonne,
  EnteteEcran,
  EtatVide,
  Guidage,
  LienAction,
  ListeFiltre,
  Surtitre,
  Tableau,
} from '@/components/ui'
import { api } from '@/lib/api'
import {
  duMois,
  formaterEntier,
  formaterLitres,
  formaterMontant,
  formaterNombre,
  MOIS,
  nomMois,
} from '@/lib/format'
import type { BilanCarburant, EcranStock, LigneConsommation, TotauxCarburant } from '@/types'

/** Niveau d'une cuve : un filet qui se remplit, pas une barre arrondie. */
function Jauge({ taux }: { taux: number | null }) {
  if (taux === null) {
    return (
      <p className="text-sm text-attenue">
        Renseignez la capacité de la cuve pour afficher le niveau.
      </p>
    )
  }

  const largeur = Math.min(Math.max(taux, 0), 100)
  // Sous 15 % la station risque la rupture, sous 30 % il faut commander.
  const couleur = taux < 15 ? 'bg-vermillon' : taux < 30 ? 'bg-kinpaku' : 'bg-patine'

  return (
    <div>
      <div className="h-1.5 w-full bg-[oklch(25%_0.02_95_/_0.08)]">
        <div className={`h-full ${couleur}`} style={{ width: `${largeur}%` }} />
      </div>
      <p className="chiffres mt-2.5 text-xs text-attenue">
        {formaterNombre(taux)} % de la capacité
      </p>
    </div>
  )
}

/**
 * Bilan d'une cuve.
 *
 * Chaque carburant a son bloc : ses litres ne s'additionnent à ceux d'aucun
 * autre, puisqu'ils ne sont pas dans le même réservoir.
 */
function BlocCuve({ bilan }: { bilan: BilanCarburant }) {
  const secondaires = [
    ['Capacité', formaterLitres(bilan.cuve.capacite)],
    ['Entrées cumulées', formaterLitres(bilan.total_entrees)],
    ['Sorties cumulées', formaterLitres(bilan.total_sorties)],
    ['Prix en vigueur', `${formaterMontant(bilan.carburant.prix_par_defaut)} / L`],
    ['Prix moyen des achats', `${formaterMontant(bilan.prix_moyen_pondere)} / L`],
  ] as const

  return (
    <div className="border-t border-arete pt-6">
      <div className="flex items-baseline justify-between gap-4">
        <Surtitre ton="or">{bilan.carburant.libelle}</Surtitre>
        {bilan.nombre_pleins_anormaux > 0 && (
          <Badge ton="signale">
            <AlertTriangle className="size-3" aria-hidden />
            {bilan.nombre_pleins_anormaux} signalé
            {bilan.nombre_pleins_anormaux > 1 ? 's' : ''}
          </Badge>
        )}
      </div>

      <div className="mt-2">
        <Chiffre valeur={formaterNombre(bilan.stock_actuel)} unite="L" taille="duo" ton="or" />
      </div>

      <div className="mt-6">
        <Jauge taux={bilan.taux_remplissage} />
      </div>

      <p className="mt-3 text-xs text-pale">{bilan.cuve.nom}</p>

      <dl className="mt-5">
        {secondaires.map(([libelle, valeur], index) => (
          <div
            key={libelle}
            className={`flex items-baseline justify-between gap-4 py-2 ${
              index === secondaires.length - 1 ? '' : 'border-b border-filet'
            }`}
          >
            <dt className="text-[13px] text-attenue">{libelle}</dt>
            <dd className="chiffres text-[13px]">{valeur}</dd>
          </div>
        ))}
      </dl>
    </div>
  )
}

function LigneTotaux({ totaux }: { totaux: TotauxCarburant }) {
  return (
    <tr className="transition-colors hover:bg-papier-profond">
      <Cellule className="font-medium">{totaux.carburant.libelle}</Cellule>
      <Cellule aligne="droite" className="text-attenue">
        {totaux.entrees.nombre}
      </Cellule>
      <Cellule aligne="droite">{formaterLitres(totaux.entrees.litres)}</Cellule>
      <Cellule aligne="droite">{formaterMontant(totaux.entrees.montant)}</Cellule>
      <Cellule aligne="droite" className="text-attenue">
        {totaux.sorties.nombre}
      </Cellule>
      <Cellule aligne="droite">{formaterLitres(totaux.sorties.litres)}</Cellule>
      <Cellule aligne="droite">{formaterMontant(totaux.sorties.montant)}</Cellule>
    </tr>
  )
}

function TableauConsommation({ lignes }: { lignes: LigneConsommation[] }) {
  if (lignes.length === 0) {
    return <EtatVide message="Aucun plein sur la période sélectionnée." />
  }

  return (
    <Tableau>
      <thead>
        <tr>
          <EnteteColonne>Code</EnteteColonne>
          <EnteteColonne>Désignation</EnteteColonne>
          <EnteteColonne>Carburant</EnteteColonne>
          <EnteteColonne aligne="droite">Pleins</EnteteColonne>
          <EnteteColonne aligne="droite">Litres</EnteteColonne>
          <EnteteColonne aligne="droite">Montant</EnteteColonne>
          <EnteteColonne aligne="droite">Distance</EnteteColonne>
          <EnteteColonne aligne="droite">Moyenne</EnteteColonne>
          <EnteteColonne aligne="droite">Signalés</EnteteColonne>
        </tr>
      </thead>
      <tbody>
        {lignes.map(({ vehicule, ...ligne }, index) => {
          const derniere = index === lignes.length - 1

          return (
            <tr key={vehicule.id} className="transition-colors hover:bg-papier-profond">
              <Cellule derniere={derniere} className="font-mono text-xs tracking-[0.06em]">
                {vehicule.code}
              </Cellule>
              <Cellule derniere={derniere}>{vehicule.designation}</Cellule>
              <Cellule derniere={derniere} className="text-attenue">
                {vehicule.carburant?.libelle ?? '—'}
              </Cellule>
              <Cellule derniere={derniere} aligne="droite" className="text-attenue">
                {ligne.nombre_pleins}
              </Cellule>
              <Cellule derniere={derniere} aligne="droite">
                {formaterLitres(ligne.litres_servis)}
              </Cellule>
              <Cellule derniere={derniere} aligne="droite" className="text-attenue">
                {formaterMontant(ligne.montant)}
              </Cellule>
              <Cellule derniere={derniere} aligne="droite" className="text-attenue">
                {ligne.distance_totale > 0
                  ? `${formaterNombre(ligne.distance_totale)} ${vehicule.unite_index}`
                  : '—'}
              </Cellule>
              <Cellule derniere={derniere} aligne="droite">
                {ligne.moyenne_consommation === null ? (
                  <span className="text-pale">—</span>
                ) : (
                  <span className="font-display text-[21px] font-normal">
                    {formaterNombre(ligne.moyenne_consommation)}
                    <span className="ml-1 font-sans text-xs text-attenue">
                      {vehicule.unite_consommation}
                    </span>
                  </span>
                )}
              </Cellule>
              <Cellule derniere={derniere} aligne="droite">
                {ligne.nombre_anomalies > 0 ? (
                  <Badge ton="signale">
                    <AlertTriangle className="size-3" aria-hidden />
                    {ligne.nombre_anomalies}
                  </Badge>
                ) : (
                  <span className="text-pale">—</span>
                )}
              </Cellule>
            </tr>
          )
        })}
      </tbody>
    </Tableau>
  )
}

/*
  Les deux liens d'export partagent leur allure. La hauteur suit celle des
  boutons secondaires — quarante-quatre pixels — pour qu'un lien d'export ne
  soit pas plus difficile à viser qu'un bouton.
*/
const LIEN_EXPORT =
  'inline-flex h-11 items-center gap-2 rounded-net border border-arete bg-leve px-5 text-sm font-medium text-encre transition-colors hover:border-encre hover:bg-papier-profond'

export function StockConsommation() {
  const maintenant = new Date()
  const [annee, setAnnee] = useState(maintenant.getFullYear())
  const [mois, setMois] = useState(maintenant.getMonth() + 1)
  const [portee, setPortee] = useState<'mois' | 'cumul'>('mois')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['stock', annee, mois],
    queryFn: async () =>
      (await api.get<{ data: EcranStock }>('/stock', { params: { annee, mois } })).data.data,
  })

  const annees = Array.from({ length: 6 }, (_, i) => maintenant.getFullYear() - i)

  const aucunMouvement =
    data?.synthese.carburants.every((b) => b.total_entrees === 0 && b.total_sorties === 0) ?? false

  return (
    <div className="space-y-12">
      <EnteteEcran
        surtitre={`Station dépôt · ${nomMois(mois)} ${annee}`}
        titre="Stock et consommation"
      >
        Une cuve par carburant, un stock par cuve. Le niveau n’est jamais stocké : il se recalcule
        à chaque mouvement, entrées moins sorties.
      </EnteteEcran>

      {isError && (
        <EtatVide message="Impossible de joindre l’API. Démarrez le backend avec « php artisan serve »." />
      )}

      {isLoading && <Chargement />}

      {data && aucunMouvement && (
        <Guidage
          titre="Aucun mouvement enregistré"
          action={
            <div className="flex flex-wrap gap-4 text-[13px]">
              <Link to="/carburants" className="text-patine-profonde hover:text-patine">
                Renseigner les cuves
              </Link>
              <Link to="/vehicules" className="text-patine-profonde hover:text-patine">
                Déclarer le parc
              </Link>
              <Link to="/entrees" className="text-patine-profonde hover:text-patine">
                Saisir une livraison
              </Link>
            </div>
          }
        >
          La station démarre. Renseignez la capacité des cuves, déclarez les véhicules et les
          chauffeurs, puis enregistrez la première livraison : le stock se calculera tout seul à
          partir de là.
        </Guidage>
      )}

      {data && (
        <>
          <div className="grid gap-x-12 gap-y-10 lg:grid-cols-2">
            {data.synthese.carburants.map((bilan) => (
              <BlocCuve key={bilan.carburant.id} bilan={bilan} />
            ))}
          </div>

          <Carte
            titre={`Totaux ${duMois(mois)} ${annee}`}
            description="Achats facturés d’un côté, carburant distribué de l’autre. Seuls les montants se totalisent : des litres de gasoil et d’essence ne sont pas dans le même réservoir."
            actions={
              <>
                <ListeFiltre
                  aria-label="Mois"
                  value={String(mois)}
                  onChange={(valeur) => setMois(Number(valeur))}
                  options={MOIS.map((libelle, index) => ({
                    valeur: String(index + 1),
                    libelle,
                  }))}
                />
                <ListeFiltre
                  aria-label="Année"
                  value={String(annee)}
                  onChange={(valeur) => setAnnee(Number(valeur))}
                  options={annees.map((a) => ({ valeur: String(a), libelle: String(a) }))}
                />
                <a
                  href={`/api/exports/mensuel?annee=${annee}&mois=${mois}`}
                  className={LIEN_EXPORT}
                >
                  <FileSpreadsheet className="size-4" aria-hidden />
                  Classeur Excel
                </a>
                <a
                  href={`/api/exports/rapport?annee=${annee}&mois=${mois}`}
                  className={LIEN_EXPORT}
                >
                  <FileText className="size-4" aria-hidden />
                  Rapport PDF
                </a>
              </>
            }
          >
            <Tableau>
              <thead>
                <tr>
                  <EnteteColonne>Carburant</EnteteColonne>
                  <EnteteColonne aligne="droite">Livraisons</EnteteColonne>
                  <EnteteColonne aligne="droite">Litres reçus</EnteteColonne>
                  <EnteteColonne aligne="droite">Montant achats</EnteteColonne>
                  <EnteteColonne aligne="droite">Pleins</EnteteColonne>
                  <EnteteColonne aligne="droite">Litres servis</EnteteColonne>
                  <EnteteColonne aligne="droite">Montant pleins</EnteteColonne>
                </tr>
              </thead>
              <tbody>
                {data.totaux_mois.carburants.map((totaux) => (
                  <LigneTotaux key={totaux.carburant.id} totaux={totaux} />
                ))}

                <tr>
                  <Cellule derniere className="surtitre text-attenue">
                    Ensemble
                  </Cellule>
                  <Cellule derniere aligne="droite" className="text-attenue">
                    {data.totaux_mois.ensemble.entrees.nombre}
                  </Cellule>
                  <Cellule derniere aligne="droite" className="text-pale">
                    —
                  </Cellule>
                  <Cellule derniere aligne="droite">
                    <span className="font-display text-[24px] font-light">
                      {formaterEntier(data.totaux_mois.ensemble.entrees.montant)}
                      <span className="ml-1 font-sans text-xs text-attenue">FCFA</span>
                    </span>
                  </Cellule>
                  <Cellule derniere aligne="droite" className="text-attenue">
                    {data.totaux_mois.ensemble.sorties.nombre}
                  </Cellule>
                  <Cellule derniere aligne="droite" className="text-pale">
                    —
                  </Cellule>
                  <Cellule derniere aligne="droite">
                    <span className="font-display text-[24px] font-light">
                      {formaterEntier(data.totaux_mois.ensemble.sorties.montant)}
                      <span className="ml-1 font-sans text-xs text-attenue">FCFA</span>
                    </span>
                  </Cellule>
                </tr>
              </tbody>
            </Tableau>
          </Carte>

          <Carte
            titre="Consommation par véhicule"
            description="L/100 km pour les véhicules et camions, L/h pour les engins et groupes électrogènes."
            actions={
              <>
                <LienAction actif={portee === 'mois'} onClick={() => setPortee('mois')}>
                  {nomMois(mois)} {annee}
                </LienAction>
                <LienAction actif={portee === 'cumul'} onClick={() => setPortee('cumul')}>
                  Depuis l’origine
                </LienAction>
              </>
            }
          >
            <TableauConsommation
              lignes={
                portee === 'mois' ? data.consommation_par_vehicule : data.consommation_cumulee
              }
            />
            <p className="mt-7 border-t border-filet pt-4 text-xs text-pale">
              Le premier plein d’un véhicule ne produit pas de consommation : il sert de repère au
              compteur.
            </p>
          </Carte>
        </>
      )}
    </div>
  )
}
