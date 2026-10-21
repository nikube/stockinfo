# StockInfo — colonne « Stock » sur les lignes de documents

Module Dolibarr (id 194053) qui ajoute une colonne **Stock** après la quantité sur les fiches
commande, devis, facture et facture récurrente. Rouge gras sous le seuil d'alerte ou négatif,
vert sinon. Visible uniquement avec le droit *Stock → Lire*.

## Réglages (Configuration → Modules → Info Stock)

- **Mode de calcul**
  - *Stock réel* : somme des entrepôts ouverts (ou de l'entrepôt choisi).
  - *Stock ajusté* : stock réel moins les quantités des commandes classées « Livrée » non facturées.
    Ne s'applique que si `STOCK_CALCULATE_ON_BILL` est actif (déstockage à la facture), sinon le
    stock réel est affiché. Pas de filtre entrepôt sur cette déduction (les commandes n'en portent pas).
- **Entrepôt** : tous les entrepôts ouverts, ou un seul.

## Technique

Hook `addMoreActionsButtons` (contextes ordercard/propalcard/invoicecard/invoicereccard) :
3 requêtes SQL par page quel que soit le nombre de lignes, formatage côté serveur (`price()`),
injection JS de la colonne après `linecolqty`. La colonne n'apparaît pas en édition de ligne
(le hook n'est pas appelé sur `editline`, comportement core).

Dolibarr ≥ 18. Testé 23.0.2 (dolitest) et 24.0.2 (Serendip).
