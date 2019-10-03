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
	    $this->connect_info = array_filter(array_merge($this->connect_info, $connect_info), function($v)
	    {
		return !is_null($v);
	    });
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
	    $this->connect = sqlsrv_connect($this->server_name, $this->connect_info);

	    if ($this->connect === false)
	    {
		$error = current(sqlsrv_errors(SQLSRV_ERR_ALL));
		throw new SQLServer\Connect\Exception($error['message'], $error['code']);
	    }

	    // Закрыть соединение по окончанию работы скрипта
	    register_shutdown_function([$this, 'close']);
	}

	return true;
    }

    public function __get($name)
    {
	if (isset($this->connect_info[$name]))
	{
	    return $this->connect_info[$name];
	}
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
     * Возврашает идентификато схемы.
     * @param string $name
     * @return string
     */
    public function schema_id($name = null)
    {
	if ($name)
	{
	    return $this->get_one('SELECT SCHEMA_ID(?)', [$name], 0);
	} else
	{
	    return $this->get_one('SELECT SCHEMA_ID()', null, 0);
	}
    }

    /**
     * Возвращает название схемы.
     * @param int $id
     */
    public function schema_name(int $id = null)
    {
	if ($id)
	{
	    return $this->get_one('SELECT SCHEMA_NAME(?)', [$id], 0);
	} else
	{
	    return $this->get_one('SELECT SCHEMA_NAME()', null, 0);
	}
    }

    /**
     * Возвращает идентификатор объекта из БД по его названию.
     * @param string $object_name
     * @return int
     */
    public function object_id($object_name, $param = null)
    {
	if ($param)
	{
	    $param = ', \''.$param.'\'';
	}

	return $this->get_one('SELECT OBJECT_ID(?'.$param.')', [$object_name], 0);
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
     * Возвращает имя текущего пользователя.
     *
     * @return int - 0:CURRENT|1:SYSTEM
     *
     * @link https://docs.microsoft.com/ru-ru/sql/t-sql/functions/system-user-transact-sql
     * @link https://docs.microsoft.com/ru-ru/sql/t-sql/functions/current-user-transact-sql
     */
    public function get_user(int $type = 1)
    {
	if (in_array($type, [0, 1]))
	{
	    return $this->get_one('SELECT ' . strtoupper(['current', 'system'][$type]) . '_USER', null, 0);
	}

	return false;
    }

    /**
     * Проверка хранимой процедуры на существование.
     *
     * @param string $name
     * @param string $schema
     *
     * @return boolean
     */
    public function proc_exists($name, $schema = null)
    {
	/** @var string $table */
	$table = '[' . $this->Database . '].[sys].[procedures]';

	/** @var string $schema_id */
	if ($schema)
	{
	    $schema_id = $this->schema_id();
	} else
	{
	    $schema_id = $this->schema_id($name);
	}

	/** @var string $sql */
	$sql = 'SELECT IIF(EXISTS(SELECT * FROM ' . $table . ' WHERE [schema_id] = ? AND [name] = ?), 1, 0)';

	return boolval((int) $this->get_one($sql, [$schema_id, $name], 0) === 1);
    }

    /**
     * Возвращает список входных параметров хранимой процедуры.
     *
     * @param string $name
     * @param string $schema
     *
     * @return array
     */
    public function proc_params($name, $schema = null)
    {
	/** @var string $schema_id */
	if ($schema)
	{
	    $schema_id = $this->schema_id($schema);
	} else
	{
	    $schema = $this->schema_name();
	    $schema_id = $this->schema_id($schema);
	}

	/** @var string $object_id */
	$object_id = $this->object_id($schema.'.'.$name, 'P');

	/** @var string $sql */
	$sql = 'SELECT '
		. '[p].[parameter_id]'
		. ',[p].[name]'
		. ',TYPE_NAME([p].[user_type_id]) as [type]'
		. ',[p].[is_readonly]'
		. ',[p].[is_nullable] '
		. 'FROM [' . $this->Database . '].[sys].[parameters] [p]'
		. 'JOIN ['.$this->Database.'].[sys].[procedures] [ps] ON [ps].[object_id] = [p].[object_id] AND [ps].[schema_id] = ? AND [ps].[object_id] = ? '
		. 'ORDER BY [parameter_id] ASC';

	return $this->get_assoc_rows($sql, [$schema_id, $object_id], 'parameter_id');
    }
}
