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

class DumplingDownloadCommand extends AbstractDumplingCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('dumpling:download')
            ->setDescription('Downloads files from another Contao instance')
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

        $state = $this->getState();
        $files = $this->request('dumpling/files');

        foreach ($files as $file) {
            $exists = is_file($file->path);
            $filename = self::strFixSize($file->path);
            $unchanged = $exists && $file->hash === md5_file($file->path);
            if($exists && $unchanged) $output->writeln("<info>✓</info> $filename \t\t <info>no change</info>");
            else {
                @mkdir(dirname($file->path), 0777, true);
                $response = $this->request('dumpling/file', ['uuid' => $file->uuid], [
                    'sink' => TL_ROOT . '/' . $file->path,
                    GuzzleHttp\RequestOptions::EXPECT => false
                ]);
                if($response){
                    $output->writeln("<info>✓</info> $filename \t\t <comment>replaced</comment>");
                }
            }
        }

        $state->fileImportAt = date('Y-m-d H:i:s');
        $this->setState($state);

        return 0;
    }

}