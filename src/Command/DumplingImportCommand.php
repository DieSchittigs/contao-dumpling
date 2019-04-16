<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace DieSchittigs\ContaoDumplingBundle\Command;

use Contao\Config;
use Contao\System;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Patchwork\Utf8;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use GuzzleHttp;
use Ifsnop\Mysqldump\Mysqldump;
use function GuzzleHttp\json_encode;
use function GuzzleHttp\json_decode;

class DumplingImportCommand extends AbstractDumplingCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('dumpling:import')
            ->setDescription('Imports database and files from another Contao instance')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->sourceUrl || !$this->apiKey) {
            return 1;
        }

        $command = $this->getApplication()->find('dumpling:download');
        $command->run($input, $output);
        $command = $this->getApplication()->find('dumpling:pull');
        $command->run($input, $output);
        $command = $this->getApplication()->find('dumpling:boostai');
        $command->run($input, $output);

        return 0;
    }

}