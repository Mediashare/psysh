<?php

namespace Psy\Profiling;

use Psy\Shell;

class ContextBuilder
{
    public static function buildContextScript(Shell $shell): string
    {
        $contextLines = [];

        $autoloader = realpath(__DIR__ . '/../../vendor/autoload.php');
        if ($autoloader) {
            $contextLines[] = sprintf('require_once %s;', var_export($autoloader, true));
        }

        // Capture all executed code (classes, functions, etc.)
        $userCode = method_exists($shell, 'getExecutedCodeAsString') ? $shell->getExecutedCodeAsString() : '';
        if (!empty($userCode)) {
            $contextLines[] = $userCode;
        }

        // Capture scope variables
        self::captureScopeVariables($shell, $contextLines);

        self::captureShellConstants($contextLines);
        self::captureEnvironmentVariables($contextLines);

        return implode(PHP_EOL, $contextLines);
    }

    /**
     * Capture scope variables from the shell.
     *
     * Note: Objects are NOT serialized here because they should already be
     * created by the executed code. We only serialize scalar values.
     */
    private static function captureScopeVariables(Shell $shell, array &$context): void
    {
        if (!method_exists($shell, 'getScopeVariables')) {
            return;
        }

        $scopeVars = $shell->getScopeVariables(false);
        foreach ($scopeVars as $name => $value) {
            // Skip internal variables
            if (strpos($name, '__psysh') === 0 || $name === 'this' || $name === '_') {
                continue;
            }

            // Only serialize scalar types and arrays (NOT objects)
            // Objects should be recreated from the executed code
            if (self::isSerializable($value)) {
                try {
                    $context[] = sprintf('$%s = %s;', $name, var_export($value, true));
                } catch (\Exception $e) {
                    $context[] = sprintf("// Variable \$%s could not be serialized: %s", $name, $e->getMessage());
                }
            }
        }
    }

    private static function captureEnvironmentVariables(array &$context): void
    {
        $importantEnvVars = [
            'APP_ENV', 'APP_DEBUG', 'APP_KEY', 'APP_URL',
            'DATABASE_URL', 'DATABASE_HOST', 'DATABASE_NAME',
            'SYMFONY_ENV', 'KERNEL_CLASS',
            'WP_ENV', 'WP_HOME', 'WP_SITEURL',
            'COMPOSER_HOME', 'COMPOSER_CACHE_DIR',
        ];

        foreach ($importantEnvVars as $envVar) {
            $value = getenv($envVar);
            if ($value !== false && is_string($value)) {
                try {
                    $context[] = sprintf("putenv(%s);", var_export("$envVar=$value", true));
                    $context[] = sprintf("\$_ENV[%s] = %s;", var_export($envVar, true), var_export($value, true));
                    $context[] = sprintf("\$_SERVER[%s] = %s;", var_export($envVar, true), var_export($value, true));
                } catch (\Exception $e) {
                    $context[] = sprintf("// Environment variable %s could not be serialized: %s", $envVar, $e->getMessage());
                }
            }
        }
    }

    private static function captureShellConstants(array &$context): void
    {
        $userConstants = get_defined_constants(true)['user'] ?? [];

        foreach ($userConstants as $name => $value) {
            if (self::isSerializable($value)) {
                try {
                    $context[] = sprintf("if (!defined(%s)) define(%s, %s);",
                        var_export($name, true),
                        var_export($name, true),
                        var_export($value, true)
                    );
                } catch (\Exception $e) {
                    $context[] = sprintf("// Constant %s could not be serialized: %s", $name, $e->getMessage());
                }
            }
        }
    }

    private static function isSerializable($value): bool
    {
        if (is_scalar($value) || is_null($value)) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::isSerializable($item)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

}
