<?php

namespace Psy\TabCompletion\Matcher;

/**
 * Matcher pour l'autocomplétion des services Symfony
 */
class SymfonyServiceMatcher extends \Psy\TabCompletion\Matcher\AbstractMatcher
{
    private $container;
    private $serviceIds;

    public function __construct($container)
    {
        $this->container = $container;
        
        // Récupère tous les IDs de services
        if (method_exists($container, 'getServiceIds')) {
            $this->serviceIds = $container->getServiceIds();
        } else {
            // Fallback pour les anciennes versions
            $this->serviceIds = [];
        }
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        $input = $this->getInput($tokens);
        
        // Vérifie si on est dans un contexte de $container->get()
        if ($this->isContainerGetContext($tokens)) {
            return array_filter($this->serviceIds, function($serviceId) use ($input) {
                return stripos($serviceId, $input) === 0;
            });
        }

        return [];
    }

    public function hasMatched(array $tokens): bool
    {
        return $this->isContainerGetContext($tokens);
    }

    private function isContainerGetContext(array $tokens): bool
    {
        $tokenCount = count($tokens);
        
        // Recherche le pattern: $container->get('
        for ($i = 0; $i < $tokenCount - 2; $i++) {
            if (isset($tokens[$i], $tokens[$i + 1], $tokens[$i + 2]) &&
                $tokens[$i]['code'] === '$container' &&
                $tokens[$i + 1]['code'] === '->' &&
                $tokens[$i + 2]['code'] === 'get') {
                return true;
            }
        }

        return false;
    }
}