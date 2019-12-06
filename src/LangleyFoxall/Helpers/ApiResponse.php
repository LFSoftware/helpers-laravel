<?php
namespace LangleyFoxall\Helpers;

class ApiResponse implements \ArrayAccess
{
	/** @var int $status */
	protected $status;

	/** @var bool $success */
	protected $success;

	/** @var array|null|string $error */
	protected $error;

	/** @var array|null $data */
	protected $data;

	/** @var array|null $meta */
	protected $meta;

	/**
	 * ApiResponse constructor.
	 *
	 * @param bool              $success
	 * @param array|null|string $error
	 * @param array|null        $data
	 * @param array|null        $meta
	 * @param int               $status
	 */
	public function __construct(bool $success = true, $error = null, $data = null, $meta = null, int $status = 200)
	{
		$this->status  = $status;
		$this->success = $success;
		$this->error   = $error;
		$this->data    = $data;
		$this->meta    = $meta;
	}

	/**
	 * @param array|null $data
	 * @param array|null $meta
	 * @param int        $status
	 * @return ApiResponse
	 */
	public static function success($data = null, $meta = null, int $status = 200)
	{
		$response = new self;

		$response->status = $status;
		$response->data   = $data;
		$response->meta   = $meta;

		return $response;
	}

	/**
	 * @param mixed $error
	 * @param int   $status
	 * @return ApiResponse
	 */
	public static function error($error = null, int $status = 400)
	{
		$response = new self;

		$response->status  = $status;
		$response->success = false;
		$response->error   = $error;
		
		if (is_string($error) && stripos('internal server error', $error) !== false) {
		    $response->status = $status < 500 ? 500 : $status;
		}

		return $response;
	}

	/**
	 * @param array $data
	 * @return $this
	 */
	public function data(array $data = null)
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * @param array $meta
	 * @return $this
	 */
	public function meta(array $meta = null)
	{
		$this->meta = $meta;

		return $this;
	}

	/**
	 * @param int $status
	 * @return $this
	 */
	public function status(int $status)
	{
		$this->status = $status;

		return $this;
	}

	/**
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function json()
	{
		return response()->json(get_object_vars($this))
						 ->setStatusCode($this->status);
	}

	/**
	 * @param string $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return isset($this->data[ $offset ]);
	}

	/**
	 * @param string $offset
	 * @return mixed|null
	 */
	public function offsetGet($offset)
	{
		return isset($this->data[ $offset ])
			? $this->data[ $offset ] : null;
	}

	/**
	 * @param string $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value)
	{
		$this->data[ $offset ] = $value;
	}

	/**
	 * @param string $offset
	 */
	public function offsetUnset($offset)
	{
		unset($this->data[ $offset ]);
	}
	
    /**
     * @param $lifespan
     * @param ResponseCache $cache
     * @return $this
     */
    public function cache($lifespan, ResponseCache $cache, $forceOverwrite = false){
        if(!$cache->hasData() || $forceOverwrite){
            $cache->cacheData($this->data, $lifespan);
        }
        return $this;
    }
}
