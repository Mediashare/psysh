<?php

/*
 * This file is part of PsySH.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Analyze an exception and provide suggestions for fixing it.
 */
class ExplainCommand extends Command
{
    private static $explanations = [
        'BadMethodCallException' => [
            'description' => 'A method was called that does not exist or is not accessible.',
            'common_causes' => [
                'Typo in method name',
                'Method is private/protected and called from wrong context',
                'Object is not of expected type',
                'Method was removed in a newer version',
            ],
            'suggestions' => [
                'Check method name spelling',
                'Verify object type with instanceof',
                'Check method visibility',
                'Use method_exists() before calling',
            ],
        ],
        'ParseError' => [
            'description' => 'The PHP code contains syntax errors.',
            'common_causes' => [
                'Missing semicolon',
                'Unmatched brackets/parentheses',
                'Invalid syntax',
                'Reserved keyword used incorrectly',
            ],
            'suggestions' => [
                'Check for missing semicolons',
                'Verify all brackets are properly closed',
                'Use a code editor with syntax highlighting',
                'Check PHP version compatibility',
            ],
        ],
        'TypeError' => [
            'description' => 'A value of unexpected type was provided.',
            'common_causes' => [
                'Wrong argument type passed to function',
                'Null value where object expected',
                'Array where string expected',
                'Strict type checking enabled',
            ],
            'suggestions' => [
                'Check function/method signature',
                'Validate input types before use',
                'Use type casting when appropriate',
                'Check for null values',
            ],
        ],
        'InvalidArgumentException' => [
            'description' => 'An invalid argument was passed to a function or method.',
            'common_causes' => [
                'Parameter outside valid range',
                'Wrong format for expected value',
                'Required parameter is missing',
                'Invalid option value',
            ],
            'suggestions' => [
                'Check parameter documentation',
                'Validate input before passing',
                'Use appropriate validation functions',
                'Check for required parameters',
            ],
        ],
        'RuntimeException' => [
            'description' => 'A runtime error occurred during execution.',
            'common_causes' => [
                'Resource not available',
                'Configuration error',
                'External dependency failure',
                'Invalid state',
            ],
            'suggestions' => [
                'Check system resources',
                'Verify configuration settings',
                'Add error handling',
                'Check dependencies are available',
            ],
        ],
    ];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('explain')
            ->setDefinition([
                new InputArgument('exception', InputArgument::OPTIONAL, 'Exception variable name (e.g., $e) or class name.'),
                new InputOption('type', 't', InputOption::VALUE_REQUIRED, 'Exception type to explain.'),
                new InputOption('last', 'l', InputOption::VALUE_NONE, 'Explain the last exception.'),
                new InputOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed analysis.'),
            ])
            ->setDescription('Analyze an exception and provide suggestions for fixing it.')
            ->setHelp(
                <<<'HELP'
Analyze an exception and provide suggestions for fixing it.

This command provides detailed information about exceptions, their common causes,
and actionable suggestions for resolving them.

Examples:
<return>> explain $e</return>
<return>> explain --type=TypeError</return>
<return>> explain --last</return>
<return>> explain --detailed ParseError</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exception = $input->getArgument('exception');
        $type = $input->getOption('type');
        $last = $input->getOption('last');
        $detailed = $input->getOption('detailed');

        $exceptionInfo = null;

        if ($last) {
            $exceptionInfo = $this->getLastException();
        } elseif ($type) {
            $exceptionInfo = $this->getExceptionByType($type);
        } elseif ($exception) {
            $exceptionInfo = $this->getExceptionFromVariable($exception);
        } else {
            $output->writeln('<error>You must specify an exception to explain.</error>');
            $output->writeln('Use: explain $exception, --type=ExceptionType, or --last');
            return 1;
        }

        if (!$exceptionInfo) {
            $output->writeln('<error>Exception not found or not available.</error>');
            return 1;
        }

        $this->displayExplanation($exceptionInfo, $detailed, $output);

        return 0;
    }

    /**
     * Get the last exception from the shell context.
     */
    private function getLastException(): ?array
    {
        $shell = $this->getApplication();
        
        if (method_exists($shell, 'getContext')) {
            $context = $shell->getContext();
            $lastException = $context->getLastException();
            
            if ($lastException) {
                return $this->analyzeException($lastException);
            }
        }

        return null;
    }

    /**
     * Get exception information by type.
     */
    private function getExceptionByType(string $type): ?array
    {
        // Normalize the exception type
        if (!str_contains($type, 'Exception') && !str_contains($type, 'Error')) {
            $type .= 'Exception';
        }

        return [
            'type' => $type,
            'message' => 'Generic ' . $type,
            'file' => 'N/A',
            'line' => 'N/A',
            'trace' => [],
            'analysis' => self::$explanations[$type] ?? $this->getGenericExplanation($type),
        ];
    }

    /**
     * Get exception from a variable.
     */
    private function getExceptionFromVariable(string $varName): ?array
    {
        $shell = $this->getApplication();
        
        if (method_exists($shell, 'getScopeVariable')) {
            try {
                $variable = $shell->getScopeVariable(ltrim($varName, '$'));
                
                if ($variable instanceof \Throwable) {
                    return $this->analyzeException($variable);
                }
            } catch (\Exception $e) {
                // Variable not found
            }
        }

        return null;
    }

    /**
     * Analyze an exception object.
     */
    private function analyzeException(\Throwable $exception): array
    {
        $type = get_class($exception);
        $shortType = substr($type, strrpos($type, '\\') + 1);

        return [
            'type' => $type,
            'short_type' => $shortType,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
            'previous' => $exception->getPrevious(),
            'analysis' => self::$explanations[$shortType] ?? $this->getGenericExplanation($shortType),
        ];
    }

