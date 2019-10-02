<?php

namespace SQLServer\Query;

class Exception extends \SQLServer\Exception
{
    /**
     * Запрос выбросивший исключение.
     * @var string
     */
    protected $sql;

    public function __construct(string $message = "", int $code = 0, string $sql = null, \Throwable $previous = NULL)
    {
	parent::__construct($message, $code, $previous);
	$this->sql = $sql;
    }

    /** @return string */
    public function getSQL():string
    {
	return (string) $this->sql;
    }
}
