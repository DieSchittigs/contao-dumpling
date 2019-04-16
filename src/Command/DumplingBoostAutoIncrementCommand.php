<?php

declare(strict_types=1);

namespace DieSchittigs\ContaoDumplingBundle\Command;

use Contao\System;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp;
use Ifsnop\Mysqldump\Mysqldump;

class DumplingBoostAutoIncrementCommand extends AbstractDumplingCommand
{

    private $output;
    private $today;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('dumpling:boostai')
            ->setDescription('Boost auto increment of pulled tables')
        ;
    }

    private function boostAutoIncrement(string $table, int $percentage = 25, int $offset = 32): int{
        $aiquery = $this->connection->executeQuery("SHOW TABLE STATUS LIKE '$table'");
        $status = $aiquery->fetch(\PDO::FETCH_ASSOC);
        $ai = intval($status['Auto_increment']);
        if($ai > 1){
            $ai = intval($offset + round($ai *= (1 + $percentage / 100)));
            $aiquery = $this->connection->executeQuery("ALTER TABLE `$table` AUTO_INCREMENT = $ai");
        }
        $this->output->writeLn(self::strFixSize($table, 20) . " <comment>bossted auto increment</comment>    $status[Auto_increment] -> <info>$ai</info>");
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

        foreach ($state->tables as $table => $ai) {
            $state->tables->{$table} = $this->boostAutoIncrement($table);
            $this->setState($state);
        }
        return 0;
    }

}