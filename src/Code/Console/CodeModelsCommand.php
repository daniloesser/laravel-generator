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

        $folder = $this->ask('Please inform the Model folder:', 'Models');
        $folder = ($folder=="Models")?"": ucfirst(strtolower(trim($folder)));

        $table = $this->ask('Please inform the Table name:');

        // Check whether we just need to generate one table
        if (is_null($table)) {
            $this->error("Please inform a table name!");
            exit();
        }

        try {
            $this->models->on($connection)->create($schema, $table, $folder);
            $this->info("Creating a model for table \"{$table}\" ...");

            $createFactory = $this->ask('Would you like to create the Factory file too?', 'Yes');
            if(strtolower(trim($createFactory))=="yes"){
                $command = 'php artisan migrate:generate '. $table;
                $process = Process::fromShellCommandline($command);

                $this->info("Creating the migration for model \"{$table}\" ...");

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
