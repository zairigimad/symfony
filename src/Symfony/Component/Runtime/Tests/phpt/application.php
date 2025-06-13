<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__.'/autoload.php';

return function (array $context) {
    $command = new Command('go');
    $command->setCode(function (InputInterface $input, OutputInterface $output) use ($context): int {
        $output->write('OK Application '.$context['SOME_VAR']);

        return 0;
    });

    $app = new Application();
    $app->addCommand($command);
    $app->setDefaultCommand('go', true);

    return $app;
};
