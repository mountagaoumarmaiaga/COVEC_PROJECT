import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Clock, Pencil, Plus, Trash2, X } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { toast } from 'sonner'

import {
  AlerteErreurs,
  Bouton,
  Carte,
  Cellule,
  Champ,
  Chargement,
  Chiffre,
  EnteteColonne,
  EnteteEcran,
  EtatVide,
  Pagination,
  Recherche,
  Liste,
  ListeFiltre,
  Saisie,
  Surtitre,
  Tableau,
} from '@/components/ui'
import { api, erreursParChamp, messagesErreur } from '@/lib/api'
import { useDiffere } from '@/lib/differe'
import { useDroits } from '@/lib/auth'
import {
  depuisChampHorodatage,
  formaterDate,
  formaterEntier,
  formaterHeure,
  formaterLitres,
  formaterMontant,
  MOIS,
  versChampHorodatage,
} from '@/lib/format'
import type { Carburant, Entree, Page } from '@/types'

interface Formulaire {
  carburant_id: string
  fournisseur: string
  quantite_litres: string
  prix_unitaire: string
  reference_bon: string
  /** Vide en création : le serveur pose l'instant. Rempli en correction. */
  date_entree: string
}

const FORMULAIRE_VIDE: Formulaire = {
  carburant_id: '',
  fournisseur: '',
  quantite_litres: '',
  prix_unitaire: '',
  reference_bon: '',
  date_entree: '',
}

