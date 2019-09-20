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
	 * @param Model[]|array[] $relations
	 * @throws \InvalidArgumentException|\Exception
	 * @return bool
	 */
	public static function areRelated(...$relations)
	{
		try {
			$max_key = count($relations) - 1;

			foreach ($relations as $key => $current) {
				if (!is_array($current)) {
					$previous = null;

					if ($key > 0) {
						$previous = $relations[ $key - 1 ];
						$previous = is_array($previous)
							? $previous[ 0 ] : $previous;
					}

					$basename = strtolower(class_basename($current));
					$method   = Str::plural($basename);

					if (!is_null($previous)) {
						if (!method_exists($previous, $method)) {
							$method = Str::singular($basename);
						}

						if (!method_exists($previous, $method)) {
							throw new \Exception('UNABLE_TO_FIND_RELATIONSHIP');
						}
					}

					$relations[ $key ] = [ $current, $method ];
				}

				if (!($relations[ $key ][ 0 ] instanceof Model)) {
					throw new \InvalidArgumentException('INVALID_MODEL');
				}
			}

			foreach ($relations as $key => $current) {
				if ($key !== $max_key) {
					$model    = $current[ 0 ];
					$relation = $relations[ $key + 1 ];

					$model->{$relation[ 1 ]}()->findOrFail($relation[ 0 ]->id);
				}
			}

			return true;
		} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
			return false;
		}
	}

    /**
     * @param Collection|String $models Either a collection of Model's or a String representation of a Model.
     * @param String $column
     * @param int $maxCap Enter this is the weights are odds (Eg: Weight = 1, $maxCap = 1000 for one in a thousand.)
     * @param object $ifLose If you've entered a maxCap, set what gets returned if nothing gets hit.
     * @return Model $model
     */
    public static function randomByWeightedValue($models, $column, $maxCap = null, $ifLose = null)
    {
        //If model string is passed in, get all model instances.
        if (is_string($models)) {
            $models = $models::whereNotNull($column)->get();
        }

        $total = $models->pluck($column)->sum();
        $rand  = mt_rand(1, $maxCap ?: $total);

        foreach ($models as $index => $model) {
            $rand -= $model->$column;

            if ($rand <= 0) {
                return [ $model, $index ];
            }
        }

	return [ $ifLose, null ];
    }
}
