<?php

namespace MatrixLab\LaravelAdvancedSearch\Console\Commands;

use Composer\Autoload\ClassLoader;
use Composer\Autoload\ClassMapGenerator;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use ReflectionObject;
use Symfony\Component\Console\Output\OutputInterface;

class ModelsAllColumnsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:models-columns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'add selects to Models';

    protected $dirs = [];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->dirs = ['app'];
        $models     = $this->loadModels();

        $hasDoctrine = interface_exists('Doctrine\DBAL\Driver');

        foreach ($models as $name) {
            $this->properties = [];
            $this->methods    = [];
            if (class_exists($name)) {
                try {
                    // handle abstract classes, interfaces, ...
                    $reflectionClass = new \ReflectionClass($name);

                    if (!$reflectionClass->isSubclassOf('Illuminate\Database\Eloquent\Model')
                        || $reflectionClass->isSubclassOf('Illuminate\Database\Eloquent\Relations\Pivot')) {
                        continue;
                    }

                    if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $this->comment("Loading model '$name'");
                    }

                    if (!$reflectionClass->IsInstantiable()) {
                        // ignore abstract class or interface
                        continue;
                    }

                    $model = $this->laravel->make($name);

                    if ($hasDoctrine) {
                        $columns = $this->getPropertiesFromTable($model);
                    }

                    if (!count($columns)) {
                        continue;
                    }

                    $columnsFiledContent = '';
                    /** @var Column $column */
                    foreach ($columns as $column) {
                        $comment             = $column->getComment() ? ' // '.$column->getComment() : '';
                        $columnsFiledContent .= <<<EOF
        '{$column->getName()}',{$comment}

EOF;
                    }

                    $replacedContent = <<<EOF


    protected \$allColumns = [
$columnsFiledContent    ];
}
EOF;

                    // Get file path and name by Refleaction
                    $modelReflection = new ReflectionObject(new $model);
                    $fileName        = $modelReflection->getFileName();

                    $modelContent = file_get_contents($fileName);
                    // Remove allColumns
                    $modelContent = preg_replace('/\s+p.+\$allColumns[\s\S]+\]\;/', '', $modelContent);

                    // Rewrite model file
                    file_put_contents($fileName, preg_replace('/\n+\}/', $replacedContent, $modelContent));

                    $this->comment($modelReflection->getName().' has been appended $allColumns.');
                } catch (\Exception $e) {
                    $this->error("Exception: ".$e->getMessage()."\nCould not analyze class $name.");
                }
            }
        }


        if (!$hasDoctrine) {
            $this->error(
                'Warning: `"doctrine/dbal": "~2.3"` is required to load database information. '.
                'Please require that in your composer.json and run `composer update`.'
            );
        }
    }

    protected function loadModels()
    {
        $models = [];
        foreach ($this->dirs as $dir) {
            $dir = base_path().'/'.$dir;
            if (file_exists($dir)) {
                foreach (ClassMapGenerator::createMap($dir) as $model => $path) {
                    $models[] = $model;
                }
            }
        }
        return $models;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public function getPropertiesFromTable($model)
    {
        $table  = $model->getConnection()->getTablePrefix().$model->getTable();
        $schema = $model->getConnection()->getDoctrineSchemaManager($table);

        $database = null;
        if (strpos($table, '.')) {
            list($database, $table) = explode('.', $table);
        }

        return $schema->listTableColumns($table, $database);
    }
}
