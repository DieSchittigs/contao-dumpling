<?php

namespace DieSchittigs\ContaoDumplingBundle\Controller;

use Contao\Config;
use Contao\System;
use Contao\FilesModel;
use Contao\Database;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Ifsnop\Mysqldump\Mysqldump;

/**
 * @Route("/dumpling", defaults={"_scope" = "frontend", "_token_check" = false})
 */
class DumplingController extends Controller {

    private $sqlDumpFile = __DIR__ . '/../../tmp/contao-dumpling-bundle-{table}.sql.gz';
    private $tableBlacklist = [
        'tl_user',
        'tl_cron',
        'tl_log',
        'tl_member',
        'tl_member_group',
        'tl_opt_in',
        'tl_opt_in_related',
        'tl_remember_me',
        'tl_search',
        'tl_search_index',
        'tl_undo',
        'tl_user',
        'tl_session',
        'tl_user_group',
        'tl_version',
        'tl_newsletter_recipients',
        'tl_newsletter_recipients_backup',
        'tl_newsletter_blacklist',
        'tl_comments_notify',
    ];

    private function checkApiKey($apiKey){
        return !!Config::get('dump_api_key') && $apiKey === Config::get('dump_api_key');
    }


    /**
     * @return Response
     *
     * @Route("/tables", name="dumpling_tables", methods={"POST"})
     */
    public function tables(Request $request) {
        $payload = json_decode($request->getContent());
        if(!$this->checkApiKey($payload->api_key))
            return $this->json(['error' => 'api key incorrect'], 401);
        
        $db = Database::getInstance();
        $result = $db->execute("show tables");
        $tables = [];

        while($row = $result->fetchRow()){
            $table = $row[0];
            if(in_array($table, $this->tableBlacklist)) continue;
            $tables[] = $table;
        }

        return $this->json($tables);
    }

        /**
     * @return Response
     *
     * @Route("/table", name="dumpling_table", methods={"POST"})
     */
    public function table(Request $request) {
        $payload = json_decode($request->getContent());
        if(!$this->checkApiKey($payload->api_key))
            return $this->json(['error' => 'api key incorrect'], 401);
            
        $table = $payload->table;
        if(in_array($this->tableBlacklist, $table))
            return $this->json(['error' => 'table is blacklisted'], 401);
        
        $container = System::getContainer();
        $parameters = [
            'database_host' => $container->getParameter('database_host'),
            'database_port' => $container->getParameter('database_port'),
            'database_user' => $container->getParameter('database_user'),
            'database_password' => $container->getParameter('database_password'),
            'database_name' => $container->getParameter('database_name'),
        ];
        $dumper = new Mysqldump(
            "mysql:host=$parameters[database_host]:".
            "$parameters[database_port];".
            "dbname=$parameters[database_name]",
            $parameters['database_user'],
            $parameters['database_password'],
            [
                'include-tables' => [$table],
                'add-drop-table' => true,
                'lock-tables' => false,
                'add-locks' => false,
                'single-transaction' => true,
                'compress' => Mysqldump::GZIP,
            ]
        );
        $_tables = [];
        $dumper->setTransformColumnValueHook(function ($tableName, $colName, $colValue) use (&$_tables) {
            $_tables[$tableName] = 1;
            return $colValue;
        });

        $target = str_replace('{table}', $table, $this->sqlDumpFile);
        
        $dumper->start($target);
        
        return $this->file($target);
    }

    /**
     * @return Response
     *
     * @Route("/files", name="dumpling_files", methods={"POST"})
     */
    public function files(Request $request) {
        $payload = json_decode($request->getContent());
        if(!$this->checkApiKey($payload->api_key))
            return $this->json(['error' => 'api key incorrect'], 401);

        $files = [];
        foreach (FilesModel::findBy('type', 'file') as $file) {
            $files[] = [
                'uuid' => bin2hex($file->uuid),
                'path' => $file->path,
                'hash' => $file->hash
            ];
        }
        return $this->json($files);
    }

    /**
     * @return Response
     *
     * @Route("/file", name="dumpling_file", methods={"POST"})
     */
    public function singleFile(Request $request) {
        $payload = json_decode($request->getContent());
        if(!$this->checkApiKey($payload->api_key))
            return $this->json(['error' => 'api key incorrect'], 401);

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');
        $file = FilesModel::findByUuid(hex2bin($payload->uuid));
        if($file) return $this->file($rootDir . '/' . $file->path);
        else $this->json(['error' => 'file not found'], 404); 
    }

    /**
     * @return Response
     *
     * @Route("/execsql", name="dumpling_execsql", methods={"POST"})
     */
    public function execSql(Request $request) {
        $payload = json_decode($request->getContent());
        if(!$this->checkApiKey($payload->api_key))
            return $this->json(['error' => 'api key incorrect'], 401);

        $sql = $payload->sql;

        $db = Database::getInstance();
        $result = $db->execute($sql);
        return $this->json($result);
    }

        /**
     * @return Response
     *
     * @Route("/upload", name="dumpling_upload", methods={"POST"})
     */
    public function upload(Request $request) {
        $payload = json_decode($request->getContent());
        if(!$this->checkApiKey($payload->api_key))
            return $this->json(['error' => 'api key incorrect'], 401);

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');
        $file = $request->files->get('file');
        $file->getClientOriginalName ();
        $file->move($rootDir, $payload->file_path);
        return $this->json(['']);
    }

}