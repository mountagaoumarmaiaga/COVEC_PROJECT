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
  Saisie,
  Tableau,
} from '@/components/ui'
import { api, erreursParChamp, messagesErreur } from '@/lib/api'
import { useDiffere } from '@/lib/differe'
import type { Chauffeur, Page } from '@/types'

interface Formulaire {
  nom: string
  matricule: string
  actif: boolean
}

const FORMULAIRE_VIDE: Formulaire = { nom: '', matricule: '', actif: true }

export function Chauffeurs() {
  const queryClient = useQueryClient()
  const [formulaire, setFormulaire] = useState<Formulaire>(FORMULAIRE_VIDE)
  const [enEdition, setEnEdition] = useState<Chauffeur | null>(null)
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({})
  const [messageGlobal, setMessageGlobal] = useState<string[]>([])
  const formulaireRef = useRef<HTMLFormElement>(null)
  const [recherche, setRecherche] = useState('')
  const [page, setPage] = useState(1)
  const rechercheDifferee = useDiffere(recherche)

  const { data: resultat, isLoading } = useQuery({
    queryKey: ['chauffeurs', rechercheDifferee, page],
    queryFn: async () =>
      (
        await api.get<Page<Chauffeur>>('/chauffeurs', {
          params: { recherche: rechercheDifferee || undefined, page },
        })
      ).data,
    // Garder la page précédente le temps du chargement évite que la liste
    // disparaisse à chaque frappe pour réapparaître aussitôt.
    placeholderData: keepPreviousData,
  })

  const chauffeurs = resultat?.data
  const meta = resultat?.meta

  // Supprimer le dernier chauffeur d'une page laisserait l'écran sur une page
  // qui n'existe plus, et donc vide.
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
    mutationFn: async (valeurs: Formulaire) =>
      enEdition
        ? api.put(`/chauffeurs/${enEdition.id}`, valeurs)
        : api.post('/chauffeurs', valeurs),
    onSuccess: () => {
      toast.success(enEdition ? 'Chauffeur mis à jour' : 'Chauffeur ajouté')
      reinitialiser()
      queryClient.invalidateQueries({ queryKey: ['chauffeurs'] })
    },
    onError: (erreur) => {
      setErreurs(erreursParChamp(erreur))
      setMessageGlobal(messagesErreur(erreur))
      toast.error('Enregistrement refusé')
    },
  })

  const supprimer = useMutation({
    mutationFn: async (id: number) => api.delete(`/chauffeurs/${id}`),
    onSuccess: () => {
      toast.success('Chauffeur supprimé')
      queryClient.invalidateQueries({ queryKey: ['chauffeurs'] })
    },
    onError: (erreur) => toast.error(messagesErreur(erreur)[0]),
  })

  const editer = (chauffeur: Chauffeur) => {
    setEnEdition(chauffeur)
    setErreurs({})
    setMessageGlobal([])
    setFormulaire({
      nom: chauffeur.nom,
      matricule: chauffeur.matricule,
      actif: chauffeur.actif,
    })

    // Même raison que pour les véhicules : le formulaire est hors écran
    // dès que l'effectif dépasse quelques lignes.
    formulaireRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }

  const modifier = <C extends keyof Formulaire>(champ: C, valeur: Formulaire[C]) =>
    setFormulaire((precedent) => ({ ...precedent, [champ]: valeur }))

  return (
    <div className="space-y-12">
      <EnteteEcran surtitre="Référentiel · saisi une seule fois" titre="Chauffeurs">
        Nom et matricule des agents habilités à se servir à la cuve. Un chauffeur qui a servi des
        pleins se désactive, il ne s’efface pas.
      </EnteteEcran>

      <Carte
        titre={enEdition ? `Modifier ${enEdition.nom}` : 'Nouveau chauffeur'}
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

          <div className="grid max-w-2xl gap-x-9 gap-y-7 sm:grid-cols-2">
            <Champ label="Nom" obligatoire erreurs={erreurs.nom}>
              <Saisie
                required
                placeholder="Amadou Traoré"
                value={formulaire.nom}
                onChange={(e) => modifier('nom', e.target.value)}
              />
            </Champ>

            <Champ label="Matricule" obligatoire erreurs={erreurs.matricule}>
              <Saisie
                required
                placeholder="CH-001"
                aria-invalid={Boolean(erreurs.matricule?.length)}
                value={formulaire.matricule}
                onChange={(e) => modifier('matricule', e.target.value)}
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
              {enEdition ? 'Enregistrer les modifications' : 'Ajouter le chauffeur'}
            </Bouton>
          </div>
        </form>
      </Carte>

      <Carte titre="Effectif">
        <Recherche
          valeur={recherche}
          onChange={chercher}
          placeholder="Chercher un nom ou un matricule…"
          className="mb-5 max-w-sm"
        />

        {isLoading ? (
          <Chargement />
        ) : !chauffeurs || chauffeurs.length === 0 ? (
          <EtatVide
            message={
              rechercheDifferee
                ? `Aucun chauffeur ne correspond à « ${rechercheDifferee} ».`
                : 'Aucun chauffeur enregistré.'
            }
          />
        ) : (
          <Tableau>
            <thead>
              <tr>
                <EnteteColonne>Nom</EnteteColonne>
                <EnteteColonne>Matricule</EnteteColonne>
                <EnteteColonne>État</EnteteColonne>
                <EnteteColonne aligne="droite">
                  <span className="sr-only">Actions</span>
                </EnteteColonne>
              </tr>
            </thead>
            <tbody>
              {chauffeurs.map((chauffeur, index) => {
                const derniere = index === chauffeurs.length - 1

                return (
                  <tr
                    key={chauffeur.id}
                    className={
                      enEdition?.id === chauffeur.id
                        ? 'bg-kinpaku-pale/30'
                        : 'transition-colors hover:bg-papier-profond'
                    }
                  >
                    <Cellule derniere={derniere}>{chauffeur.nom}</Cellule>
                    <Cellule derniere={derniere} className="font-mono text-xs tracking-[0.06em]">
                      {chauffeur.matricule}
                    </Cellule>
                    <Cellule derniere={derniere}>
                      {chauffeur.actif ? <Badge ton="or">Actif</Badge> : <Badge>Inactif</Badge>}
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      <div className="flex justify-end gap-1">
                        <Bouton
                          variante="discret"
                          type="button"
                          aria-label={`Modifier ${chauffeur.nom}`}
                          onClick={() => editer(chauffeur)}
                        >
                          <Pencil className="size-4" aria-hidden />
                        </Bouton>
                        <Bouton
                          variante="discret"
                          type="button"
                          aria-label={`Supprimer ${chauffeur.nom}`}
                          onClick={() => {
                            if (window.confirm(`Supprimer ${chauffeur.nom} ?`)) {
                              supprimer.mutate(chauffeur.id)
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
