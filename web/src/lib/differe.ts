import { useEffect, useState } from 'react'

/**
 * Renvoie la valeur après un silence de `delai` millisecondes.
 *
 * Une recherche branchée directement sur la frappe enverrait une requête par
 * caractère : « hilux » en produirait cinq, dont quatre déjà périmées à leur
 * arrivée. Le différé n'interroge le serveur qu'une fois la saisie posée.
 */
export function useDiffere<T>(valeur: T, delai = 300): T {
  const [differee, setDifferee] = useState(valeur)

  useEffect(() => {
    const minuteur = setTimeout(() => setDifferee(valeur), delai)

    return () => clearTimeout(minuteur)
  }, [valeur, delai])

  return differee
}
