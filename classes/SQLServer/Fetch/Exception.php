<?php

namespace SQLServer\Fetch;

class Exception extends \SQLServer\Exception
{
    /**
     * Запрос выбросивший исключение при попытке получения значений.
     * @var string
     */
    protected $query;

    public function __construct(string $message = "", int $code = 0, $query = null, \Throwable $previous = NULL)
    {
	parent::__construct($message, $code, $previous);
	$this->query =& $query;
    }

    /** @return string */
    public function getQuery():string
    {
	return $this->query;
    }
}
