<?php

namespace FumeApp\ModelTyper\Actions;

use Exception;
use Illuminate\Support\Facades\Artisan;

class RunModelShowCommand
{
    /**
     * Run internal Laravel model:show command.
     *
     * @see https://github.com/laravel/framework/blob/9.x/src/Illuminate/Foundation/Console/ShowModelCommand.php
     *
     * @param  string  $model
     * @return array
     *
     * @throws Exception
     */
    public function __invoke(string $model): array
    {
        Artisan::call('model:show', ['model' => $model, '--json' => true]);
        $output = json_decode(Artisan::output(), true);

        if (!$output) {
            throw new Exception('You may need to install the doctrine/dbal package to use this command.');
        }

        return $output;
    }
}
