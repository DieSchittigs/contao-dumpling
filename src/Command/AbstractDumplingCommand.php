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


abstract class AbstractDumplingCommand extends Command
{
    /**
     * @var ContaoFramework
     */
    protected $framework;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var String
     */
    protected $sourceUrl;

    /**
     * @var String
     */
    protected $apiKey;

    /**
     * @var GuzzleHttp\Client
     */
    protected $client;

    protected $dbParameters;

    public function __construct(ContaoFramework $framework, Connection $connection)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->client = new GuzzleHttp\Client();
        $framework->initialize();

        $container = System::getContainer();
        $this->dbParameters = [
            'database_host' => $container->getParameter('database_host'),
            'database_port' => $container->getParameter('database_port'),
            'database_user' => $container->getParameter('database_user'),
            'database_password' => $container->getParameter('database_password'),
            'database_name' => $container->getParameter('database_name'),
        ];
        
        parent::__construct();
    }

    protected static function json_encode($o){
        return json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    protected static function strFixSize($s, $size = 50){
        while(mb_strlen($s) < $size) $s .= ' ';
        if(mb_strlen($s) > $size) $s = '…' . substr($s, -($size-1), $size-1);
        return $s;
    }

    protected function progressBar(OutputInterface $output, $max): ProgressBar{
        ProgressBar::setFormatDefinition('custom', '<info>[%bar%] %current%/%max%</> – %message%');
        $progressBar = new ProgressBar($output, $max);
        $progressBar->setFormat('custom');
        return $progressBar;
    }

    protected function getSettings(){
        $settingsFile = TL_ROOT . '/.dumpling-settings.json';
        if(is_file($settingsFile)){
            $settings = json_decode(file_get_contents($settingsFile));
            $this->sourceUrl = $settings->sourceUrl ?? null;
            $this->apiKey = $settings->apiKey ?? null;
        }
        if(!$this->sourceUrl){
            $questionHelper = $this->getHelper('question');
            $question = new Question('Please enter the URL of the source system: ', '');
            $this->sourceUrl = $questionHelper->ask($input, $output, $question);
        }
        if (!$this->sourceUrl) {
            throw new InvalidArgumentException('Please provide a valid url!');
        }
        
        if (!$this->apiKey) {
            $question = new Question('Please enter the Dumpling Api Key of the source system: ', '');
            $this->apiKey = $questionHelper->ask($input, $output, $question);
        }
        if (!$this->apiKey) {
            throw new InvalidArgumentException('Please provide an api key!');
        }

        file_put_contents(
            $settingsFile,
            self::json_encode([ 'sourceUrl' => $this->sourceUrl, 'apiKey' => $this->apiKey ])
        );
    }

    protected function getState(){
        $stateFile = TL_ROOT . '/var/dumpling/state.json';
        if(is_file($stateFile)) return json_decode(file_get_contents($stateFile));
        return new \stdClass;
    }

    protected function setState($state): void{
        @mkdir(TL_ROOT . '/var/dumpling', 0777, true);
        $stateFile = TL_ROOT . '/var/dumpling/state.json';
        file_put_contents(
            $stateFile,
            self::json_encode($state)
        );
    }

    protected function request($path, $post = [], $opts = []){
        $_opts = [
            GuzzleHttp\RequestOptions::JSON => array_merge(['api_key' => $this->apiKey], $post)
        ];
        $response = $this->client->post("$this->sourceUrl/$path", array_merge($_opts, $opts));
        if(in_array(substr((string) $response->getBody(), 0, 1), ['[', '{'])) return json_decode((string) $response->getBody());
        else (string) $response->getBody();
    }

    /**
     * {@inheritdoc}
     */
    
    public function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->getSettings();
    }
    
}