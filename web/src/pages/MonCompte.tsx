import { KeyRound } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'

import {
  AlerteErreurs,
  Bouton,
  Carte,
  Champ,
  EnteteEcran,
  Saisie,
  Surtitre,
} from '@/components/ui'
import { api, erreursParChamp, messagesErreur } from '@/lib/api'
import { useAuth } from '@/lib/auth'

const VIDE = { actuel: '', nouveau: '', confirmation: '' }

export function MonCompte() {
  const { utilisateur } = useAuth()
  const [formulaire, setFormulaire] = useState(VIDE)
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({})
  const [messageGlobal, setMessageGlobal] = useState<string[]>([])
  const [enCours, setEnCours] = useState(false)

  if (!utilisateur) return null

  const modifier = (champ: keyof typeof VIDE, valeur: string) =>
    setFormulaire((precedent) => ({ ...precedent, [champ]: valeur }))

  const soumettre = async (evenement: React.FormEvent) => {
    evenement.preventDefault()
    setErreurs({})
    setMessageGlobal([])
    setEnCours(true)

    try {
      await api.put('/moi/mot-de-passe', {
        actuel: formulaire.actuel,
        nouveau: formulaire.nouveau,
        nouveau_confirmation: formulaire.confirmation,
      })

      setFormulaire(VIDE)
      toast.success('Mot de passe modifié')
    } catch (erreur) {
      setErreurs(erreursParChamp(erreur))
      setMessageGlobal(messagesErreur(erreur))
      toast.error('Modification refusée')
    } finally {
      setEnCours(false)
    }
  }

  const identite = [
    ['Nom', utilisateur.nom],
    ['Matricule', utilisateur.matricule],
    ['Rôle', utilisateur.role_libelle],
    ['Droits', utilisateur.role_description],
  ] as const

  return (
    <div className="space-y-12">
      <EnteteEcran surtitre="Mon compte" titre={utilisateur.nom}>
        {utilisateur.role_description}
      </EnteteEcran>

      <div className="grid gap-x-12 gap-y-10 lg:grid-cols-2">
        <section className="border-t border-arete pt-6">
          <Surtitre ton="or">Identité</Surtitre>
          <dl className="mt-4">
            {identite.map(([libelle, valeur], index) => (
              <div
                key={libelle}
                className={`flex items-baseline justify-between gap-6 py-2.5 ${
                  index === identite.length - 1 ? '' : 'border-b border-filet'
                }`}
              >
                <dt className="shrink-0 text-[13px] text-attenue">{libelle}</dt>
                <dd className="text-right text-[13px]">{valeur}</dd>
              </div>
            ))}
          </dl>

          <p className="mt-5 text-xs text-pale">
            Nom, matricule et rôle sont tenus par le gestionnaire du dépôt.
          </p>
        </section>

        <Carte titre="Changer de mot de passe" levee>
          <form className="space-y-7" onSubmit={soumettre}>
            <AlerteErreurs messages={messageGlobal} />

            <div className="space-y-7">
              <Champ label="Mot de passe actuel" obligatoire erreurs={erreurs.actuel}>
                <Saisie
                  required
                  type="password"
                  autoComplete="current-password"
                  aria-invalid={Boolean(erreurs.actuel?.length)}
                  value={formulaire.actuel}
                  onChange={(e) => modifier('actuel', e.target.value)}
                />
              </Champ>

              <Champ
                label="Nouveau mot de passe"
                obligatoire
                erreurs={erreurs.nouveau}
                indication="Huit caractères au minimum"
              >
                <Saisie
                  required
                  type="password"
                  autoComplete="new-password"
                  minLength={8}
                  aria-invalid={Boolean(erreurs.nouveau?.length)}
                  value={formulaire.nouveau}
                  onChange={(e) => modifier('nouveau', e.target.value)}
                />
              </Champ>

              <Champ label="Confirmation" obligatoire>
                <Saisie
                  required
                  type="password"
                  autoComplete="new-password"
                  value={formulaire.confirmation}
                  onChange={(e) => modifier('confirmation', e.target.value)}
                />
              </Champ>
            </div>

            <div className="flex justify-end">
              <Bouton type="submit" disabled={enCours}>
                <KeyRound className="size-4" aria-hidden />
                {enCours ? 'Modification…' : 'Changer le mot de passe'}
              </Bouton>
            </div>
          </form>
        </Carte>
      </div>
    </div>
  )
}
