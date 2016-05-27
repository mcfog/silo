<?php namespace Silo\Builtin;

use PDO;
use Silo\Builder\AbstractBuilder;
use Silo\Interfaces\IDriver;

class PDODriver implements IDriver
{
    const COMMA = ', ';
    const QUOTE = '`%s`';
    const PARENTHESIS = '(%s)';

    /**
     * @var PDO
     */
    protected $pdo;
    protected $param;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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
        $sql .= sprintf(' FROM %s.%s ', $builder->db, $builder->table);

        $sql = $this->appendWhere($builder, $sql);
        $sql = $this->appendOrder($builder, $sql);
        $sql = $this->appendLimit($builder, $sql);

        $statement = $this->execute($sql, $builder->params);

        return $statement;
    }

    public function count(AbstractBuilder $builder)
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s.%s ', $builder->db, $builder->table);

        $sql = $this->appendWhere($builder, $sql);

        $statement = $this->execute($sql, $builder->params);

        return (int)$statement->fetchColumn();
    }

    public function delete(AbstractBuilder $builder)
    {
        $sql = sprintf('DELETE FROM %s.%s ', $builder->db, $builder->table);

        $sql = $this->appendWhere($builder, $sql);
        $sql = $this->appendOrder($builder, $sql);
        $sql = $this->appendLimit($builder, $sql);

        $statement = $this->execute($sql, $builder->params);

        return $statement->rowCount();
    }

    public function update(AbstractBuilder $builder)
    {
        $sql = sprintf('UPDATE %s.%s SET ', $builder->db, $builder->table);

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
        $this->_insert($builder);

        return $this->pdo->lastInsertId();
    }

    public static function comma($values)
    {
        return implode(static::COMMA, $values);
    }

    public static function wrap($inner)
    {
        return sprintf(static::PARENTHESIS, $inner);
    }

    public static function quote($inner)
    {
        return sprintf(static::QUOTE, $inner);
    }

    /**
     * @return mixed
     */
    public function getOutParam()
    {
        return $this->param;
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
        foreach ($params as $key => &$value) {
            if (substr($key, 0, 3) === ':o_') {
                $statement->bindParam($key, $value, PDO::PARAM_INPUT_OUTPUT, 255);
            } else {
                $statement->bindValue($key, $value);
            }
        }

        $success = $statement->execute();

        if (!$success) {
            throw new \Exception(json_encode([
                $statement->errorCode(),
                $statement->errorInfo()
            ]));
        }

        //$params的out参数有引用，这里通过copy一次的方式解除引用
        $this->param = [];
        foreach ($params as $k => $v) {
            $this->param[$k] = $v;
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
    protected function _insert(AbstractBuilder $builder)
    {
        $params = [];
        $fields = [];
        foreach ($builder->content as $field => $value) {
            $key = ':v_' . count($params) . '_';
            $params[$key] = $value;
            $fields[$this->quote($field)] = $key;
        }

        $sql = sprintf('INSERT INTO %s.%s %s VALUES %s',
            $builder->db,
            $builder->table,
            $this->wrap($this->comma(array_keys($fields))),
            $this->wrap($this->comma(array_values($fields)))
        );

        return $this->execute($sql, $params + $builder->params);
    }
}
