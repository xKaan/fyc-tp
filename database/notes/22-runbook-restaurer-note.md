---
titre: Runbook : restaurer une note supprimée par erreur
projet: Infra
auteur: chloe
tags: runbook, restauration
---
À suivre quand un utilisateur a supprimé une note par erreur.

1. Moins de 30 jours : la note est dans la corbeille. La restaurer depuis l'écran d'administration, sans toucher à la base.
2. Plus de 30 jours : restaurer depuis la sauvegarde quotidienne. Ouvrir un ticket, la restauration se fait sur une base séparée, jamais sur la production directement.
3. Prévenir l'utilisateur et noter l'intervention dans le journal d'exploitation.

Délai cible : une heure pour le cas 1, une journée ouvrée pour le cas 2.
