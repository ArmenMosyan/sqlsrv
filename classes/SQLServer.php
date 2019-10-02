<?php

if (!extension_loaded('sqlsrv'))
{
    throw new SQLServer\Exception('PHP extension sqlsrv not install');
}

class SQLServer
{
    /**
     * Имя сервера.
     * @var string
     */
    protected $server_name;

    /**
     * Информация по подключению.
     * @var array
     */
    protected $connect_info = [
	'Database' => null,
	'UID' => null,
	'PWD' => null,
	'CharacterSet' => SQLSRV_ENC_CHAR,
        //'CharacterSet' => 'UTF-8'
    ];

    /** @var resource of type(SQL Server Connection) */
    protected $connect;

    /** @var string */
    protected $sql;

    /** @var resource SQL Server Statement */
    protected $query;

    /**
     * @param string $server_name
     * @param array $connect_info
     */
    public function __construct(string $server_name, array $connect_info = null)
    {
	$this->server_name = $server_name;

	if ($connect_info)
	{
	    $this->connect_info = array_merge($this->connect_info, $connect_info);
	}
    }

    /**
     * Подключиться к БД
     * @return bool
     */
    public function connect()
    {
	if (!$this->connect)
	{
	    if (($this->connect = sqlsrv_connect($this->server_name, $this->connect_info)) ===
		    false)
	    {
		$error = current(sqlsrv_errors(SQLSRV_ERR_ALL));
		throw new SQLServer\Connect\Exception($error['message'], $error['code']);
	    }
	    // Закрыть соединение по окончанию работы скрипта
	    register_shutdown_function([$this, 'close']);
	}

	return true;
    }

    /**
     * Закрывает соединение.
     * @return boolean
     */
    public function close()
    {
	if ($this->connect)
	{
	    return sqlsrv_close($this->connect);
	}

	return true;
    }

    /**
     * Возвращает информацию о сервере.
     * @return array
     */
    public function server_info()
    {
	if ($this->connect())
	{
	    return sqlsrv_server_info($this->connect);
	}
    }

    /**
     * Возвращает сведения о подключении и стеке клиента.
     * @return array
     */
    public function client_info()
    {
	if ($this->connect())
	{
	    return sqlsrv_client_info($this->connect);
	}
    }

    /**
     * Выполняет запрос.
     *
     * @param string $sql
     * @param array $params
     * @param array $options
     * @return resource
     * @throws SQLServerQueryException
     */
    public function query($sql, array $params = null, array $options = null)
    {
	$this->connect();

	$this->query = sqlsrv_query($this->connect, $sql, $params, $options);

	if ($this->query === false)
	{
	    $error = current(sqlsrv_errors(SQLSRV_ERR_ALL));
	    throw new SQLServer\Query\Exception($error['message'], $error['code'], $sql);
	}

	return $this->query;
    }

    /**
     * Возвращает результирующий набор в виде ассоциативного массива.
     *
     * @param string $sql
     * @param array $params
     * @return array
     * @throws \SQLServer\Fetch\Exception
     */
    public function get_assoc_row($sql, array $params = null)
    {
	$this->query($sql, $params);

	if (!is_resource($this->query))
	{
	    throw new \SQLServer\Fetch\Exception('Query is empty');
	}

	$result = sqlsrv_fetch_array($this->query, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_NEXT);

	if ($result === false)
	{
	    $error = current(sqlsrv_errors(SQLSRV_ERR_ALL));
	    throw new SQLServer\Fetch\Exception($error['message'], $error['code'], $this->query);
	}

	/* Free statement and connection resources. */
	sqlsrv_free_stmt($this->query);

	return $result;
    }

