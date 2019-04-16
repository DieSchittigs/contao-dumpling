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

class DumplingPushCommand extends AbstractDumplingCommand
{

    private $output;
    private $today;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('dumpling:push')
            ->setDescription('Push your local database delta to the target Contao instance')
        ;

        $this->today = date('Y-m-d');
        @mkdir(TL_ROOT . '/var/dumpling/temp', 0777, true);
    }

    private function tableDelta(string $table, int $aiId): string{
        $target = TL_ROOT."/var/dumpling/temp/$table-delta-{$this->today}_". date('His') .".sql";
        $dumper = new Mysqldump(
            "mysql:host={$this->dbParameters['database_host']}".
            ":{$this->dbParameters['database_port']}".
            ";dbname={$this->dbParameters['database_name']}",
            $this->dbParameters['database_user'],
            $this->dbParameters['database_password'],
            [
                'include-tables' => [$table],
                'add-drop-table' => false,
                'lock-tables' => false,
                'add-locks' => false,
                'single-transaction' => true,
                'no-create-info' => true
            ]
        );
        $dumper->setTableWheres([
            $table => 'id >= ' . $aiId
        ]);
        $dumper->start($target);
        $dumpSql = file_get_contents($target);
        @unlink($target);
        return $dumpSql;
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
        $errors = [];

        $progressBar = $this->progressBar($output, count((array) $this->state->tables));

        $errors = [];
        $this->output->writeLn("\n\n<comment>Pushing new rows</>");
        foreach ($this->state->tables as $table => $id) {
            $progressBar->advance();
            $progressBar->setMessage("Pushing table $table [id >= $id]");
            try{
                $this->request('execsql', [
                    'sql' => $this->tableDelta($table, intval($id))
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