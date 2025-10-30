# Guide Rapide : Utilisation de la Commande Profile

## Activation de Xdebug Trace

Pour utiliser la commande `profile`, vous devez activer le mode trace de Xdebug :

```bash
XDEBUG_MODE=trace,develop php bin/psysh
```

Ou de façon permanente dans votre `php.ini` ou `.ini` de développement :
```ini
xdebug.mode=trace,develop
```

## Exemples d'Utilisation

### 1. Profiler une Expression Simple
```php
> profile strlen("hello world")
```

### 2. Profiler du Code Multi-lignes
```php
> profile function test() { return array_sum(range(1, 100)); } test()
```

### 3. Profiler un Fichier
```php
> profile @/path/to/script.php
```

### 4. Mode Debug (Informations Détaillées)
```php
> profile --debug array_map(fn($x) => $x * 2, range(1, 100))
```

### 5. Filtrage par Niveau
```php
// Seulement le code utilisateur (défaut)
> profile --filter=user my_function()

// Inclure les fonctions PHP internes
> profile --filter=php my_function()

// Tout afficher (y compris PsySH)
> profile --filter=all my_function()
```

### 6. Seuil de Temps Minimum
```php
// Afficher seulement les fonctions > 100μs
> profile --threshold=100 complex_operation()

// Afficher seulement les fonctions > 1ms
> profile --threshold=1000 complex_operation()
```

### 7. Afficher les Paramètres
```php
> profile --show-params my_function(arg1, arg2)
```

### 8. Exporter les Données
```php
> profile --out=/tmp/profile.json expensive_operation()
```

### 9. Mode Trace Complet (Toutes les Fonctions)
```php
> profile --trace-all my_function()
```

## Dépannage

### Erreur : "No profiling data collected"

**Causes possibles :**
1. Xdebug trace mode n'est pas activé
2. Le code s'exécute trop rapidement
3. Tous les appels sont filtrés

**Solutions :**
```bash
# Vérifier que Xdebug est chargé avec trace mode
php -i | grep xdebug.mode

# Lancer avec le bon mode
XDEBUG_MODE=trace,develop php bin/psysh

# Utiliser --debug pour plus d'informations
> profile --debug your_code()
```

### Erreur : "Profiling not available"

**Cause :** Ni XHProf ni Xdebug n'est disponible.

**Solutions :**
```bash
# Option 1 : Installer XHProf (recommandé pour la production)
pecl install xhprof

# Option 2 : Activer Xdebug trace
XDEBUG_MODE=trace,develop php bin/psysh
```

## Performance

### Moteurs de Profilage Disponibles

1. **XHProf** (recommandé) - In-process, très rapide
2. **Xdebug Subprocess** - Processus isolé, capture tout
3. **Xdebug In-Process** - In-process, léger

L'ordre de préférence est automatique : XHProf → Xdebug In-Process → Xdebug Subprocess

### Conseils de Performance

- Utilisez `--threshold` pour filtrer le bruit
- Utilisez `--filter=user` pour ignorer les internals PHP
- `--trace-all` est très verbeux, utilisez-le uniquement si nécessaire
- XHProf est plus rapide que Xdebug pour le profilage

## Interprétation des Résultats

```
+-----------+-------+---------+--------+--------+----------+
| Function  | Calls | Time    | Time % | Memory | Memory % |
+-----------+-------+---------+--------+--------+----------+
| fibonacci | 177   | 10.8 ms | 99.8%  | 0 B    | 0.0%     |
+-----------+-------+---------+--------+--------+----------+

Total execution: Time: 10.8 ms, Memory: 0 B
```

- **Function** : Nom de la fonction/méthode
- **Calls** : Nombre d'appels
- **Time** : Temps total d'exécution (μs/ms/s)
- **Time %** : Pourcentage du temps total
- **Memory** : Mémoire utilisée (B/KB/MB)
- **Memory %** : Pourcentage de la mémoire totale

### Identification des Bottlenecks

1. **Fonctions avec Time % élevé** : Cibles d'optimisation principales
2. **Fonctions avec beaucoup d'appels** : Candidats pour la mémoïsation/cache
3. **Memory % élevé** : Risques de fuites mémoire

## Exemples Pratiques

### Comparer Deux Approches

```php
> profile array_reduce(range(1, 1000), fn($a, $b) => $a + $b, 0)
> profile array_sum(range(1, 1000))
```

### Identifier les Appels Coûteux

```php
> profile --threshold=1000 complex_algorithm()
```

### Debugging de Performance

```php
> profile --debug --show-params --full-namespaces slow_function()
```
