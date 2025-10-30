# Corrections de la Commande Profile - Problème de Données Xdebug Vides

## Problème Identifié

La commande `profile` ne retournait aucune donnée Xdebug pour les raisons suivantes :

### 1. Mode Xdebug Incorrect
- **Problème** : Xdebug était en mode `develop` au lieu de `trace`
- **Impact** : Le traçage des fonctions n'était pas activé
- **Solution** : Documentation claire indiquant qu'il faut lancer avec `XDEBUG_MODE=trace,develop`

### 2. Output Utilisateur Polluant le Stdout
- **Problème** : Le code utilisateur qui contient des `echo` pollue stdout avant l'affichage du chemin du fichier de trace
- **Symptôme** : Chemin de trace corrompu comme "11/var/tmp/trace..." au lieu de "/var/tmp/trace..."
- **Solution** : Utilisation de `ob_start()` / `ob_end_clean()` pour capturer et supprimer l'output utilisateur

### 3. Fichiers de Trace Compressés
- **Problème** : Xdebug 3 génère des fichiers `.xt.gz` compressés par défaut
- **Impact** : Le parser ne pouvait pas lire les fichiers compressés
- **Solution** : Détection automatique et décompression avec `gzdecode()`

### 4. Format de Trace Xdebug 3 Moderne
- **Problème** : Xdebug 3 utilise le "File format: 4" au lieu du format 1 legacy
- **Impact** : Le regex de parsing ne correspondait pas au nouveau format
- **Solution** : Parser mis à jour pour supporter les deux formats (legacy et moderne)

### 5. Statements de Debug Oubliés
- **Problème** : Des appels `dump()` restaient dans le code de production
- **Impact** : Pollution de la sortie
- **Solution** : Suppression des statements de debug

### 6. Précision des Types Float/Int
- **Problème** : Conversions implicites de float vers int causant des warnings
- **Solution** : Utilisation de `(int) round()` pour les conversions explicites

## Corrections Apportées

### XdebugSubprocessEngine.php

1. **Capture de l'output utilisateur** (ligne 65) :
```php
'ob_start();' . PHP_EOL .
'try { %s } catch (\Throwable $__psysh_e) { $__psysh_thrown = $__psysh_e; }' . PHP_EOL .
'ob_end_clean();' . PHP_EOL .
```

2. **Support de la décompression gzip** (ligne 171-180) :
```php
// Detect gzip compression and decompress if needed
if (str_ends_with($traceFile, '.gz') || substr($content, 0, 2) === "\x1f\x8b") {
    $decompressed = @gzdecode($content);
    if ($decompressed === false) {
        throw new RuntimeException(sprintf('Failed to decompress trace file: %s', $traceFile));
    }
    $content = $decompressed;
}
```

3. **Parser multi-format** (lignes 183-295) :
- Détection automatique du format de fichier (format 1 vs format 4)
- Parser pour format 4 (Xdebug 3 moderne) : utilise `func_num` et `type` (0=entrée, 1=sortie)
- Parser pour format 1 (legacy) : utilise `level` et `op` (->/<-)

4. **Conversions de types correctes** (ligne 325) :
```php
(int) round($data['time']),
// ...
(int) round($data['cpu_time']),
```

5. **Suppression des dumps** (lignes 109, 113) :
```php
// Removed: dump($this->createProfileResult($profileData));
// Removed: dump(2, $traceFile);
```

### XdebugInProcessEngine.php

Mêmes corrections pour la décompression et le parsing multi-format.

### ProfileCommand.php

1. **Messages d'erreur améliorés** (ligne 134-145) :
```php
if (empty($profileData)) {
    $output->writeln('<warning>No profiling data collected</warning>');
    $output->writeln('');
    $output->writeln('<comment>This may happen because:</comment>');
    $output->writeln('<comment>1. The code executed too quickly (try more complex code)</comment>');
    $output->writeln('<comment>2. Xdebug trace mode is not properly enabled</comment>');
    $output->writeln('<comment>3. The profiler is filtering out all functions</comment>');
    // ...
}
```

2. **Diagnostics détaillés** (nouvelle méthode `getDiagnostics()`, lignes 733-779) :
- Vérifie si Xdebug est chargé
- Affiche le mode Xdebug actuel
- Détecte si trace mode est activé
- Suggère la commande correcte : `XDEBUG_MODE=trace,develop`
- Vérifie la disponibilité de `xdebug_start_trace()`
- Suggère l'installation de XHProf si nécessaire

## Utilisation Corrigée

### Sans Xdebug Trace (affiche diagnostics)
```bash
php bin/psysh
> profile array_sum([1,2,3])
# Affiche un message d'erreur clair avec diagnostics
```

### Avec Xdebug Trace (fonctionne)
```bash
XDEBUG_MODE=trace,develop php bin/psysh
> profile fibonacci(10)
# Affiche les résultats de profilage
```

### Avec Debug
```bash
XDEBUG_MODE=trace,develop php bin/psysh
> profile --debug fibonacci(10)
# Affiche des informations de debug supplémentaires
```

### Avec Code Complexe
```bash
XDEBUG_MODE=trace,develop php bin/psysh
> profile @/path/to/file.php
# Profile un fichier entier
```

## Tests de Validation

Tous les scénarios suivants ont été testés et fonctionnent :

1. ✅ Profile avec Xdebug trace activé retourne des données
2. ✅ Profile avec code contenant des `echo` fonctionne correctement
3. ✅ Fichiers de trace gzip sont correctement décompressés
4. ✅ Format 4 (Xdebug 3) est correctement parsé
5. ✅ Messages d'erreur clairs quand trace mode n'est pas activé
6. ✅ Diagnostics détaillés avec suggestions de correction
7. ✅ Code récursif (fibonacci) est correctement profilé
8. ✅ Aucun warning de précision float/int

## Conclusion

La commande `profile` est maintenant complètement fonctionnelle avec :
- Support complet de Xdebug 3 (format moderne)
- Rétrocompatibilité avec Xdebug 2 (format legacy)
- Messages d'erreur clairs et diagnostics utiles
- Gestion correcte de tous les cas limites
