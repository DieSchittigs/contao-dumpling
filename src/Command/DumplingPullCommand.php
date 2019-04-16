<?php

declare(strict_types=1);

namespace DieSchittigs\ContaoDumplingBundle\Command;

use Contao\System;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp;
use Ifsnop\Mysqldump\Mysqldump;

class DumplingPullCommand extends AbstractDumplingCommand
{

    private $output;
    private $today;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('dumpling:pull')
            ->setDescription('Import tables from another Contao instance')
        ;

        $this->today = date('Y-m-d');
        @mkdir(TL_ROOT . '/var/dumpling/temp', 0777, true);
        @mkdir(TL_ROOT."/var/dumpling/backup/$this->today", 0777, true);
    }

    private function dumpTable(string $table): void{
        $target = TL_ROOT."/var/dumpling/backup/$this->today/$table-{$this->today}_". date('His') .".sql.gz";
        $this->output->writeLn("Creating backup of table <comment>$table</comment> -> <comment>{$target}</comment>");
        $dumper = new Mysqldump(
            "mysql:host={$this->dbParameters['database_host']}".
            ":{$this->dbParameters['database_port']}".
            ";dbname={$this->dbParameters['database_name']}",
            $this->dbParameters['database_user'],
            $this->dbParameters['database_password'],
            [
                'include-tables' => [$table],
                'add-drop-table' => true,
                'lock-tables' => false,
                'add-locks' => false,
                'single-transaction' => true,
                'compress' => Mysqldump::GZIP,
            ]
        );
        $dumper->start($target);
    }

    private function importTable(string $table): bool{
        $target = TL_ROOT."/var/dumpling/temp/$table.sql.gz";
        $this->request('dumpling/table', ['table' => $table], ['sink' => $target]);
        $this->output->writeLn("\nImporting table $table");
        try{
            exec(
                "zcat $target".
                " | mysql --silent --quick ".
                "--host={$this->dbParameters['database_host']} ".
                "--port={$this->dbParameters['database_port']} ".
                "--user={$this->dbParameters['database_user']} ".
                "--password={$this->dbParameters['database_password']} ".
                "{$this->dbParameters['database_name']} 2>/dev/null"
            );
        } catch (\Exception $e){
            $this->output->writeLn(" <error>✘ There was an Error:</error>");
            print_r($e);
            return false;
        }
        $this->output->writeLn("<info>✓ Import successfully executed</info>");
        @unlink($target);
        return true;
    }

    private function boostAutoIncrement(string $table): int{
        $aiquery = $this->connection->executeQuery("SHOW TABLE STATUS LIKE '$table'");
        $status = $aiquery->fetch(\PDO::FETCH_ASSOC);
        $ai = intval($status['Auto_increment']);
        if($ai > 1){
            $ai = intval(32 + round($ai *= 1.25));
            $aiquery = $this->connection->executeQuery("ALTER TABLE `$table` AUTO_INCREMENT = $ai");
        }
        $this->output->writeLn("Auto increment is now at $ai");
        return $ai;
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

        $state = $this->getState();
        $state->tables = new \stdClass;

        $tables = $this->request('dumpling/tables');

        foreach ($tables as $table) {
            $this->dumpTable($table);
            if(!$this->importTable($table)) continue;
            $state->tables->{$table} = $this->boostAutoIncrement($table);
            $this->setState($state);
        }
        return 0;
    }

}