    /**
     * Get generic explanation for unknown exception types.
     */
    private function getGenericExplanation(string $type): array
    {
        return [
            'description' => 'An exception of type ' . $type . ' occurred.',
            'common_causes' => [
                'Application logic error',
                'Invalid input data',
                'Resource unavailability',
                'Configuration issue',
            ],
            'suggestions' => [
                'Check the exception message for details',
                'Review the stack trace',
                'Validate input data',
                'Add appropriate error handling',
                'Check documentation for the failing component',
            ],
        ];
    }

    /**
     * Display the exception explanation.
     */
    private function displayExplanation(array $exceptionInfo, bool $detailed, OutputInterface $output)
    {
        $output->writeln('<info>Exception Analysis</info>');
        $output->writeln('==================');
        $output->writeln('');

        // Basic information
        $output->writeln(sprintf('<comment>Type:</comment> %s', $exceptionInfo['short_type'] ?? $exceptionInfo['type']));
        $output->writeln(sprintf('<comment>Message:</comment> %s', $exceptionInfo['message']));
        
        if (isset($exceptionInfo['file']) && $exceptionInfo['file'] !== 'N/A') {
            $output->writeln(sprintf('<comment>Location:</comment> %s:%s', $exceptionInfo['file'], $exceptionInfo['line']));
        }

        $output->writeln('');

        // Analysis
        $analysis = $exceptionInfo['analysis'];
        
        $output->writeln('<info>Description:</info>');
        $output->writeln($analysis['description']);
        $output->writeln('');

        $output->writeln('<info>Common Causes:</info>');
        foreach ($analysis['common_causes'] as $i => $cause) {
            $output->writeln(sprintf('%d. %s', $i + 1, $cause));
        }
        $output->writeln('');

        $output->writeln('<info>Suggestions:</info>');
        foreach ($analysis['suggestions'] as $i => $suggestion) {
            $output->writeln(sprintf('%d. %s', $i + 1, $suggestion));
        }
        $output->writeln('');

        // Detailed information
        if ($detailed) {
            $this->displayDetailedAnalysis($exceptionInfo, $output);
        }

        // Previous exception
        if (isset($exceptionInfo['previous']) && $exceptionInfo['previous']) {
            $output->writeln('<comment>Previous Exception:</comment>');
            $previousInfo = $this->analyzeException($exceptionInfo['previous']);
            $output->writeln(sprintf('  Type: %s', $previousInfo['short_type']));
            $output->writeln(sprintf('  Message: %s', $previousInfo['message']));
            $output->writeln('');
        }

        // Quick fixes based on message content
        $this->suggestQuickFixes($exceptionInfo, $output);
    }

    /**
     * Display detailed analysis.
     */
    private function displayDetailedAnalysis(array $exceptionInfo, OutputInterface $output)
    {
        $output->writeln('<info>Detailed Analysis:</info>');

        // Stack trace analysis
        if (!empty($exceptionInfo['trace'])) {
            $output->writeln('<comment>Stack Trace Analysis:</comment>');
            
            $userFrames = 0;
            $vendorFrames = 0;
            
            foreach ($exceptionInfo['trace'] as $frame) {
                if (isset($frame['file'])) {
                    if (strpos($frame['file'], 'vendor/') !== false) {
                        $vendorFrames++;
                    } else {
                        $userFrames++;
                    }
                }
            }
            
            $output->writeln(sprintf('  User code frames: %d', $userFrames));
            $output->writeln(sprintf('  Vendor code frames: %d', $vendorFrames));
            
            // Show top user frames
            $output->writeln('  Recent user code calls:');
            $count = 0;
            foreach ($exceptionInfo['trace'] as $frame) {
                if (isset($frame['file']) && strpos($frame['file'], 'vendor/') === false && $count < 3) {
                    $output->writeln(sprintf('    %s:%d in %s()', 
                        basename($frame['file']), 
                        $frame['line'] ?? '?', 
                        $frame['function'] ?? 'unknown'
                    ));
                    $count++;
                }
            }
        }

        $output->writeln('');
    }

    /**
     * Suggest quick fixes based on exception message.
     */
    private function suggestQuickFixes(array $exceptionInfo, OutputInterface $output)
    {
        $message = strtolower($exceptionInfo['message']);
        $fixes = [];

        // Common patterns and their fixes
        if (strpos($message, 'undefined') !== false && strpos($message, 'method') !== false) {
            $fixes[] = 'Check method name spelling and object type';
        }
        
        if (strpos($message, 'cannot access') !== false) {
            $fixes[] = 'Check property/method visibility (private/protected)';
        }
        
        if (strpos($message, 'null') !== false) {
            $fixes[] = 'Add null checks before using the value';
        }
        
        if (strpos($message, 'array') !== false && strpos($message, 'string') !== false) {
            $fixes[] = 'Use array_values(), implode(), or serialize() to convert array';
        }
        
        if (strpos($message, 'file') !== false && strpos($message, 'not found') !== false) {
            $fixes[] = 'Check file path and ensure file exists';
        }

        if (!empty($fixes)) {
            $output->writeln('<info>Quick Fixes:</info>');
            foreach ($fixes as $i => $fix) {
                $output->writeln(sprintf('💡 %s', $fix));
            }
            $output->writeln('');
        }

        // Related documentation links
        $type = $exceptionInfo['short_type'] ?? $exceptionInfo['type'];
        $output->writeln('<info>Related Documentation:</info>');
        $output->writeln(sprintf('📚 PHP Manual: https://php.net/manual/en/class.%s.php', strtolower($type)));
        $output->writeln('📚 Stack Overflow: Search for "PHP ' . $type . '"');
    }
}
