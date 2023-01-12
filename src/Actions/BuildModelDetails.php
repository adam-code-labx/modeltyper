<?php

namespace FumeApp\ModelTyper\Actions;

use FumeApp\ModelTyper\Traits\ClassBaseName;
use FumeApp\ModelTyper\Traits\ModelRefClass;
use Illuminate\Console\Concerns\CallsCommands;
use Illuminate\Support\Facades\Artisan;
use ReflectionException;
use Symfony\Component\Finder\SplFileInfo;

class BuildModelDetails
{
    use ClassBaseName;
    use ModelRefClass;
    use CallsCommands;

    /**
     * Build the model details.
     *
     * @param  SplFileInfo  $modelFile
     * @return array
     *
     * @throws ReflectionException
     */
    public function __invoke(SplFileInfo $modelFile): array
    {
        $model = $modelFile->getBasename('.php');
        if (!str_ends_with($modelFile->getPath(), 'app/Models')) {
            $model = getClassNamespaceFromFile($modelFile->getPathname()) . '\\' . $model;
        }



        $modelDetails = app(RunModelShowCommand::class)($model);

        $reflectionModel = $this->getRefInterface($modelDetails);
        $laravelModel = $reflectionModel->newInstance();
        $databaseColumns = $laravelModel->getConnection()->getSchemaBuilder()->getColumnListing($laravelModel->getTable());

        $name = $this->getClassName($modelDetails['class']);
        $columns = collect($modelDetails['attributes'])->filter(fn ($att) => in_array($att['name'], $databaseColumns));
        $nonColumns = collect($modelDetails['attributes'])->filter(fn ($att) => ! in_array($att['name'], $databaseColumns));
        $relations = collect($modelDetails['relations']);
        $interfaces = collect($laravelModel->interfaces)->map(fn ($interface, $key) => [
            'name' => $key,
            'type' => $interface['type'] ?? 'unknown',
            'nullable' => $interface['nullable'] ?? false,
            'import' => $interface['import'] ?? null,
            'forceType' => true,
        ]);

        $imports = $interfaces->filter(function ($interface) {
            return isset($interface['import']);
        })
            ->map(function ($interface) {
                return [
                    'import' => $interface['import'],
                    'type' => $interface['type'],
                ];
            })
            ->unique()
            ->values();

        $columns = $columns->map(function ($column) use (&$interfaces) {
            $interfaces->each(function ($interface, $key) use (&$column, &$interfaces) {
                if ($key === $column['name']) {
                    if (isset($interface['type'])) {
                        $column['type'] = $interface['type'];
                        $column['forceType'] = true;

                        $interfaces->forget($key);
                    }
                }
            });

            return $column;
        });

        return [
            'reflectionModel' => $reflectionModel,
            'name' => $name,
            'columns' => $columns,
            'nonColumns' => $nonColumns,
            'relations' => $relations,
            'interfaces' => $interfaces->values(),
            'imports' => $imports,
        ];
    }

    protected function resolveCommand($command)
    {
        // TODO: Implement resolveCommand() method.
    }
}
