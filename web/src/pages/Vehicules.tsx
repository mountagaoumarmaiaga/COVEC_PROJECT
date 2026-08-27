import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Trash2, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'

import {
  AlerteErreurs,
  Badge,
  Bouton,
  Carte,
  Cellule,
  Champ,
  Chargement,
  EnteteColonne,
  EnteteEcran,
  EtatVide,
  Pagination,
  Recherche,
  Liste,
  Saisie,
  Tableau,
} from '@/components/ui'
import { api, erreursParChamp, messagesErreur } from '@/lib/api'
import { useDiffere } from '@/lib/differe'
import { formaterLitres, formaterMontant } from '@/lib/format'
import type { Carburant, ModeSuivi, Vehicule, Page } from '@/types'

interface Formulaire {
  code: string
  designation: string
  carburant_id: string
  mode_suivi: ModeSuivi
  capacite_reservoir: string
  actif: boolean
}

const FORMULAIRE_VIDE: Formulaire = {
  code: '',
  designation: '',
  carburant_id: '',
  mode_suivi: 'km',
  capacite_reservoir: '',
  actif: true,
}

export function Vehicules() {
  const queryClient = useQueryClient()
  const [formulaire, setFormulaire] = useState<Formulaire>(FORMULAIRE_VIDE)
  const [enEdition, setEnEdition] = useState<Vehicule | null>(null)
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({})
  const [messageGlobal, setMessageGlobal] = useState<string[]>([])
  const formulaireRef = useRef<HTMLFormElement>(null)

  const { data: carburants } = useQuery({
    queryKey: ['carburants'],
    queryFn: async () => (await api.get<{ data: Carburant[] }>('/carburants')).data.data,
  })

  const [recherche, setRecherche] = useState('')
  const [page, setPage] = useState(1)
  const rechercheDifferee = useDiffere(recherche)

  const { data: resultat, isLoading } = useQuery({
    queryKey: ['vehicules', rechercheDifferee, page],
    queryFn: async () =>
      (
        await api.get<Page<Vehicule>>('/vehicules', {
          params: { recherche: rechercheDifferee || undefined, page },
        })
      ).data,
    // Garder la page précédente le temps du chargement évite que la liste
    // disparaisse à chaque frappe pour réapparaître aussitôt.
    placeholderData: keepPreviousData,
  })

  const vehicules = resultat?.data
  const meta = resultat?.meta

  // Supprimer la dernière ligne d'une page laisserait l'écran sur une page qui
  // n'existe plus, et donc vide.
  useEffect(() => {
    if (meta && page > meta.last_page) {
      setPage(meta.last_page)
    }
  }, [meta, page])

  const chercher = (valeur: string) => {
    setRecherche(valeur)
    setPage(1)
  }

  const reinitialiser = () => {
    setFormulaire(FORMULAIRE_VIDE)
    setEnEdition(null)
    setErreurs({})
    setMessageGlobal([])
  }

  const enregistrer = useMutation({
    mutationFn: async (valeurs: Formulaire) => {
      const corps = {
        code: valeurs.code,
        designation: valeurs.designation,
        carburant_id: valeurs.carburant_id ? Number(valeurs.carburant_id) : null,
        mode_suivi: valeurs.mode_suivi,
        capacite_reservoir: Number(valeurs.capacite_reservoir),
        actif: valeurs.actif,
      }

      return enEdition
        ? api.put(`/vehicules/${enEdition.id}`, corps)
        : api.post('/vehicules', corps)
    },
    onSuccess: () => {
      toast.success(enEdition ? 'Véhicule mis à jour' : 'Véhicule ajouté')
      reinitialiser()
      queryClient.invalidateQueries({ queryKey: ['vehicules'] })
      queryClient.invalidateQueries({ queryKey: ['stock'] })
    },
    onError: (erreur) => {
      setErreurs(erreursParChamp(erreur))
      setMessageGlobal(messagesErreur(erreur))
      toast.error('Enregistrement refusé')
    },
  })

  const supprimer = useMutation({
    mutationFn: async (id: number) => api.delete(`/vehicules/${id}`),
    onSuccess: () => {
      toast.success('Véhicule supprimé')
      queryClient.invalidateQueries({ queryKey: ['vehicules'] })
    },
    // Un véhicule qui a servi ne se supprime pas : l'API répond 409 avec le
    // message à afficher tel quel.
    onError: (erreur) => toast.error(messagesErreur(erreur)[0]),
  })

  const editer = (vehicule: Vehicule) => {
    setEnEdition(vehicule)
    setErreurs({})
    setMessageGlobal([])
    setFormulaire({
      code: vehicule.code,
      designation: vehicule.designation,
      carburant_id: String(vehicule.carburant_id),
      mode_suivi: vehicule.mode_suivi,
      capacite_reservoir: String(vehicule.capacite_reservoir),
      actif: vehicule.actif,
    })

    // Le formulaire est en haut de page : sans ce défilement, le clic sur le
    // crayon d'une ligne basse ne semble rien faire.
    formulaireRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }

  const modifier = <C extends keyof Formulaire>(champ: C, valeur: Formulaire[C]) =>
    setFormulaire((precedent) => ({ ...precedent, [champ]: valeur }))

  return (
    <div className="space-y-12">
      <EnteteEcran surtitre="Référentiel · saisi une seule fois" titre="Véhicules et engins">
        Code interne, désignation, mode de suivi et capacité du réservoir. Le mode de suivi
        détermine l’unité de consommation : L/100 km pour ce qui roule, L/h pour ce qui tourne.
      </EnteteEcran>

      <Carte
        titre={enEdition ? `Modifier ${enEdition.code}` : 'Nouveau véhicule'}
        levee
        actions={
          enEdition && (
            <Bouton variante="secondaire" type="button" onClick={reinitialiser}>
              <X className="size-4" aria-hidden />
              Annuler
            </Bouton>
          )
        }
      >
        <form
          ref={formulaireRef}
          className="space-y-7"
          onSubmit={(e) => {
            e.preventDefault()
            enregistrer.mutate(formulaire)
          }}
        >
          <AlerteErreurs messages={messageGlobal} />

          <div className="grid gap-x-9 gap-y-7 sm:grid-cols-2 lg:grid-cols-3">
            <Champ label="Code interne" obligatoire erreurs={erreurs.code}>
              <Saisie
                required
                placeholder="VL-001"
                value={formulaire.code}
                onChange={(e) => modifier('code', e.target.value)}
              />
            </Champ>

            <Champ label="Désignation" obligatoire erreurs={erreurs.designation}>
              <Saisie
                required
                placeholder="Toyota Hilux double cabine"
                value={formulaire.designation}
                onChange={(e) => modifier('designation', e.target.value)}
              />
            </Champ>

            <Champ
              label="Carburant"
              obligatoire
              erreurs={erreurs.carburant_id}
              indication="Décide de la cuve où ses pleins sont décomptés, et du prix appliqué"
            >
              <Liste
                value={formulaire.carburant_id}
                onChange={(valeur) => modifier('carburant_id', valeur)}
                invalide={Boolean(erreurs.carburant_id?.length)}
                options={(carburants ?? []).map((carburant) => ({
                  valeur: String(carburant.id),
                  libelle: carburant.libelle,
                  detail: `${formaterMontant(carburant.prix_par_defaut)} / L`,
                }))}
              />
            </Champ>

            <Champ
              label="Mode de suivi"
              obligatoire
              erreurs={erreurs.mode_suivi}
              indication={
                formulaire.mode_suivi === 'km' ? 'Consommation en L/100 km' : 'Consommation en L/h'
              }
            >
              <Liste
                value={formulaire.mode_suivi}
                onChange={(valeur) => modifier('mode_suivi', valeur as ModeSuivi)}
                invalide={Boolean(erreurs.mode_suivi?.length)}
                options={[
                  { valeur: 'km', libelle: 'Kilométrage', detail: 'Consommation en L/100 km' },
                  { valeur: 'heures', libelle: 'Heures moteur', detail: 'Consommation en L/h' },
                ]}
              />
            </Champ>

            <Champ
              label="Capacité du réservoir (L)"
              obligatoire
              erreurs={erreurs.capacite_reservoir}
              indication="Plafond des litres servis en un plein"
            >
              <Saisie
                type="number"
                step="0.01"
                min="0.01"
                required
                aria-invalid={Boolean(erreurs.capacite_reservoir?.length)}
                value={formulaire.capacite_reservoir}
                onChange={(e) => modifier('capacite_reservoir', e.target.value)}
              />
            </Champ>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-4">
            <label className="flex items-center gap-2.5 text-sm text-encre">
              <input
                type="checkbox"
                checked={formulaire.actif}
                onChange={(e) => modifier('actif', e.target.checked)}
                className="size-4 rounded-none border-arete accent-[oklch(25%_0.018_95)]"
              />
              Actif — proposé à la saisie des sorties
            </label>

            <Bouton type="submit" disabled={enregistrer.isPending}>
              {enEdition ? (
                <Pencil className="size-4" aria-hidden />
              ) : (
                <Plus className="size-4" aria-hidden />
              )}
              {enEdition ? 'Enregistrer les modifications' : 'Ajouter le véhicule'}
            </Bouton>
          </div>
        </form>
      </Carte>

      <Carte titre="Parc">
        <Recherche
          valeur={recherche}
          onChange={chercher}
          placeholder="Chercher un code ou une désignation…"
          className="mb-5 max-w-sm"
        />

        {isLoading ? (
          <Chargement />
        ) : !vehicules || vehicules.length === 0 ? (
          <EtatVide
            message={
              rechercheDifferee
                ? `Aucun résultat pour « ${rechercheDifferee} ».`
                : 'Aucun véhicule enregistré.'
            }
          />
        ) : (
          <Tableau>
            <thead>
              <tr>
                <EnteteColonne>Code</EnteteColonne>
                <EnteteColonne>Désignation</EnteteColonne>
                <EnteteColonne>Carburant</EnteteColonne>
                <EnteteColonne>Mode de suivi</EnteteColonne>
                <EnteteColonne aligne="droite">Réservoir</EnteteColonne>
                <EnteteColonne>État</EnteteColonne>
                <EnteteColonne aligne="droite">
                  <span className="sr-only">Actions</span>
                </EnteteColonne>
              </tr>
            </thead>
            <tbody>
              {vehicules.map((vehicule, index) => {
                const derniere = index === vehicules.length - 1

                return (
                  <tr
                    key={vehicule.id}
                    className={
                      enEdition?.id === vehicule.id
                        ? 'bg-kinpaku-pale/30'
                        : 'transition-colors hover:bg-papier-profond'
                    }
                  >
                    <Cellule derniere={derniere} className="font-mono text-xs tracking-[0.06em]">
                      {vehicule.code}
                    </Cellule>
                    <Cellule derniere={derniere}>{vehicule.designation}</Cellule>
                    <Cellule derniere={derniere}>
                      {vehicule.carburant?.libelle ?? '—'}
                      <span className="block text-xs text-pale">
                        {formaterMontant(vehicule.carburant?.prix_par_defaut)} / L
                      </span>
                    </Cellule>
                    <Cellule derniere={derniere}>
                      {vehicule.mode_suivi_libelle}
                      <span className="block text-xs text-pale">
                        {vehicule.unite_consommation}
                      </span>
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      {formaterLitres(vehicule.capacite_reservoir)}
                    </Cellule>
                    <Cellule derniere={derniere}>
                      {vehicule.actif ? (
                        <Badge ton="or">Actif</Badge>
                      ) : (
                        <Badge>Retiré du parc</Badge>
                      )}
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      <div className="flex justify-end gap-2">
                        <Bouton
                          variante="icone"
                          type="button"
                          aria-label={`Modifier ${vehicule.code}`}
                          onClick={() => editer(vehicule)}
                        >
                          <Pencil className="size-4" aria-hidden />
                        </Bouton>
                        <Bouton
                          variante="icone-danger"
                          type="button"
                          aria-label={`Supprimer ${vehicule.code}`}
                          onClick={() => {
                            if (window.confirm(`Supprimer ${vehicule.code} ?`)) {
                              supprimer.mutate(vehicule.id)
                            }
                          }}
                        >
                          <Trash2 className="size-4" aria-hidden />
                        </Bouton>
                      </div>
                    </Cellule>
                  </tr>
                )
              })}
            </tbody>
          </Tableau>
        )}

        {meta && <Pagination meta={meta} onPage={setPage} />}
      </Carte>
    </div>
  )
}
