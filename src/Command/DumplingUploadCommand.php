<?php

declare(strict_types=1);

namespace DieSchittigs\ContaoDumplingBundle\Command;

use Contao\System;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Contao\FilesModel;
use GuzzleHttp;
use Ifsnop\Mysqldump\Mysqldump;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Changes the password of a Contao back end user.
 */
class DumplingUploadCommand extends AbstractDumplingCommand
{

    private $output;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('dumpling:upload')
            ->setDescription('Upload new local files to the target Contao instance')
        ;
    }

    private function filesDelta(): array {
        $files = FilesModel::findBy(['type = ?', 'id >= ?'], ['file', $this->state->tables->tl_files]);
        if(!$files) return [];
        $_files = [];
        foreach ($files as $file) {
            $_files[] = [
                'uuid' => bin2hex($file->uuid),
                'path' => $file->path,
                'hash' => $file->hash
            ];
        }
        return $_files;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->sourceUrl || !$this->apiKey) {
            return 1;
        }

        $this->output = $output;

        $this->state = $this->getState();
        $files = $this->filesDelta();
        if(!$files){
            $this->output->writeLn("<error>No new files found</>");
            return 0;
        }
        $progressBar = $this->progressBar($output, count($files));
        $errors = [];

        $this->output->writeLn("\n\n<comment>Uploading new files</>");
        foreach ($files as $i => $file) {
            $progressBar->advance();
            $progressBar->setMessage("Upload " . self::strFixSize($file['path'], 50));
            try{
                $this->request('upload', [], [
                    'multipart' => [
                        [
                            'name'     => 'body',
                            'contents' => json_encode(['file_path' => $file['path']]),
                            'headers'  => ['Content-Type' => 'application/json']
                        ],
                        [
                            'name'     => 'file',
                            'contents' => fopen(TL_ROOT."/$file[path]", 'r'),
                            //'headers'  => ['Content-Type' => MimeTypeGuesser::getInstance()->guess(TL_ROOT."/$file->path")]
                        ],
                    ],
                ]);
            } catch (\Exception $e){
                $errors[] = $e->getMessage();
            }
        }
        $progressBar->finish();
        foreach($errors as $error){
            $this->output->writeLn("<error>$error</>");
        }
        return 0;
    }

}