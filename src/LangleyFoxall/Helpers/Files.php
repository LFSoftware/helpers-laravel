<?php
namespace LangleyFoxall\Helpers;

class Files
{
    /**
	 * Return a filtered array of files in a given directory
	 * 
	 * @param string|array $pattern
	 * @param string $dir
	 * @return array
	 */
    public static function filter($pattern, string $dir)
    {
        if (!ends_with($dir, '/*')) {
            $dir = $dir . '/*';
        }

        $patterns = is_array($pattern) ? $pattern : [$pattern];

        return collect(glob($dir))->filter(function ($file) use ($patterns) {
            foreach ($patterns as $pattern) {
		    return !!preg_match($pattern, $file);
            }

            return false;
        });
    }
}
