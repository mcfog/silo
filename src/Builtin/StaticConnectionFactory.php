<?php namespace Silo\Builtin;

use Silo\Builder\AbstractBuilder;
use Silo\Interfaces\IConnectionFactory;
use Silo\Interfaces\IDriver;

class StaticConnectionFactory implements IConnectionFactory
{
    const CONNECTION_DEFAULT = 'default';
    protected static $instance;
    protected $connections = [];

    /**
     * @return self
     */
    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new static;
        }

        return self::$instance;
    }

    public function setConnection(IDriver $connection, $key = self::CONNECTION_DEFAULT)
    {
        $this->connections[$key] = $connection;

        return $this;
    }

    /**
     * @param AbstractBuilder $builder
     * @return IDriver $connection
     */
    public function getConnection(AbstractBuilder $builder)
    {
        if (!isset($this->connections[$builder::CONNECTION])) {
            throw new \RuntimeException(sprintf('conneciont<%s> not found'));
        }

        return $this->connections[$builder::CONNECTION];
    }
}
