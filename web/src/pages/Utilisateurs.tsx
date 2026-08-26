import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Trash2, X } from 'lucide-react'
import { useRef, useState } from 'react'
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
  Guidage,
  Liste,
  Saisie,
  Tableau,
} from '@/components/ui'
import { api, erreursParChamp, messagesErreur } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import type { RoleCode, RoleOption, Utilisateur } from '@/types'

interface Formulaire {
  nom: string
  matricule: string
  role: RoleCode
  password: string
  actif: boolean
}

const FORMULAIRE_VIDE: Formulaire = {
  nom: '',
  matricule: '',
  role: 'pompiste',
  password: '',
  actif: true,
}

export function Utilisateurs() {
  const queryClient = useQueryClient()
  const { utilisateur: moi } = useAuth()
  const [formulaire, setFormulaire] = useState<Formulaire>(FORMULAIRE_VIDE)
  const [enEdition, setEnEdition] = useState<Utilisateur | null>(null)
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({})
  const [messageGlobal, setMessageGlobal] = useState<string[]>([])
  const formulaireRef = useRef<HTMLFormElement>(null)

  const { data: comptes, isLoading } = useQuery({
    queryKey: ['utilisateurs'],
    queryFn: async () => (await api.get<{ data: Utilisateur[] }>('/utilisateurs')).data.data,
  })

  const { data: roles } = useQuery({
    queryKey: ['roles'],
    queryFn: async () => (await api.get<{ data: RoleOption[] }>('/referentiel/roles')).data.data,
  })

  const reinitialiser = () => {
    setFormulaire(FORMULAIRE_VIDE)
    setEnEdition(null)
    setErreurs({})
    setMessageGlobal([])
  }

  const editer = (compte: Utilisateur) => {
    setEnEdition(compte)
    setErreurs({})
    setMessageGlobal([])
    setFormulaire({
      nom: compte.nom,
      matricule: compte.matricule,
      role: compte.role,
      // Volontairement vide : laisser le champ tel quel conserve le mot de
      // passe existant, le remplir en attribue un nouveau.
      password: '',
      actif: compte.actif,
    })

    formulaireRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }

  const enregistrer = useMutation({
    mutationFn: async (valeurs: Formulaire) => {
      const corps = {
        nom: valeurs.nom,
        matricule: valeurs.matricule,
        role: valeurs.role,
        actif: valeurs.actif,
        ...(valeurs.password ? { password: valeurs.password } : {}),
      }

      return enEdition
        ? api.put(`/utilisateurs/${enEdition.id}`, corps)
        : api.post('/utilisateurs', corps)
    },
    onSuccess: () => {
      toast.success(enEdition ? 'Compte mis à jour' : 'Compte créé')
      reinitialiser()
      queryClient.invalidateQueries({ queryKey: ['utilisateurs'] })
    },
    onError: (erreur) => {
      setErreurs(erreursParChamp(erreur))
      setMessageGlobal(messagesErreur(erreur))
      toast.error('Enregistrement refusé')
    },
  })

  const supprimer = useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/utilisateurs/${id}`)

      return id
    },
    onSuccess: (id) => {
      toast.success('Compte supprimé')

      if (enEdition?.id === id) reinitialiser()

      queryClient.invalidateQueries({ queryKey: ['utilisateurs'] })
    },
    // Le refus du dernier gestionnaire ou de son propre compte arrive en 409
    // avec le motif : il s'affiche tel quel.
    onError: (erreur) => toast.error(messagesErreur(erreur)[0]),
  })

  const modifier = <C extends keyof Formulaire>(champ: C, valeur: Formulaire[C]) =>
    setFormulaire((precedent) => ({ ...precedent, [champ]: valeur }))

  const roleChoisi = roles?.find((r) => r.valeur === formulaire.role)

  return (
    <div className="space-y-12">
      <EnteteEcran surtitre="Référentiel · comptes d’accès" titre="Comptes">
        Qui peut entrer dans l’application, et jusqu’où. À ne pas confondre avec les chauffeurs :
        un pompiste enregistre des pleins sans en recevoir, un chauffeur en reçoit sans forcément
        toucher à l’application.
      </EnteteEcran>

      <Guidage titre="Mot de passe oublié">
        Il n’y a pas de réinitialisation par courriel, puisque les comptes n’ont pas d’adresse.
        Ouvrez le compte concerné et saisissez un nouveau mot de passe : il remplace l’ancien
        immédiatement.
      </Guidage>

      <Carte
        titre={enEdition ? `Modifier ${enEdition.nom}` : 'Nouveau compte'}
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

          <div className="grid gap-x-9 gap-y-7 sm:grid-cols-2 lg:grid-cols-4">
            <Champ label="Nom" obligatoire erreurs={erreurs.nom}>
              <Saisie
                required
                placeholder="Fatoumata Diarra"
                value={formulaire.nom}
                onChange={(e) => modifier('nom', e.target.value)}
              />
            </Champ>

            <Champ
              label="Matricule"
              obligatoire
              erreurs={erreurs.matricule}
              indication="Sert d’identifiant de connexion"
            >
              <Saisie
                required
                placeholder="POMPE-01"
                aria-invalid={Boolean(erreurs.matricule?.length)}
                value={formulaire.matricule}
                onChange={(e) => modifier('matricule', e.target.value)}
              />
            </Champ>

            <Champ
              label="Rôle"
              obligatoire
              erreurs={erreurs.role}
              indication={roleChoisi?.description}
            >
              <Liste
                value={formulaire.role}
                onChange={(valeur) => modifier('role', valeur as RoleCode)}
                invalide={Boolean(erreurs.role?.length)}
                options={(roles ?? []).map((role) => ({
                  valeur: role.valeur,
                  libelle: role.libelle,
                  detail: role.description,
                }))}
              />
            </Champ>

            <Champ
              label="Mot de passe"
              obligatoire={!enEdition}
              erreurs={erreurs.password}
              indication={
                enEdition
                  ? 'Laissez vide pour conserver le mot de passe actuel'
                  : 'Huit caractères au minimum'
              }
            >
              <Saisie
                type="password"
                autoComplete="new-password"
                minLength={8}
                required={!enEdition}
                aria-invalid={Boolean(erreurs.password?.length)}
                value={formulaire.password}
                onChange={(e) => modifier('password', e.target.value)}
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
              Actif — peut se connecter
            </label>

            <Bouton type="submit" disabled={enregistrer.isPending}>
              {enEdition ? (
                <Pencil className="size-4" aria-hidden />
              ) : (
                <Plus className="size-4" aria-hidden />
              )}
              {enEdition ? 'Enregistrer les modifications' : 'Créer le compte'}
            </Bouton>
          </div>
        </form>
      </Carte>

      <Carte titre="Comptes existants">
        {isLoading ? (
          <Chargement />
        ) : !comptes || comptes.length === 0 ? (
          <EtatVide message="Aucun compte enregistré." />
        ) : (
          <Tableau>
            <thead>
              <tr>
                <EnteteColonne>Nom</EnteteColonne>
                <EnteteColonne>Matricule</EnteteColonne>
                <EnteteColonne>Rôle</EnteteColonne>
                <EnteteColonne>État</EnteteColonne>
                <EnteteColonne aligne="droite">
                  <span className="sr-only">Actions</span>
                </EnteteColonne>
              </tr>
            </thead>
            <tbody>
              {comptes.map((compte, index) => {
                const derniere = index === comptes.length - 1
                const cestMoi = compte.id === moi?.id

                return (
                  <tr
                    key={compte.id}
                    className={
                      enEdition?.id === compte.id
                        ? 'bg-kinpaku-pale/30'
                        : 'transition-colors hover:bg-papier-profond'
                    }
                  >
                    <Cellule derniere={derniere}>
                      {compte.nom}
                      {cestMoi && (
                        <span className="ml-2 text-xs text-pale">vous</span>
                      )}
                    </Cellule>
                    <Cellule derniere={derniere} className="font-mono text-xs tracking-[0.06em]">
                      {compte.matricule}
                    </Cellule>
                    <Cellule derniere={derniere}>
                      {compte.role_libelle}
                      <span className="block text-xs text-pale">{compte.role_description}</span>
                    </Cellule>
                    <Cellule derniere={derniere}>
                      {compte.actif ? <Badge ton="or">Actif</Badge> : <Badge>Désactivé</Badge>}
                    </Cellule>
                    <Cellule derniere={derniere} aligne="droite">
                      <div className="flex justify-end gap-1">
                        <Bouton
                          variante="discret"
                          type="button"
                          aria-label={`Modifier ${compte.nom}`}
                          onClick={() => editer(compte)}
                        >
                          <Pencil className="size-4" aria-hidden />
                        </Bouton>
                        <Bouton
                          variante="discret"
                          type="button"
                          disabled={cestMoi}
                          aria-label={`Supprimer ${compte.nom}`}
                          title={cestMoi ? 'Vous ne pouvez pas supprimer votre propre compte.' : undefined}
                          onClick={() => {
                            if (window.confirm(`Supprimer le compte de ${compte.nom} ?`)) {
                              supprimer.mutate(compte.id)
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
      </Carte>
    </div>
  )
}