    /**
     * Возвращает результирующий набор в виде массива, где ключ числовое значение
     * или значение указанное в параметре $key, а значение ассоциативный массив.
     *
     * @param string $sql
     * @param array $params
     * @param string $key
     * @return type
     * @throws \SQLServer\Fetch\Exception
     */
    public function get_assoc_rows($sql, array $params = null, string $key = null)
    {
	$this->query($sql, $params);

	if (!is_resource($this->query))
	{
	    throw new \SQLServer\Fetch\Exception('Query is empty');
	}

	$result = [];

	while ($row = sqlsrv_fetch_array($this->query, SQLSRV_FETCH_ASSOC, SQLSRV_SCROLL_NEXT))
	{
	    if ($row === false)
	    {
		$error = current(sqlsrv_errors(SQLSRV_ERR_ALL));
		throw new SQLServer\Fetch\Exception($error['message'], $error['code'], $this->query);
	    }

	    if (!is_null($key) && array_key_exists($key, $row))
	    {
		$result[$row[$key]] = $row;
	    } else
	    {
		array_push($result, $row);
	    }
	}

	/* Free statement and connection resources. */
	sqlsrv_free_stmt($this->query);

	return $result;
    }

    /**
     * Извлекает данные из указанного поля текущей строки.
     *
     * @param string $sql
     * @param array $params
     * @param int $fieldIndex
     * @return string
     * @throws \SQLServer\Fetch\Exception
     */
    public function get_one($sql, array $params = null, int $fieldIndex = 1)
    {
	$this->query($sql, $params);

	if (!is_resource($this->query))
	{
	    throw new \SQLServer\Fetch\Exception('Query is empty');
	}

	if (sqlsrv_fetch($this->query) === false)
	{
	    $error = current(sqlsrv_errors(SQLSRV_ERR_ALL));
	    throw new SQLServer\Fetch\Exception($error['message'], $error['code'], $this->query);
	}

	$result = sqlsrv_get_field($this->query, $fieldIndex);

	if ($result === false)
	{
	    $error = current(sqlsrv_errors(SQLSRV_ERR_ALL));
	    throw new SQLServer\Fetch\Exception($error['message'], $error['code'], $this->query);
	}

	/* Free statement and connection resources. */
	sqlsrv_free_stmt($this->query);

	return $result;
    }

    /**
     * Возвращает идентификатор объекта из БД по его названию.
     * @param string $object_name
     * @return int
     */
    public function object_id($object_name)
    {
	return $this->get_one('SELECT OBJECT_ID(?)', [$object_name], 0);
    }

    /**
     * Возвращает название объекта из БД по его идентификатору.
     * @param int $object_id
     * @return int
     */
    public function object_name(int $object_id)
    {
	return $this->get_one('SELECT OBJECT_NAME(?)', [$object_id], 0);
    }

    /**
     * Возвращает идентификатор объекта из БД
     * @param string $object_name
     * @return int
     */
    public function guid()
    {
	return $this->get_one('SELECT NEWID()', null, 0);
    }

    /**
     * Возвращает список всех хранимых процедур и их входных параметров.
     * @return array
     */
    public function procedures()
    {
	/**
	 * Запрос на получение процедур.
	 * @var string $sql */
	$sql = "SELECT [object_id],[create_date], [modify_date], CONCAT('[',SCHEMA_NAME([schema_id]),'].[',[name],']') [name] FROM [sys].[procedures] ORDER BY SCHEMA_NAME([schema_id]) ASC, [name] ASC";

	/** @var array $procedures */
	$procedures = $this->get_assoc_rows($sql, null, 'object_id');

	if ($procedures)
	{
	    /**
	     * Запрос на получение входных параметров.
	     * @var string $sql
	     */
	    $sql = "SELECT [object_id],[parameter_id],[name],TYPE_NAME([user_type_id]) as [type],[is_readonly],[is_nullable] FROM [sys].[parameters] ORDER BY OBJECT_NAME([object_id]) ASC, [parameter_id] ASC";

	    /** @var array $row*/
	    foreach ($this->get_assoc_rows($sql) as $row)
	    {
		if (isset($procedures[$row['object_id']]))
		{
		    $procedures[$row['object_id']]['params'][$row['parameter_id']] = $row;
		}
	    }
	}

	return $procedures;
    }
}
