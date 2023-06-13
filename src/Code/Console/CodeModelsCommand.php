<?php

namespace CliGenerator\Code\Console;

use CliGenerator\Types\EnumType;
use Doctrine\DBAL\Types\Type;
use Illuminate\Console\Command;
use CliGenerator\Code\Model\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\OutputInterface;
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
    protected $description = 'Generate code from Database Tables';

    /**
     * @var \CliGenerator\Code\Model\Factory
     */
    protected $models;

    protected $modelsList = [];

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * @var Filesystem $files
     */
    protected $files;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'code:generate';

    /**
     * @var string
     */
    protected $dir = 'app';

    /** @var \Illuminate\Contracts\View\Factory */
    protected $view;

    /**
     * @var string
     */
    protected $existingFactories = '';

    /**
     * @var array
     */
    protected $properties = array();

    /**
     * @var
     */
    protected $force;

    /**
     * Create a new command instance.
     *
     * @param \CliGenerator\Code\Model\Factory $models
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(Factory $models, Repository $config, Filesystem $files)
    {
        parent::__construct();

        $this->models = $models;
        $this->config = $config;
        $this->files = $files;
        $this->view = $models->getView();
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
            $created = null;
            $selectedTables = explode(',', strtolower(trim($tablesList)));

            foreach ($selectedTables as $table) {
                $created = $this->models->on($connection)->create($schema, $table, $folder);
                $this->addCreatedModelToList($created);
                $this->info("Creating a model for table \"{$table}\" ...");
            }

            $createFactory = $this->ask('Would you like to create the Migration files too?', 'Yes');
            if(strtolower(trim($createFactory))=="yes" || strtolower(trim($createFactory))=="y"){
                $command = 'php artisan migrate:generate ' . implode(',', $selectedTables);
                $process = Process::fromShellCommandline($command);

                $this->info("Creating migration files for the following tables \"{$tablesList}\" ...");

                $process->setTty(true);

                $process->run(
                    function ($type, $buffer) {
                        $this->info($buffer);
                    }
                );
                $this->comment('Finished Migrations creation!');
            }

            $createSeeder = $this->ask('Would you like to create the Factories files too?', 'Yes');
            if (strtolower(trim($createSeeder)) == "yes" || strtolower(trim($createSeeder)) == "y") {
                $config = $this->models->on($connection)->config(null);
                $namespace = str_replace("\\", "/", $config->getByKey('namespace'));
                $customDir = $folder ? $namespace . "/{$folder}" : $namespace;
                $this->info(
                    "Creating factory files for the following models " . implode(', ', $this->getModelsList()) . " ..."
                );

                Type::addType('customEnum', EnumType::class);

                $this->dir = $customDir;
                $this->force = true;

                $models = $this->loadModels($this->getModelsList());

                foreach ($models as $model) {
                    $filename = 'database/factories/' . class_basename($model) . 'Factory.php';

                    $result = $this->generateFactory($model);

                    if ($result === false) {
                        continue;
                    }

                    $written = $this->files->put($filename, $result);
                    if ($written !== false) {
                        $this->line('<info>Model factory created:</info> ' . $filename);
                    } else {
                        $this->line('<error>Failed to create model factory:</error> ' . $filename);
                    }
                }

                $this->comment('Finished Factories creation!');
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

    /**
     * @return array
     */
    public function getModelsList(): array
    {
        return $this->modelsList;
    }

    public function addCreatedModelToList($model): void
    {
        $this->modelsList[] = $model;
    }

    protected function generateFactory($model)
    {
        $output = '<?php' . "\n\n";

        $this->properties = [];
        if (!class_exists($model)) {
            if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->error("Unable to find '$model' class");
            }
            return false;
        }

        try {
            // handle abstract classes, interfaces, ...
            $reflectionClass = new \ReflectionClass($model);

            if (!$reflectionClass->isSubclassOf('Illuminate\Database\Eloquent\Model')) {
                return false;
            }

            if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $this->comment("Loading model '$model'");
            }

            if (!$reflectionClass->IsInstantiable()) {
                // ignore abstract class or interface
                return false;
            }

            $model = $this->laravel->make($model);

            $this->getPropertiesFromTable($model);
            $this->getPropertiesFromMethods($model);

            $output .= $this->createFactory($model);
        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage() . "\nCould not analyze class $model.");
        }

        return $output;
    }


    protected function loadModels($models = [])
    {
        if (!empty($models)) {
            return array_map(
                function ($name) {
                    if (strpos($name, '\\') !== false) {
                        return $name;
                    }

                    return str_replace(
                        [DIRECTORY_SEPARATOR, basename($this->laravel->path()) . '\\'],
                        ['\\', $this->laravel->getNamespace()],
                        $this->dir . DIRECTORY_SEPARATOR . $name
                    );
                },
                $models
            );
        }


        $dir = base_path($this->dir);
        if (!file_exists($dir)) {
            return [];
        }

        return array_map(
            function (\SplFIleInfo $file) {
                return str_replace(
                    [DIRECTORY_SEPARATOR, basename($this->laravel->path()) . '\\'],
                    ['\\', $this->laravel->getNamespace()],
                    $file->getPath() . DIRECTORY_SEPARATOR . basename($file->getFilename(), '.php')
                );
            },
            $this->files->allFiles($this->dir)
        );
    }

    /**
     * Load the properties from the database table.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function getPropertiesFromTable($model)
    {
        $table = $model->getConnection()->getTablePrefix() . $model->getTable();
        $schema = $model->getConnection()->getDoctrineSchemaManager($table);
        $databasePlatform = $schema->getDatabasePlatform();
        $databasePlatform->registerDoctrineTypeMapping('enum', 'customEnum');

        $platformName = $databasePlatform->getName();
        $customTypes = $this->laravel['config']->get("ide-helper.custom_db_types.{$platformName}", array());
        foreach ($customTypes as $yourTypeName => $doctrineTypeName) {
            $databasePlatform->registerDoctrineTypeMapping($yourTypeName, $doctrineTypeName);
        }

        $database = null;
        if (strpos($table, '.')) {
            list($database, $table) = explode('.', $table);
        }

        $columns = $schema->listTableColumns($table, $database);

        if ($columns) {
            foreach ($columns as $column) {
                $name = $column->getName();
                if (in_array($name, $model->getDates())) {
                    $type = 'datetime';
                } else {
                    $type = $column->getType()->getName();
                }
                if (!($model->incrementing && $model->getKeyName() === $name) &&
                    $name !== $model::CREATED_AT &&
                    $name !== $model::UPDATED_AT
                ) {
                    if (!method_exists($model, 'getDeletedAtColumn') || (method_exists(
                                $model,
                                'getDeletedAtColumn'
                            ) && $name !== $model->getDeletedAtColumn())) {
                        $this->setProperty($name, $type, $table);
                    }
                }
            }
        }
    }


    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function getPropertiesFromMethods($model)
    {
        $methods = get_class_methods($model);

        foreach ($methods as $method) {
            if (!Str::startsWith($method, 'get') && !method_exists('Illuminate\Database\Eloquent\Model', $method)) {
                // Use reflection to inspect the code, based on Illuminate/Support/SerializableClosure.php
                $reflection = new \ReflectionMethod($model, $method);
                $file = new \SplFileObject($reflection->getFileName());
                $file->seek($reflection->getStartLine() - 1);
                $code = '';
                while ($file->key() < $reflection->getEndLine()) {
                    $code .= $file->current();
                    $file->next();
                }
                $code = trim(preg_replace('/\s\s+/', '', $code));
                $begin = strpos($code, 'function(');
                $code = substr($code, $begin, strrpos($code, '}') - $begin + 1);
                foreach (['belongsTo'] as $relation) {
                    $search = '$this->' . $relation . '(';
                    if ($pos = stripos($code, $search)) {
                        if (method_exists('Illuminate\Database\Eloquent\Model', $method)) {
                            $relationObj = $model->$method();
                            if ($relationObj instanceof Relation) {
                                $this->setProperty(
                                    $relationObj->getForeignKeyName(),
                                    'factory(' . get_class($relationObj->getRelated()) . '::class)'
                                );
                            }
                        } else {
                            $this->setProperty(
                                $method . "_id",
                                'factory(' . ucfirst(
                                    str_replace("_", "", str::camel($method) . "Model")
                                ) . '::class)'
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $name
     * @param string|null $type
     */
    protected function setProperty($name, $type = null, $table = null)
    {
        if ($type !== null && Str::startsWith($type, 'factory(')) {
            $this->properties[$name] = $type;

            return;
        }

        $fakeableTypes = [
            'enum' => '$faker->randomElement(' . $this->enumValues($table, $name) . ')',
            'string' => '$faker->word',
            'text' => '$faker->text',
            'date' => '$faker->date()',
            'time' => '$faker->time()',
            'guid' => '$faker->word',
            'datetimetz' => '$faker->dateTime()',
            'datetime' => '$faker->dateTime()',
            'integer' => '$faker->randomNumber()',
            'bigint' => '$faker->randomNumber()',
            'smallint' => '$faker->randomNumber()',
            'decimal' => '$faker->randomFloat()',
            'float' => '$faker->randomFloat()',
            'boolean' => '$faker->boolean'
        ];

        $fakeableNames = [
            'city' => '$faker->city',
            'company' => '$faker->company',
            'country' => '$faker->country',
            'description' => '$faker->text',
            'email' => '$faker->safeEmail',
            'first_name' => '$faker->firstName',
            'firstname' => '$faker->firstName',
            'guid' => '$faker->uuid',
            'last_name' => '$faker->lastName',
            'lastname' => '$faker->lastName',
            'lat' => '$faker->latitude',
            'latitude' => '$faker->latitude',
            'lng' => '$faker->longitude',
            'longitude' => '$faker->longitude',
            'name' => '$faker->name',
            'password' => 'bcrypt($faker->password)',
            'phone' => '$faker->phoneNumber',
            'phone_number' => '$faker->phoneNumber',
            'postcode' => '$faker->postcode',
            'postal_code' => '$faker->postcode',
            'remember_token' => 'Str::random(10)',
            'slug' => '$faker->slug',
            'street' => '$faker->streetName',
            'address1' => '$faker->streetAddress',
            'address2' => '$faker->secondaryAddress',
            'summary' => '$faker->text',
            'url' => '$faker->url',
            'user_name' => '$faker->userName',
            'username' => '$faker->userName',
            'uuid' => '$faker->uuid',
            'zip' => '$faker->postcode',
        ];

        if (isset($fakeableNames[$name])) {
            $this->properties[$name] = $fakeableNames[$name];

            return;
        }

        if (isset($fakeableTypes[$type])) {
            $this->properties[$name] = $fakeableTypes[$type];

            return;
        }

        $this->properties[$name] = '$faker->word';
    }

    public static function enumValues($table, $name)
    {
        if ($table === null) {
            return "[]";
        }

        $type = DB::select(DB::raw('SHOW COLUMNS FROM ' . $table . ' WHERE Field = "' . $name . '"'))[0]->Type;

        preg_match_all("/'([^']+)'/", $type, $matches);

        $values = isset($matches[1]) ? $matches[1] : array();

        return "['" . implode("', '", $values) . "']";
    }


    /**
     * @param string $class
     * @return string
     */
    protected function createFactory($class)
    {
        $reflection = new \ReflectionClass($class);

        $content = $this->view->make(
            'cli-generator::factory',
            [
                'reflection' => $reflection,
                'properties' => $this->properties,
            ]
        )->render();

        return $content;
    }
}
