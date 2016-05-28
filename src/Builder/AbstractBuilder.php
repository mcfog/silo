<?php namespace Silo\Builder;

use ArrayObject;
use Silo\Builtin\StaticConnectionFactory;
use Silo\Interfaces\IConnectionFactory;
use Silo\Interfaces\IDriver;

/**
 * Class AbstractBuilder
 * @package Silo
 *
 *
 * @property array content
 * @property string fields
 * @property string table
 * @property string db
 * @property string where
 * @property string order
 * @property int limit
 * @property int offset
 * @property array params
 */
abstract class AbstractBuilder extends \ArrayObject
{
    const CONNECTION = StaticConnectionFactory::CONNECTION_DEFAULT;

    protected static $pk = [];

    public function __construct($input = [])
    {
        parent::__construct($input + [
                'params' => [],
            ], self::ARRAY_AS_PROPS);
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
     * @param string $sql,...
     * @return $this
     */
    public function where()
    {
        return $this->setSqlStatement('order', func_get_args());
    }

    /**
     * @param string $sql,...
     * @return $this
     */
    public function andWhere()
    {
        $sql = implode(' ', func_get_args());
        if (isset($this->where)) {
            $this->where .= ' AND ' . $sql;
        } else {
            $this->where = $sql;
        }

        return $this;
    }

    /**
     * @param string $sql,...
     * @return $this
     */
    public function order()
    {
        return $this->setSqlStatement('order', func_get_args());
    }

    protected function setSqlStatement($part, array $sqls)
    {
        if (isset($this->{$part})) {
            trigger_error("overriding {$part} clause", E_USER_WARNING);
        }
        $this->{$part} = implode(' ', $sqls);

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
    protected function getDriver()
    {
        return static::getConnectionFactory()->getConnection($this);
    }

    /**
     * @return IConnectionFactory
     */
    protected static function getConnectionFactory()
    {
        return StaticConnectionFactory::instance();
    }
}
