<?php

namespace LangleyFoxall\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Class Models
 * @package LangleyFoxall\Helpers
 */
abstract class Models
{
    /**
     * Returns a collection of class names for all Eloquent models within your app path.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function all()
    {
        $command = 'grep --include="*.php" --files-with-matches -r "class" '.app_path();

        exec($command, $files);

        return collect($files)->map(function($file) {
            return self::convertFileToClass($file);
        })->filter(function($class) {
            return class_exists($class) && is_subclass_of($class, Model::class);
        });
    }

    /**
     * Converts a file name to a namespaced class name.
     *
     * @param string $file
     * @return string
     */
    private static function convertFileToClass(string $file)
    {
        $fh = fopen($file, 'r');

        $namespace = '';

        while(($line = fgets($fh, 5000)) !== false) {

            if (str_contains($line, 'namespace')) {
                $namespace = trim(str_replace(['namespace', ';'], '', $line));
                break;
            }
        }

        fclose($fh);

        $class = basename(str_replace('.php', '', $file));

        return $namespace.'\\'.$class;
    }

    /**
     * UTF-8 encodes the attributes of a model.
     *
     * @param Model $model
     * @return Model
     */
    public static function utf8EncodeModel(Model $model)
    {
        foreach($model->toArray() as $key => $value) {
            if (is_numeric($value) || !is_string($value)) {
                continue;
            }
            $model->$key = utf8_encode($value);
        }

        return $model;
    }

    /**
     * UTF-8 encodes the attributes of a collection of models.
     *
     * @param Collection $models
     * @return Collection
     */
    public static function utf8EncodeModels(Collection $models)
    {
        return $models->map(function($model) {
            return self::utf8EncodeModel($model);
        });
    }

    /**
     * Gets an array of the columns in this model's database table
     *
     * @param Model $model
     * @return mixed
     */
    public static function getColumns(Model $model)
    {
        return Schema::getColumnListing($model->getTable());
    }

    /**
     * Gets the next auto-increment id for a model
     *
     * @param Model $model
     * @return int
     * @throws \Exception
     */
    public static function getNextId(Model $model)
    {
        $statement = DB::select('show table status like \''.$model->getTable().'\'');

        if (!isset($statement[0]) || !isset($statement[0]->Auto_increment)) {
            throw new \Exception('Unable to retrieve next auto-increment id for this model.');
        }

        return (int) $statement[0]->Auto_increment;
    }

	/**
	 * Check if any number of models are related to each other
	 *
	 * @param Model $a
	 * @param Model $b
	 * @return bool
	 */
	public static function areRelated($a, $b)
	{
		try {
			$relations = func_get_args();
			$max_key   = count($relations) - 1;

			foreach ($relations as $key => &$relation) {
				if (!is_array($relation)) {
					$basename = strtolower(class_basename($relation));

					$relation = [ $relation, Str::plural($basename) ];
				}

				if (!($relation[ 0 ] instanceof Model)) {
					throw new \Exception('INVALID_MODEL');
				}
			}

			foreach ($relations as $key => $current) {
				if ($key !== $max_key) {
					$model    = $current[ 0 ];
					$relation = $relations[ $key + 1 ];

					$model->{$relation[ 1 ]}->findOrFail($relation[ 0 ]->id);
				}
			}

			return true;
		} catch(\Exception $e) {
			return false;
		}
	}
}