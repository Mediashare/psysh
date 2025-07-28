<?php

namespace Psy\TabCompletion\Matcher;

/**
 * Matcher pour l'autocomplétion des paramètres Symfony
 */
class SymfonyParameterMatcher extends \Psy\TabCompletion\Matcher\AbstractMatcher
{
    private $container;
    private $parameters;

    public function __construct($container)
    {
        $this->container = $container;
        
        // Récupère tous les paramètres
        if (method_exists($container, 'getParameterBag')) {
            $this->parameters = array_keys($container->getParameterBag()->all());
        } else {
            $this->parameters = [];
        }
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        $input = $this->getInput($tokens);
        
        if ($this->isContainerGetParameterContext($tokens)) {
            return array_filter($this->parameters, function($param) use ($input) {
                return stripos($param, $input) === 0;
            });
        }

        return [];
    }

    public function hasMatched(array $tokens): bool
    {
        return $this->isContainerGetParameterContext($tokens);
    }

    private function isContainerGetParameterContext(array $tokens): bool
    {
        $tokenCount = count($tokens);
        
        // Recherche le pattern: $container->getParameter('
        for ($i = 0; $i < $tokenCount - 2; $i++) {
            if (isset($tokens[$i], $tokens[$i + 1], $tokens[$i + 2]) &&
                $tokens[$i]['code'] === '$container' &&
                $tokens[$i + 1]['code'] === '->' &&
                $tokens[$i + 2]['code'] === 'getParameter') {
                return true;
            }
        }

        return false;
    }
}