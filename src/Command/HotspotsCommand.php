<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Identify code hotspots by profiling a string of PHP code.
 */
class HotspotsCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('hotspots')
            ->setDefinition([
                new InputArgument('code', InputArgument::REQUIRED, 'The code to identify hotspots in.'),
            ])
            ->setDescription('Identify code hotspots by profiling a string of PHP code.')
            ->setHelp(
                <<<'HELP'
This command is an alias for the `profile` command, specifically designed to highlight
the most time-consuming functions (hotspots) in a given PHP code snippet.

e.g.
<return>hotspots for ($i = 0; $i < 1000; $i++) { md5('hello'); }</return>
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getArgument('code');

        // Create an ArrayInput for the profile command
        $profileInput = new ArrayInput([
            'command' => 'profile',
            'code'    => $code,
        ]);

        // Create a BufferedOutput to capture the profile command's output
        $bufferedOutput = new BufferedOutput();

        // Find and run the profile command
        $profileCommand = $this->getApplication()->find('profile');
        $exitCode = $profileCommand->run($profileInput, $bufferedOutput);

        // Display the captured output
        $output->write($bufferedOutput->fetch());

        return $exitCode;
    }
}
