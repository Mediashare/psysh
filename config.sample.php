<?php

/**
 * Sample PsySH configuration file with async features.
 *
 * Copy this file to ~/.config/psysh/config.php or wherever your PsySH
 * configuration is located.
 *
 * For more information, see:
 * - Configuration docs: https://github.com/bobthecow/psysh/wiki/Configuration
 * - Async features: ASYNC.md
 */

return [
    // Enable async metrics tracking (default: false)
    // When enabled, execution time and memory usage are tracked in real-time
    'useAsyncMetrics' => true,

    // Enable status bar (default: false)
    // Shows real-time metrics at the bottom of the terminal during execution
    'useStatusBar' => true,

    // Other standard configuration options
    
    // Colorize output (auto, forced, or disabled)
    'colorMode' => \Psy\Configuration::COLOR_MODE_AUTO,
    
    // Enable tab completion (requires readline)
    'useTabCompletion' => true,
    
    // Use Unicode characters in output
    'useUnicode' => true,
    
    // History file location
    // 'historyFile' => '~/.config/psysh/history',
    
    // Maximum history entries
    'historySize' => 10000,
    
    // Erase duplicate history entries
    'eraseDuplicates' => true,
    
    // Error logging level
    'errorLoggingLevel' => E_ALL,
    
    // Require semicolons at the end of statements
    'requireSemicolons' => false,
    
    // Use strict types (declare(strict_types=1))
    'strictTypes' => false,
    
    // Check for updates
    'updateCheck' => 'always', // Options: 'always', 'daily', 'weekly', 'monthly', 'never'
    
    // Custom startup message
    // 'startupMessage' => 'Welcome to my custom PsySH!',
    
    // Files to include on startup
    // 'defaultIncludes' => [
    //     '/path/to/helpers.php',
    //     '/path/to/bootstrap.php',
    // ],
    
    // Output pager (null, 'less', or false to disable)
    // 'pager' => 'less',
    
    // Theme configuration
    // 'theme' => 'modern', // Options: 'modern', 'classic', or custom theme
];
