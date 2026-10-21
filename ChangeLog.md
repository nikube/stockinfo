# ChangeLog

## 1.1.0 (2026-10-21)

- Numéro de module 194053 (plage Anatole Conseil) au lieu de 500100.
- Requêtes groupées par produit (3 SQL par page au lieu de 3 par ligne), périmètre entité.
- Stock réel = somme des entrepôts ouverts, même périmètre en mode total et par entrepôt.
- Mode ajusté appliqué seulement si `STOCK_CALCULATE_ON_BILL` (évite la double déduction).
- Colonne masquée sans droit Stock → Lire.
- Formatage côté serveur dans la langue de l'utilisateur, classes CSS Dolibarr au lieu de couleurs en dur.
- JS : une passe au DOM prêt, plus de setTimeout en boucle ni de console.log, plus de modification des colspan.
- Setup : POST obligatoire, mode et entrepôt validés, retour de `dolibarr_set_const` vérifié, alerte si l'entrepôt configuré n'est plus ouvert.
- Suppression de `STOCKINFO_ENABLED` (module actif = colonne active), de `require.php` et de l'appel `_load_tables` sans dossier sql.
- Review Codex appliquée.

## 1.0.0 (2024)

- Version initiale en production chez Serendip.
