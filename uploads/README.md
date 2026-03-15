# Dossier Uploads - Cosmos Group

Ce dossier contient les fichiers téléchargés par les candidats.

## Structure

```
uploads/
└── cv/          # CV et lettres de motivation des candidats
```

## Sécurité

- Le fichier `.htaccess` empêche l'exécution de scripts PHP
- Seuls les fichiers PDF, DOC et DOCX sont autorisés
- L'affichage du contenu du répertoire est désactivé

## Permissions

Le dossier doit avoir les permissions suivantes :
- Propriétaire : www-data (ou utilisateur du serveur web)
- Permissions : 755 (rwxr-xr-x)

## Maintenance

- Nettoyer régulièrement les anciens fichiers
- Vérifier l'espace disque disponible
- Sauvegarder les fichiers importants

## Notes

- Les noms de fichiers sont générés automatiquement pour éviter les conflits
- Format : `nom_timestamp_random.extension`
- Taille maximale : 5 MB par fichier
