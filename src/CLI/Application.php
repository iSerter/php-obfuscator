<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\CLI;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;

final class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('php-obfuscator', '1.0.0');

        $this->add(new ObfuscateCommand());
    }

    /**
     * Set the name of the command to run as default.
     */
    protected function getCommandName(InputInterface $input): string
    {
        return 'obfuscate';
    }

    /**
     * Overridden to allow for a single-command application.
     */
    public function getDefinition(): \Symfony\Component\Console\Input\InputDefinition
    {
        $inputDefinition = parent::getDefinition();
        // remove the first argument, which is the command name
        $inputDefinition->setArguments();

        return $inputDefinition;
    }
}
