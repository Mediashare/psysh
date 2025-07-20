# Intégration de Xdebug dans PsySH : Fonctionnalités et Implémentation

Ce document décrit les fonctionnalités potentielles offertes par l'intégration de Xdebug, avec un focus particulier sur une spécification technique pour le profilage de code.

---

## 1. Spécification Technique : Profilage de Code Interactif

Cette section détaille l'implémentation de la fonctionnalité de profilage, qui est un excellent premier pas vers une intégration plus profonde de Xdebug.

### 1.1. Fonctionnalité

Permettre à l'utilisateur de profiler un bloc de code directement depuis le REPL pour analyser ses performances (temps CPU, mémoire) et identifier les goulots d'étranglement.

### 1.2. Commandes Utilisateur

- **Commande principale pour un résumé rapide :**
  ```php
  profile <PHP code to profile>
  ```
- **Commande avec exportation pour une analyse avancée :**
  ```php
  profile --out=<file.grind> <PHP code to profile>
  ```

### 1.3. Méthode d'Implémentation

L'implémentation se fera en créant une nouvelle commande Symfony (`ProfileCommand`) et un service de support (`Profiler`) pour gérer la logique.

#### a) Création de la Commande (`ProfileCommand.php`)

1.  **Fichier** : `src/Command/ProfileCommand.php`
2.  **Héritage** : La classe doit hériter de `Psy\Command\Command`.
3.  **Configuration (`configure()`)** :
    -   Nom de la commande : `profile`.
    -   Argument : `code` (requis), pour le code à profiler.
    -   Option : `out` (optionnel), pour le chemin du fichier de sortie.
    -   Description : "Profile a string of PHP code and display the execution summary."

#### b) Logique d'Exécution (`execute()`)

1.  **Vérification des prérequis** : La commande doit d'abord vérifier si l'extension Xdebug est chargée (`extension_loaded('xdebug')`) et si le mode profilage est disponible. Si ce n'est pas le cas, afficher un message d'erreur clair et s'arrêter.
2.  **Récupération du code** : Le code à profiler sera récupéré depuis l'argument de la commande.
3.  **Isolation du processus** :
    -   Pour éviter d'impacter la session Psysh principale, le profilage doit être exécuté dans un **processus PHP isolé**.
    -   Utiliser le composant `Symfony\Component\Process\Process` pour construire et lancer la commande.
    -   Le processus enfant sera lancé avec les directives `ini` nécessaires pour activer le profilage à la demande.
    ```php
    $process = new Process([
        'php',
        '-d', 'xdebug.mode=profile',
        '-d', 'xdebug.start_with_request=yes',
        '-d', 'xdebug.output_dir=/tmp', // Un répertoire temporaire
        '-r', $codeToProfile, // Le code passé via -r
    ]);
    $process->run();
    ```
4.  **Gestion du Fichier de Profilage** :
    -   Xdebug générera un fichier (ex: `cachegrind.out.12345`) dans le répertoire de sortie spécifié. La commande doit trouver ce nouveau fichier (par exemple, en cherchant le plus récent).
5.  **Analyse et Affichage (cas par défaut)** :
    -   Si l'option `--out` n'est **pas** utilisée, la commande doit analyser le fichier `cachegrind.out.*`.
    -   Un simple analyseur peut être écrit pour extraire les informations clés : temps total, mémoire, et une liste des fonctions avec leur coût cumulé ("self cost").
    -   La sortie sera formatée et affichée dans la console à l'aide d'une table `Symfony\Component\Console\Helper\Table`.
6.  **Exportation (cas `--out`)** :
    -   Si l'option `--out` est utilisée, la commande déplacera (ou copiera) le fichier `cachegrind.out.*` vers le chemin spécifié par l'utilisateur.
    -   Un message de succès indiquera où le fichier a été sauvegardé.

#### c) Tests

-   Ajouter un test unitaire dans `test/Command/ProfileCommandTest.php`.
-   Le test devra utiliser un "mock" du `Process` pour simuler l'exécution de PHP et la création d'un faux fichier de profilage, afin de vérifier que la logique d'analyse et de formatage fonctionne correctement.

---

## 2. Idées et Fonctionnalités Futures (Brainstorming)

Cette section conserve les idées créatives pour des intégrations futures plus poussées.

### 🔍 Débogage Interactif Avancé
- **Breakpoints Dynamiques**: `break Class::method`, `break-if $condition`
- **Exploration du Contexte**: `context --depth=3`, `watch $variable`

### 📊 Profilage Interactif (Extensions)
- **`hotspots`**: Alias pour `profile` qui trie par les fonctions les plus coûteuses.
- **`memory-map`**: Visualisation ASCII de l'utilisation mémoire.
- **`compare {codeA} vs {codeB}`**: Comparaison de deux blocs de code.

### 🕵️ Analyse de Code Approfondie
- **Traçage Intelligent**: `trace --smart` (ignore les vendors), `trace-sql`, `trace-http`.
- **Analyse de Couverture**: `coverage {code}` pour voir la couverture de test.

### 🎯 Productivité
- **`explain $exception`**: Analyse une exception avec des suggestions.
- **`stack --annotate`**: Affiche la stack trace avec les valeurs des arguments.
- **Time Travel Debugging**: Naviguer dans l'historique d'exécution (très complexe).

### 🎨 Interface Utilisateur
- **Flamegraphs interactifs** dans le terminal.
- **Graphiques de dépendances** générés automatiquement.
