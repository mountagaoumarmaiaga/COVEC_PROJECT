import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Save } from 'lucide-react'
import { useEffect, useState } from 'react'
import { toast } from 'sonner'

import {
  AlerteErreurs,
  Bouton,
  Carte,
  Champ,
  Chargement,
  EnteteEcran,
  EtatVide,
  Guidage,
  Saisie,
} from '@/components/ui'
import { api, erreursParChamp, messagesErreur } from '@/lib/api'
import { formaterLitres, formaterMontant } from '@/lib/format'
import type { Carburant } from '@/types'

interface Formulaire {
  libelle: string
  prix_par_defaut: string
  nom_cuve: string
  capacite: string
}

/**
 * Fiche d'un carburant et de sa cuve.
 *
 * Les deux se modifient d'un seul geste : ils forment une même ligne dans la
 * tête du gestionnaire — « le gasoil, sa cuve, son prix » — et les séparer
 * obligerait à enregistrer deux fois pour une seule décision.
 */
function FicheCarburant({ carburant }: { carburant: Carburant }) {
  const queryClient = useQueryClient()
  const [erreurs, setErreurs] = useState<Record<string, string[]>>({})
  const [messageGlobal, setMessageGlobal] = useState<string[]>([])
  const [formulaire, setFormulaire] = useState<Formulaire>({
    libelle: carburant.libelle,
    prix_par_defaut: String(carburant.prix_par_defaut),
    nom_cuve: carburant.cuve?.nom ?? '',
    capacite: String(carburant.cuve?.capacite ?? ''),
  })

  // La fiche se recale sur les valeurs du serveur après chaque rechargement.
  useEffect(() => {
    setFormulaire({
      libelle: carburant.libelle,
      prix_par_defaut: String(carburant.prix_par_defaut),
      nom_cuve: carburant.cuve?.nom ?? '',
      capacite: String(carburant.cuve?.capacite ?? ''),
    })
  }, [carburant])

  const enregistrer = useMutation({
    mutationFn: async () =>
      api.put(`/carburants/${carburant.id}`, {
        libelle: formulaire.libelle,
        prix_par_defaut: Number(formulaire.prix_par_defaut),
        cuve: {
          nom: formulaire.nom_cuve,
          capacite: Number(formulaire.capacite),
        },
      }),
    onSuccess: () => {
      setErreurs({})
      setMessageGlobal([])
      toast.success(`${formulaire.libelle} mis à jour`)
      queryClient.invalidateQueries({ queryKey: ['carburants'] })
      queryClient.invalidateQueries({ queryKey: ['stock'] })
    },
    onError: (erreur) => {
      setErreurs(erreursParChamp(erreur))
      setMessageGlobal(messagesErreur(erreur))
      toast.error('Enregistrement refusé')
    },
  })

  const modifier = (champ: keyof Formulaire, valeur: string) =>
    setFormulaire((precedent) => ({ ...precedent, [champ]: valeur }))

  return (
    <Carte
      titre={carburant.libelle}
      description={`Prix en vigueur ${formaterMontant(carburant.prix_par_defaut)} le litre · cuve de ${formaterLitres(carburant.cuve?.capacite)}`}
      levee
    >
      <form
        className="space-y-7"
        onSubmit={(e) => {
          e.preventDefault()
          enregistrer.mutate()
        }}
      >
        <AlerteErreurs messages={messageGlobal} />

        <div className="grid gap-x-9 gap-y-7 sm:grid-cols-2 lg:grid-cols-4">
          <Champ label="Libellé" obligatoire erreurs={erreurs.libelle}>
            <Saisie
              required
              value={formulaire.libelle}
              onChange={(e) => modifier('libelle', e.target.value)}
            />
          </Champ>

          <Champ
            label="Prix du litre (FCFA)"
            obligatoire
            erreurs={erreurs.prix_par_defaut}
            indication="S’applique aux pleins et pré-remplit les livraisons"
          >
            <Saisie
              type="number"
              step="0.01"
              min="0"
              required
              aria-invalid={Boolean(erreurs.prix_par_defaut?.length)}
              value={formulaire.prix_par_defaut}
              onChange={(e) => modifier('prix_par_defaut', e.target.value)}
            />
          </Champ>

          <Champ label="Nom de la cuve" obligatoire erreurs={erreurs['cuve.nom']}>
            <Saisie
              required
              value={formulaire.nom_cuve}
              onChange={(e) => modifier('nom_cuve', e.target.value)}
            />
          </Champ>

          <Champ
            label="Capacité de la cuve (L)"
            obligatoire
            erreurs={erreurs['cuve.capacite']}
            indication="Référence du niveau affiché sur l’écran de stock"
          >
            <Saisie
              type="number"
              step="0.01"
              min="0.01"
              required
              aria-invalid={Boolean(erreurs['cuve.capacite']?.length)}
              value={formulaire.capacite}
              onChange={(e) => modifier('capacite', e.target.value)}
            />
          </Champ>
        </div>

        <div className="flex justify-end">
          <Bouton type="submit" disabled={enregistrer.isPending}>
            <Save className="size-4" aria-hidden />
            {enregistrer.isPending ? 'Enregistrement…' : 'Enregistrer'}
          </Bouton>
        </div>
      </form>
    </Carte>
  )
}

export function Carburants() {
  const { data: carburants, isLoading } = useQuery({
    queryKey: ['carburants'],
    queryFn: async () => (await api.get<{ data: Carburant[] }>('/carburants')).data.data,
  })

  // Une capacité restée à zéro vient de l'amorçage : elle dépend de la cuve
  // réellement installée, et personne d'autre que la station ne la connaît.
  const capaciteAConfigurer = carburants?.some((c) => !c.cuve?.capacite) ?? false

  return (
    <div className="space-y-12">
      <EnteteEcran surtitre="Référentiel · saisi une seule fois" titre="Carburants et cuves">
        Un carburant, une cuve, un prix. Le prix du litre s’applique
        automatiquement à chaque plein servi et pré-remplit chaque livraison — un plein déjà
        enregistré garde le prix qu’il portait, même après une hausse.
      </EnteteEcran>

      {capaciteAConfigurer && (
        <Guidage titre="Capacité de cuve à renseigner">
          Une cuve sans capacité empêche l’écran de stock d’afficher un niveau de remplissage.
          Indiquez le volume de chaque cuve installée sur le site.
        </Guidage>
      )}

      {isLoading && <Chargement />}

      {!isLoading && (!carburants || carburants.length === 0) && (
        <EtatVide message="Aucun carburant enregistré." />
      )}

      {carburants?.map((carburant) => (
        <FicheCarburant key={carburant.id} carburant={carburant} />
      ))}
    </div>
  )
}