export function Entrees() {
  const queryClient = useQueryClient()
  const { peutGerer } = useDroits()
  const [formulaire, setFormulaire] = useState<Formulaire>(FORMULAIRE_VIDE)
  const [enEdition, setEnEdition] = useState<Entree | null>(null)
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({})
  const [messageGlobal, setMessageGlobal] = useState<string[]>([])
  const formulaireRef = useRef<HTMLFormElement>(null)

  const maintenant = new Date()
  const [filtreMois, setFiltreMois] = useState('')
  const [filtreAnnee, setFiltreAnnee] = useState(String(maintenant.getFullYear()))

  const { data: carburants } = useQuery({
    queryKey: ['carburants'],
    queryFn: async () => (await api.get<{ data: Carburant[] }>('/carburants')).data.data,
  })

  const carburantChoisi = useMemo(
    () => carburants?.find((c) => String(c.id) === formulaire.carburant_id),
    [carburants, formulaire.carburant_id],
  )

  const [recherche, setRecherche] = useState('')
  const [page, setPage] = useState(1)
  const rechercheDifferee = useDiffere(recherche)

  const { data: entrees, isLoading } = useQuery({
    queryKey: ['entrees', filtreAnnee, filtreMois, rechercheDifferee, page],
    queryFn: async () =>
      (
        await api.get<Page<Entree>>('/entrees', {
          params: {
            annee: filtreMois ? filtreAnnee : undefined,
            mois: filtreMois || undefined,
            recherche: rechercheDifferee || undefined,
            page,
          },
        })
      ).data,
    // Garder la page précédente le temps du chargement évite que la liste
    // disparaisse à chaque frappe pour réapparaître aussitôt.
    placeholderData: keepPreviousData,
  })

  const meta = entrees?.meta

  // Un changement de filtre remet à la première page : rester en page 4 d'un
  // mois qui n'en compte qu'une afficherait un écran vide.
  useEffect(() => {
    setPage(1)
  }, [filtreAnnee, filtreMois])

  useEffect(() => {
    if (meta && page > meta.last_page) {
      setPage(meta.last_page)
    }
  }, [meta, page])

  const chercher = (valeur: string) => {
    setRecherche(valeur)
    setPage(1)
  }

  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['entrees'] })
    queryClient.invalidateQueries({ queryKey: ['stock'] })
  }

  const reinitialiser = () => {
    setEnEdition(null)
    setErreurs({})
    setMessageGlobal([])
    setFormulaire(FORMULAIRE_VIDE)
  }

  const editer = (entree: Entree) => {
    setEnEdition(entree)
    setErreurs({})
    setMessageGlobal([])
    setFormulaire({
      carburant_id: String(entree.carburant_id),
      fournisseur: entree.fournisseur,
      quantite_litres: String(entree.quantite_litres),
      prix_unitaire: String(entree.prix_unitaire),
      reference_bon: entree.reference_bon ?? '',
      date_entree: versChampHorodatage(entree.date_entree),
    })

    formulaireRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }

  const enregistrer = useMutation({
    mutationFn: async (valeurs: Formulaire) => {
      const corps = {
        ...(enEdition && valeurs.date_entree
          ? { date_entree: depuisChampHorodatage(valeurs.date_entree) }
          : {}),
        carburant_id: valeurs.carburant_id ? Number(valeurs.carburant_id) : null,
        fournisseur: valeurs.fournisseur,
        quantite_litres: Number(valeurs.quantite_litres),
        prix_unitaire: Number(valeurs.prix_unitaire),
        reference_bon: valeurs.reference_bon || null,
      }

      return enEdition
        ? api.put(`/entrees/${enEdition.id}`, corps)
        : api.post('/entrees', corps)
    },
    onSuccess: () => {
      const modifiait = Boolean(enEdition)

      setErreurs({})
      setMessageGlobal([])
      setEnEdition(null)
      toast.success(modifiait ? 'Livraison modifiée' : 'Livraison enregistrée')
      setFormulaire(FORMULAIRE_VIDE)
      rafraichir()
    },
    onError: (erreur) => {
      setErreurs(erreursParChamp(erreur))
      setMessageGlobal(messagesErreur(erreur))
      toast.error('Saisie refusée')
    },
  })

  const supprimer = useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/entrees/${id}`)

      return id
    },
    onSuccess: (id) => {
      toast.success('Livraison supprimée')

      if (enEdition?.id === id) reinitialiser()

      rafraichir()
    },
    onError: (erreur) => toast.error(messagesErreur(erreur)[0]),
  })

  const modifier = (champ: keyof Formulaire, valeur: string) =>
    setFormulaire((precedent) => ({ ...precedent, [champ]: valeur }))

  /**
   * Choisir un carburant pose son prix en vigueur.
   *
   * Le prix reste corrigeable : c'est celui du bon de livraison qui fait foi,
   * et un fournisseur ne facture pas forcément au tarif de la station.
   */
  const choisirCarburant = (valeur: string) => {
    const carburant = carburants?.find((c) => String(c.id) === valeur)

    setFormulaire((precedent) => ({
      ...precedent,
      carburant_id: valeur,
      prix_unitaire: carburant ? String(carburant.prix_par_defaut) : precedent.prix_unitaire,
    }))
  }

  const montantEstime =
    (Number(formulaire.quantite_litres) || 0) * (Number(formulaire.prix_unitaire) || 0)

  const totalLitres = entrees?.data.reduce((somme, e) => somme + e.quantite_litres, 0) ?? 0
  const totalMontant = entrees?.data.reduce((somme, e) => somme + e.montant, 0) ?? 0

  return (
    <div className="space-y-12">
      <EnteteEcran surtitre="Écran 1 · remplissage de la cuve" titre="Entrées">
        Le carburant choisi pose son prix du jour et sa cuve. L’heure de réception est enregistrée
        automatiquement.
      </EnteteEcran>

      {peutGerer && (
      <Carte
        titre={
          enEdition
            ? `Modifier la livraison du ${formaterDate(enEdition.date_entree)}`
            : 'Nouvelle livraison'
        }
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
            <Champ
              label="Carburant"
              obligatoire
              erreurs={erreurs.carburant_id}
              indication={
                carburantChoisi?.cuve
                  ? `${carburantChoisi.cuve.nom} · ${formaterLitres(carburantChoisi.cuve.capacite)}`
                  : undefined
              }
            >
              <Liste
                value={formulaire.carburant_id}
                onChange={choisirCarburant}
                invalide={Boolean(erreurs.carburant_id?.length)}
                options={(carburants ?? []).map((carburant) => ({
                  valeur: String(carburant.id),
                  libelle: carburant.libelle,
                  detail: `${formaterMontant(carburant.prix_par_defaut)} / L`,
                }))}
              />
            </Champ>

            <Champ label="Fournisseur" obligatoire erreurs={erreurs.fournisseur}>
              <Saisie
                required
                placeholder="Total Énergies Mali"
                value={formulaire.fournisseur}
                onChange={(e) => modifier('fournisseur', e.target.value)}
              />
            </Champ>

            <Champ
              label="N° de bon de livraison"
              erreurs={erreurs.reference_bon}
              indication="Facultatif"
            >
              <Saisie
                placeholder="BL-2026-0855"
                value={formulaire.reference_bon}
                onChange={(e) => modifier('reference_bon', e.target.value)}
              />
            </Champ>

            <Champ label="Quantité livrée (L)" obligatoire erreurs={erreurs.quantite_litres}>
              <Saisie
                type="number"
                step="0.01"
                min="0.01"
                required
                aria-invalid={Boolean(erreurs.quantite_litres?.length)}
                value={formulaire.quantite_litres}
                onChange={(e) => modifier('quantite_litres', e.target.value)}
              />
            </Champ>

            <Champ
              label="Prix du litre (FCFA)"
              obligatoire
              erreurs={erreurs.prix_unitaire}
              indication={
                carburantChoisi
                  ? `Tarif en vigueur ${formaterMontant(carburantChoisi.prix_par_defaut)} — corrigez si le bon diffère`
                  : undefined
              }
            >
              <Saisie
                type="number"
                step="0.01"
                min="0"
                required
                aria-invalid={Boolean(erreurs.prix_unitaire?.length)}
                value={formulaire.prix_unitaire}
                onChange={(e) => modifier('prix_unitaire', e.target.value)}
              />
            </Champ>

            <div className="flex flex-col justify-end">
              <Surtitre className="mb-0.5">Montant</Surtitre>
              <div className="pt-0.5 pb-2">
                <Chiffre
                  valeur={formaterEntier(montantEstime)}
                  unite="FCFA"
                  taille="grand"
                  ton="or"
                />
              </div>
            </div>
          </div>

          {enEdition ? (
            <div className="max-w-sm">
              <Champ
                label="Date et heure de réception"
                erreurs={erreurs.date_entree}
                indication="Corrigez uniquement si la livraison a été saisie en retard."
              >
                <Saisie
                  type="datetime-local"
                  value={formulaire.date_entree}
                  onChange={(e) => modifier('date_entree', e.target.value)}
                />
              </Champ>
            </div>
          ) : (
            <p className="flex items-center gap-2 text-xs text-pale">
              <Clock className="size-3.5" aria-hidden />
              La date et l’heure de réception sont enregistrées automatiquement à la validation.
            </p>
          )}

          <div className="flex justify-end">
            <Bouton type="submit" disabled={enregistrer.isPending}>
              {enEdition ? (
                <Pencil className="size-4" aria-hidden />
              ) : (
                <Plus className="size-4" aria-hidden />
              )}
              {enregistrer.isPending
                ? 'Enregistrement…'
                : enEdition
                  ? 'Enregistrer les modifications'
                  : 'Enregistrer la livraison'}
            </Bouton>
          </div>
        </form>
      </Carte>
      )}

      <Carte
        titre="Livraisons reçues"
        actions={
          <>
            <ListeFiltre
              aria-label="Filtrer par mois"
              value={filtreMois}
              onChange={setFiltreMois}
              options={[
                { valeur: '', libelle: 'Tous les mois' },
                ...MOIS.map((libelle, index) => ({ valeur: String(index + 1), libelle })),
              ]}
            />
            <ListeFiltre
              aria-label="Filtrer par année"
              value={filtreAnnee}
              onChange={setFiltreAnnee}
              options={Array.from({ length: 6 }, (_, i) => maintenant.getFullYear() - i).map(
                (a) => ({ valeur: String(a), libelle: String(a) }),
              )}
            />
          </>
        }
      >
        <Recherche
          valeur={recherche}
          onChange={chercher}
          placeholder="Chercher un fournisseur ou un bon…"
          className="mb-5 max-w-sm"
        />

        {isLoading ? (
          <Chargement />
        ) : !entrees || entrees.data.length === 0 ? (
          <EtatVide
            message={
              rechercheDifferee
                ? `Aucun résultat pour « ${rechercheDifferee} ».`
                : 'Aucune livraison pour ces critères.'
            }
          />
        ) : (
          <Tableau>
            <thead>
              <tr>
                <EnteteColonne>Date et heure</EnteteColonne>
                <EnteteColonne>Carburant</EnteteColonne>
                <EnteteColonne>Fournisseur</EnteteColonne>
                <EnteteColonne>N° de bon</EnteteColonne>
                <EnteteColonne aligne="droite">Quantité</EnteteColonne>
                <EnteteColonne aligne="droite">Prix du litre</EnteteColonne>
                <EnteteColonne aligne="droite">Montant</EnteteColonne>
                <EnteteColonne aligne="droite">
                  <span className="sr-only">Actions</span>
                </EnteteColonne>
              </tr>
            </thead>
            <tbody>
              {entrees.data.map((entree) => (
                <tr
                  key={entree.id}
                  className={
                    enEdition?.id === entree.id
                      ? 'bg-kinpaku-pale/30'
                      : 'transition-colors hover:bg-papier-profond'
                  }
                >
                  <Cellule>
                    <span className="chiffres text-attenue">
                      {formaterDate(entree.date_entree)}
                    </span>
                    <span className="chiffres block text-xs text-pale">
                      {formaterHeure(entree.date_entree)}
                    </span>
                  </Cellule>
                  <Cellule>{entree.carburant?.libelle ?? '—'}</Cellule>
                  <Cellule>{entree.fournisseur}</Cellule>
                  <Cellule className="font-mono text-xs tracking-[0.06em] text-attenue">
                    {entree.reference_bon ?? '—'}
                  </Cellule>
                  <Cellule aligne="droite">{formaterLitres(entree.quantite_litres)}</Cellule>
                  <Cellule aligne="droite" className="text-attenue">
                    {formaterMontant(entree.prix_unitaire)}
                  </Cellule>
                  <Cellule aligne="droite">
                    <span className="font-display text-[22px] font-normal">
                      {formaterEntier(entree.montant)}
                      <span className="ml-1 font-sans text-xs text-attenue">F</span>
                    </span>
                  </Cellule>
                  <Cellule aligne="droite">
                    <div className="flex justify-end gap-2">
                      {peutGerer && (<>
                      <Bouton
                        variante="icone"
                        type="button"
                        aria-label={`Modifier la livraison du ${formaterDate(entree.date_entree)}`}
                        onClick={() => editer(entree)}
                      >
                        <Pencil className="size-4" aria-hidden />
                      </Bouton>
                      <Bouton
                        variante="icone-danger"
                        type="button"
                        aria-label={`Supprimer la livraison du ${formaterDate(entree.date_entree)}`}
                        onClick={() => {
                          if (window.confirm('Supprimer cette livraison ?')) {
                            supprimer.mutate(entree.id)
                          }
                        }}
                      >
                        <Trash2 className="size-4" aria-hidden />
                      </Bouton>
                      </>)}
                    </div>
                  </Cellule>
                </tr>
              ))}

              {/*
                Le total ferme le registre. Les litres ne s'additionnent que si
                un seul carburant est affiché ; le montant, lui, se totalise
                toujours.
              */}
              <tr>
                <Cellule derniere className="surtitre text-attenue">
                  Total
                </Cellule>
                <Cellule derniere>{null}</Cellule>
                <Cellule derniere>{null}</Cellule>
                <Cellule derniere>{null}</Cellule>
                <Cellule derniere aligne="droite">
                  <span className="font-display text-[26px] font-light">
                    {formaterEntier(totalLitres)}
                    <span className="ml-1 font-sans text-xs text-attenue">L</span>
                  </span>
                </Cellule>
                <Cellule derniere>{null}</Cellule>
                <Cellule derniere aligne="droite">
                  <span className="font-display text-[26px] font-light">
                    {formaterEntier(totalMontant)}
                    <span className="ml-1 font-sans text-xs text-attenue">F</span>
                  </span>
                </Cellule>
                <Cellule derniere>{null}</Cellule>
              </tr>
            </tbody>
          </Tableau>
        )}

        {meta && <Pagination meta={meta} onPage={setPage} />}
      </Carte>
    </div>
  )
}
