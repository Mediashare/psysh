# PsySH UI/UX – Notes de refonte et points d’extension

Dernière mise à jour: 2025-11-01T21:43:50.616Z

Objet: synthèse de l’architecture UI/UX actuelle et des leviers de customisation pour une refonte de l’interface (prompts, couleurs, rendu, completion, pager, I/O).

## Carte des composants pertinents

- Shell (src/Shell.php)
  - Boucle REPL, collecte/évaluation de l’input, rendu stdout/retours/erreurs.
  - Méthodes clés: writeStdout(), writeReturnValue(), writeException(), formatException(), readline(), getPrompt().
  - Utilise Configuration pour thème, Unicode, rawOutput, piping; délègue le rendu à ShellOutput et Presenter; tab completion via AutoCompleter.

- Configuration (src/Configuration.php)
  - Source de vérité UI: colorMode, interactiveMode, verbosity, rawOutput, useUnicode, useReadline, useBracketedPaste, useTabCompletion, pager, theme, formatter styles.
  - Fournit/getOutput() (ShellOutput), getPresenter(), getReadline(), getPager(); propage setTheme() vers ShellOutput.
  - Modes couleur: auto/forced/disabled; interactivité: auto/forced/disabled; gestion pipe pour décorations.

- Output (src/Output/*)
  - ShellOutput: sous-classe ConsoleOutput (Symfony) avec pagination, numérotation de lignes (NUMBER_LINES), et thèmes.
  - Pagers: PassthruPager (direct), ProcOutputPager (commande externe, ex. less), OutputPager (base).
  - Theme: définit prompts (prompt, bufferPrompt, replayPrompt, returnValue), compact, grayFallback et styles (DEFAULT_STYLES). Applique styles sur output et error output.

- Presenter & VarDumper (src/VarDumper/Presenter.php)
  - Transforme des valeurs en sortie colorée/structurée; mappe les « styles » Presenter -> styles Console (num, integer, float, string, class, comment, etc.).

- TraceFormatter (src/Formatter/TraceFormatter.php)
  - Formatte les traces d’exception; support du filtre, chemin relatifs au CWD; option showParams (bool) pour afficher les arguments.

- Readline (src/Readline/*)
  - GNUReadline, Libedit, Userland, Transient implémentent Readline.
  - supportsBracketedPaste(): true pour GNUReadline (hors editline), false sinon; Configuration::useBracketedPaste() active l’envoi des séquences.
  - Historique, redisplay, mapping touches (ex: Ctrl+L en Userland).

- TabCompletion (src/TabCompletion/AutoCompleter.php + Matcher/*)
  - Branche readline_completion_function(); agrége des Matchers; activation contrôlée par Configuration::useTabCompletion().

## Styles et tags de sortie utilisés

- Tags Symfony Console: info, warning, error, whisper, aside, strong, return, urgent, hidden, public, protected, private, global, const, class, function, default, number/integer/float/string/bool/keyword/comment/code_comment/object/resource/inline_html.
- Shell utilise notamment: <whisper>, <aside>, <info> et la valeur de retour préfixée par theme->returnValue().

## Points d’extension UI/UX

- Thème
  - Configuration::setTheme('modern'|'compact'|'classic'|array personnalisée)
  - Theme setters: setPrompt(), setBufferPrompt(), setReplayPrompt(), setReturnValue(), setCompact(), setGrayFallback(), setStyles(array $styles)
  - ShellOutput::setTheme(Theme) ré-applique les styles sur output/erreur.

- Couleurs / Styles
  - Personnaliser Theme::DEFAULT_STYLES via Theme::setStyles([...]) ou passer 'styles' dans la config du thème.
  - Presenter::STYLES peut orienter les couleurs du dumper (via Dumper::setStyles à l’initialisation du Presenter).

- Pager
  - Configuration::setPager(false|'cat'|"less -R"|OutputPager). ShellOutput pagine via startPaging()/stopPaging()/page().

- Entrée / Interaction
  - Configuration::setUseReadline(), setUseTabCompletion(), setUseBracketedPaste(); dépendent du backend Readline (GNU/Libedit/Userland/Transient).

- Traces et erreurs
  - Shell::formatException() + TraceFormatter::formatTrace(); possibilité d’activer showParams lors de l’appel à TraceFormatter si besoin de refonte.

## Exemples de customisation (extraits PHP)

- Thème personnalisé (prompts + compact + styles):

  $config = new \Psy\Configuration([
    'theme' => [
      'compact' => true,
      'prompt' => 'λ ',
      'bufferPrompt' => '… ',
      'replayPrompt' => '↪ ',
      'returnValue' => '⇒ ',
      'styles' => [
        'whisper' => ['gray'],
        'string' => ['green'],
        'number' => ['magenta'],
        'class' => ['blue', null, ['underscore']],
      ],
    ],
    'useTabCompletion' => true,
    'useBracketedPaste' => true,
    'updateCheck' => 'never',
  ]);
  $shell = new \Psy\Shell($config);
  $shell->run();

- Forcer couleurs + pager externe:

  $config->setColorMode(\Psy\Configuration::COLOR_MODE_FORCED);
  $config->setPager('less -R');

## Checklist de refonte

- Prompts: cohérence des 4 prompts (normal/buffer/replay/retour). Vérifier l’alignement et l’indentation des valeurs multi-lignes.
- Lisibilité: compact vs détaillé, symboles Unicode vs ASCII (Configuration::useUnicode()).
- Palette: harmoniser styles Theme et Presenter pour types/visibilités/erreurs.
- Pager: UX de pagination (less/cat), numérotation des lignes (ShellOutput::NUMBER_LINES) si utile.
- Complétion: activer/useTabCompletion et ajuster les Matchers si besoin.
- Collage (paste): activer bracketed paste si GNUReadline disponible.
- Traces: verbosité, inclusion des paramètres (showParams), filtrage (FilterOptions).
- Piping: sorties décorées désactivées si pipe; gérer cas rawOutput.

## Impacts / risques

- Forcer couleurs peut gêner en TTY non compatibles; vérifier getOutputDecorated() et pipes.
- Unicode: fallback ASCII si terminaux limités; prévoir symboles alternatifs.
- Pagers externes: dépendances OS (less), variables d’environnement; prévoir fallback PassthruPager.
- Readline variable selon plateforme (GNU vs Libedit vs Userland/Transient): conditionner des features (ex. bracketed paste).

## Pistes d’amélioration ciblées

- Presets de thèmes supplémentaires (ex: solarized, dracula) via Theme::MODERN_THEME-like.
- Option CLI pour show-trace-args et style dédié aux arguments de stack.
- Profil de complétion enrichi (mix de Matchers contexte + noms d’objets de scope).
- Mode « teaching »: prompts verbeux, plus d’asides/whisper explicatifs.

Répertoires utiles: src/Output (Theme, ShellOutput, Pagers), src/VarDumper (Presenter), src/Formatter (TraceFormatter), src/Readline (implémentations), src/TabCompletion (AutoCompleter + Matchers), src/Configuration.php, src/Shell.php.
