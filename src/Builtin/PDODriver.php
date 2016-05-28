<?php namespace Silo\Builtin;

use PDO;
use Silo\Builder\AbstractBuilder;
use Silo\Interfaces\IDriver;

class PDODriver implements IDriver
{
    const DRIVER_PGSQL = 'pgsql';
    /**
     * @var string
     */
    protected $pdoDriver;
    /**
     * @var string
     */
    protected $dbName;

    /**
     * @var PDO
     */
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdoDriver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function selectFirst(AbstractBuilder $builder)
    {
        return $this->select($builder)->fetch();
    }

    public function select(AbstractBuilder $builder)
    {
        if (isset($builder->fields)) {
            $sql = 'SELECT ' . $builder->fields;
        } else {
            $sql = 'SELECT *';
        }
        $sql .= sprintf(' FROM %s ', $this->makeFrom($builder));

        $sql = $this->appendWhere($builder, $sql);
        $sql = $this->appendOrder($builder, $sql);
        $sql = $this->appendLimit($builder, $sql);

        $statement = $this->execute($sql, $builder->params);

        return $statement;
    }

    public function count(AbstractBuilder $builder)
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s ', $this->makeFrom($builder));

        $sql = $this->appendWhere($builder, $sql);

        $statement = $this->execute($sql, $builder->params);

        return (int)$statement->fetchColumn();
    }

    public function delete(AbstractBuilder $builder)
    {
        $sql = sprintf('DELETE FROM %s ', $this->makeFrom($builder));

        $sql = $this->appendWhere($builder, $sql);
        $sql = $this->appendOrder($builder, $sql);
        $sql = $this->appendLimit($builder, $sql);

        $statement = $this->execute($sql, $builder->params);

        return $statement->rowCount();
    }

    public function update(AbstractBuilder $builder)
    {
        $sql = sprintf('UPDATE %s SET ', $this->makeFrom($builder));

        list($params, $part) = $this->makeAssignStatements($builder->content);

        $sql .= $part;

        $sql = $this->appendWhere($builder, $sql);
        $sql = $this->appendOrder($builder, $sql);
        $sql = $this->appendLimit($builder, $sql);

        if (isset($builder->params)) {
            $params += $builder->params;
        }

        $statement = $this->execute($sql, $params);

        return $statement->rowCount();
    }

    public function insert(AbstractBuilder $builder, array $pk)
    {
        $statement = $this->_insert($builder, $pk);

        switch ($this->pdoDriver) {
            case self::DRIVER_PGSQL:
                if (count($pk) === 1) {
                    return $statement->fetchColumn();
                }

                return null;
            default:
                return $this->pdo->lastInsertId();
        }
    }

    public function comma(array $values)
    {
        return implode(', ', $values);
    }

    public function wrap($inner)
    {
        return sprintf('(%s)', $inner);
    }

    public function quote($inner)
    {
        switch ($this->pdoDriver) {
            case 'sqlite':
                return sprintf('[%s]', $inner);
            case self::DRIVER_PGSQL:
                return sprintf('"%s"', $inner);
            case 'mysql':
                return sprintf('`%s`', $inner);
            default:
                return $inner;
        }
    }

    /**
     * @param AbstractBuilder $builder
     * @param $sql
     * @return string
     */
    protected function appendWhere(AbstractBuilder $builder, $sql)
    {
        if (isset($builder->where)) {
            $sql .= ' WHERE ' . $builder->where;
        }
        return $sql;
    }

    /**
     * @param AbstractBuilder $builder
     * @param $sql
     * @return string
     */
    protected function appendOrder(AbstractBuilder $builder, $sql)
    {
        if (isset($builder->order)) {
            $sql .= ' ORDER BY ' . $builder->order;
        }

        return $sql;
    }

    /**
     * @param AbstractBuilder $builder
     * @param $sql
     * @return string
     */
    protected function appendLimit(AbstractBuilder $builder, $sql)
    {
        if (isset($builder->limit)) {
            $sql .= ' LIMIT ';
            if (isset($builder->offset)) {
                $sql .= $builder->offset . ',';
            }
            $sql .= $builder->limit;
        }

        return $sql;
    }

    /**
     * @param $sql
     * @param $params
     * @return \PDOStatement
     * @throws \Exception
     */
    protected function execute($sql, $params)
    {
        $statement = $this->pdo->prepare($sql);
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $success = $statement->execute();

        if (!$success) {
            throw new \Exception(json_encode([
                'errorCode' => $statement->errorCode(),
                'errorInfo' => $statement->errorInfo(),
            ]));
        }

        return $statement;
    }

    /**
     * @param array $content
     * @return array
     */
    protected function makeAssignStatements(array $content)
    {
        $params = [];
        $updates = [];
        foreach ($content as $field => $value) {
            $key = ':v_' . count($params);
            $params[$key] = $value;
            $updates[] = sprintf('%s = %s', $this->quote($field), $key);
        }

        $part = $this->comma($updates);

        return [$params, $part];
    }

    /**
     * @param AbstractBuilder $builder
     * @return \PDOStatement
     * @throws \Exception
     */
    protected function _insert(AbstractBuilder $builder, array $pk)
    {
        $params = [];
        $fields = [];
        foreach ($builder->content as $field => $value) {
            $key = ':v_' . count($params) . '_';
            $params[$key] = $value;
            $fields[$this->quote($field)] = $key;
        }

        $sql = sprintf('INSERT INTO %s %s VALUES %s',
            $this->makeFrom($builder),
            $this->wrap($this->comma(array_keys($fields))),
            $this->wrap($this->comma(array_values($fields)))
        );

        switch ($this->pdoDriver) {
            case self::DRIVER_PGSQL:
                $sql .= implode(',', array_map(function($key) {
                    return ' RETURNING ' . $key;
                }, $pk));

                break;
            default:
                break;
        }

        return $this->execute($sql, $params + $builder->params);
    }

    protected function makeFrom(AbstractBuilder $builder)
    {
        switch ($this->pdoDriver) {
            case self::DRIVER_PGSQL:
                if (!empty($builder->db) && $this->getDbName() !== $builder->db) {
                    throw new \RuntimeException('pgsql connection is not on this db:' . $builder->db);
                }

                return $builder->table;

            default:
                return implode('.', [$builder->db, $builder->table]);
        }
    }

    /**
     * @return string
     */
    protected function getDbName()
    {
        if (!isset($this->dbName)) {
            $this->dbName = $this->pdo->query(' SELECT current_database()')->fetchColumn();
        }

        $dbName = $this->dbName;
        return $dbName;
    }

    public function runTransation(callable $callback)
    {
        $this->pdo->beginTransaction();
        try {
            $result = call_user_func($callback);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        if (false === $this->pdo->commit()) {
            throw new \PDOException('transaction failed to commit');
        }

        return $result;
    }
}
