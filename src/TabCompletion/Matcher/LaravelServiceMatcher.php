<?php

namespace Psy\TabCompletion\Matcher;

/**
 * Matcher pour Laravel (services via App::make)
 */
class LaravelServiceMatcher extends \Psy\TabCompletion\Matcher\AbstractMatcher
{
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        $input = $this->getInput($tokens);
        
        if ($this->isAppMakeContext($tokens)) {
            // Liste basique des services Laravel courants
            $services = [
                'auth', 'cache', 'config', 'db', 'events', 'files', 'hash',
                'log', 'mail', 'queue', 'redis', 'request', 'router', 'session',
                'url', 'validator', 'view'
            ];
            
            return array_filter($services, function($service) use ($input) {
                return stripos($service, $input) === 0;
            });
        }

        return [];
    }

    public function hasMatched(array $tokens): bool
    {
        return $this->isAppMakeContext($tokens);
    }

    private function isAppMakeContext(array $tokens): bool
    {
        $tokenCount = count($tokens);
        
        for ($i = 0; $i < $tokenCount - 2; $i++) {
            if (isset($tokens[$i], $tokens[$i + 1], $tokens[$i + 2]) &&
                $tokens[$i]['code'] === '$app' &&
                $tokens[$i + 1]['code'] === '->' &&
                $tokens[$i + 2]['code'] === 'make') {
                return true;
            }
        }

        return false;
    }
}