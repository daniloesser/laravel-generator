<?php

namespace CliGenerator\Code\Console;

use Illuminate\Console\Command;
use CliGenerator\Code\Model\Factory;
use Illuminate\Contracts\Config\Repository;
use Symfony\Component\Process\Process;

class CodeModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'code:generate
                            {--s|schema= : The name of the MySQL database}
                            {--c|connection= : The name of the connection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate code from Table';

    /**
     * @var \CliGenerator\Code\Model\Factory
     */
    protected $models;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * Create a new command instance.
     *
     * @param \CliGenerator\Code\Model\Factory $models
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(Factory $models, Repository $config)
    {
        parent::__construct();

        $this->models = $models;
        $this->config = $config;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->getConnection();
        $schema = $this->getSchema($connection);

        $existingTablesList = $this->models->on($connection)->makeSchema($schema)->tables();
        $this->warn("Current tables list:");

        $i=0;
        foreach ($existingTablesList as $existingTable){
            echo str_pad( $existingTable->table(), 40 );
            $i++;
            if($i==4){
                echo "\n";
                $i = 0;
            }
        }

        $tablesList = $this->ask('Please inform Table(s) name separated by comma');

        // Check whether we just need to generate one table
        if (is_null($tablesList)) {
            $this->error("Please inform at least one table!");
            exit();
        }

        $askFolder = strtolower(trim($this->ask('Would you like to store the generated files in a Model sub-folder?','No')));
        $folder="";
        if($askFolder!="no" && $askFolder!="n"){
            $folder = $this->ask('Model sub-folder name');
        }
        $folder = ucfirst(strtolower(trim($folder)));

        try {
            $selectedTables = explode(',',strtolower(trim($tablesList)));

            foreach ($selectedTables as $table){
            $this->models->on($connection)->create($schema, $table, $folder);
            $this->info("Creating a model for table \"{$table}\" ...");
            }

            $createFactory = $this->ask('Would you like to create the Migration files too?', 'Yes');
            if(strtolower(trim($createFactory))=="yes" || strtolower(trim($createFactory))=="y"){
                $command = 'php artisan migrate:generate '. implode(',',$selectedTables);
                $process = Process::fromShellCommandline($command);

                $this->info("Creating migration files for the following tables \"{$tablesList}\" ...");

                $process->setTty(true);

                $process->run(function ($type, $buffer) {
                    $this->info($buffer);
                });
                $this->comment('Finished!');
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * @return string
     */
    protected function getConnection()
    {
        return $this->option('connection') ?: $this->config->get('database.default');
    }

    /**
     * @param $connection
     *
     * @return string
     */
    protected function getSchema($connection)
    {
        return $this->option('schema') ?: $this->config->get("database.connections.$connection.database");
    }
}
