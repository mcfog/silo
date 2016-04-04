<?php namespace Silo\Builder;

use ArrayObject;
use Silo\Interfaces\IDriver;

/**
 * Class AbstractBuilder
 * @package Silo
 *
 *
 * @property array content
 * @property string fields
 * @property string table
 * @property string where
 * @property string order
 * @property int limit
 * @property int offset
 * @property array params
 */
abstract class AbstractBuilder extends \ArrayObject
{
    const CONNECTION = 'default';
    protected static $pk = [];

    /**
     * @var IDriver[]
     */
    protected static $connections = [];

    public function __construct($input = [])
    {
        parent::__construct($input + [
                'params' => [],
            ], self::ARRAY_AS_PROPS);
    }

    public static function setConnection(IDriver $driver, $name = 'default')
    {
        static::$connections[$name] = $driver;
    }

    public static function query()
    {
        return new static;
    }


    public function runSelect()
    {
        return static::getDriver()->select($this);
    }

    public function runSelectFirst()
    {
        return static::getDriver()->selectFirst($this);
    }

    public function runDelete()
    {
        return static::getDriver()->delete($this);
    }

    public function runInsert($pk = null)
    {
        if(is_null($pk)) {
            $pk = static::$pk;
        }
        return static::getDriver()->insert($this, $pk);
    }

    public function runUpdate()
    {
        return static::getDriver()->update($this);
    }

    public function runCount()
    {
        return static::getDriver()->count($this);
    }

    public function limit($limit, $offset = null)
    {
        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * @param $args
     * @return $this
     */
    public function where($args)
    {
        if (isset($this->where)) {
            trigger_error('overriding where clause', E_USER_WARNING);
        }
        $this->where = implode(' ', func_get_args());

        return $this;
    }

    public function andWhere($args)
    {
        $exp = implode(' ', func_get_args());
        if (isset($this->where)) {
            $this->where .= ' AND ' . $exp;
        } else {
            $this->where = $exp;
        }

        return $this;
    }

    public function order($args)
    {
        if (isset($this->order)) {
            trigger_error('overriding order clause', E_USER_WARNING);
        }
        $args = func_get_args();
        $this->order = implode(' ', $args);

        return $this;
    }

    public function param($value, $key = null)
    {
        if (is_null($key)) {
            $key = 'p_' . count($this->params);
        }
        $key = ":$key";
        $this->params[$key] = $value;

        return $key;
    }

    public function params(array $values, $keyPrefix = null)
    {
        if (is_null($keyPrefix)) {
            $keyPrefix = 'p_' . count($this->params);
        }
        $driver = static::getDriver();

        $keys = [];
        foreach ($values as $k => $value) {
            $key = ':' . $keyPrefix . '_' . $k;
            $this->params[$key] = $value;
            $keys[] = $key;
        }

        return $driver->wrap($driver->comma($keys));
    }

    /**
     * @return IDriver
     */
    protected static function getDriver()
    {
        if (!isset(static::$connections[static::CONNECTION])) {
            $msg = sprintf('cannot find silo connection <%s> for class %s', static::CONNECTION, static::class);
            trigger_error($msg, E_USER_WARNING);
        }

        return static::$connections[static::CONNECTION];
    }
}
