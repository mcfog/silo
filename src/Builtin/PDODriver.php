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
        $data = $builder->getArrayCopy();
        if (isset($data['fields'])) {
            $sql = 'SELECT ' . $data['fields'];
        } else {
            $sql = 'SELECT *';
        }
        $sql .= sprintf(' FROM %s.%s ', $data['db'], $data['table']);

        $sql = $this->appendWhere($data, $sql);
        $sql = $this->appendOrder($data, $sql);
        $sql = $this->appendLimit($data, $sql);

        $statement = $this->execute($sql, $data['params']);

        return $statement;
    }

    public function count(AbstractBuilder $builder)
    {
        $data = $builder->getArrayCopy();
        $sql = sprintf('SELECT COUNT(*) FROM %s.%s ', $data['db'], $data['table']);

        $sql = $this->appendWhere($data, $sql);

        $statement = $this->execute($sql, $data['params']);

        return (int)$statement->fetchColumn();
    }

    public function delete(AbstractBuilder $builder)
    {
        $data = $builder->getArrayCopy();

        $sql = sprintf('DELETE FROM %s.%s ', $data['db'], $data['table']);

        $sql = $this->appendWhere($data, $sql);
        $sql = $this->appendOrder($data, $sql);
        $sql = $this->appendLimit($data, $sql);

        $statement = $this->execute($sql, $data['params']);

        return $statement->rowCount();
    }

    public function update(AbstractBuilder $builder)
    {
        $data = $builder->getArrayCopy();
        $sql = sprintf('UPDATE %s.%s SET ', $data['db'], $data['table']);

        list($params, $part) = $this->makeAssignStatements($builder, $data['content']);

        $sql .= $part;

        $sql = $this->appendWhere($data, $sql);
        $sql = $this->appendOrder($data, $sql);
        $sql = $this->appendLimit($data, $sql);

        if (isset($data['params'])) {
            $params += $data['params'];
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
     * @param $data
     * @param $sql
     * @return string
     */
    protected function appendWhere($data, $sql)
    {
        if (isset($data['where'])) {
            $sql .= ' WHERE ' . $data['where'];
        }
        return $sql;
    }

    /**
     * @param $data
     * @param $sql
     * @return string
     */
    protected function appendOrder($data, $sql)
    {
        if (isset($data['order'])) {
            $sql .= ' ORDER BY ' . $data['order'];
        }

        return $sql;
    }

    /**
     * @param $data
     * @param $sql
     * @return string
     */
    protected function appendLimit($data, $sql)
    {
        if (isset($data['limit'])) {
            $sql .= ' LIMIT ';
            if (isset($data['offset'])) {
                $sql .= $data['offset'] . ',';
            }
            $sql .= $data['limit'];
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
     * @param AbstractBuilder $builder
     * @param array $content
     * @return array
     */
    protected function makeAssignStatements(AbstractBuilder $builder, array $content)
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
        $data = $builder->getArrayCopy();
        $params = [];
        $fields = [];
        foreach ($data['content'] as $field => $value) {
            $key = ':v_' . count($params) . '_';
            $params[$key] = $value;
            $fields[$this->quote($field)] = $key;
        }

        $sql = sprintf('INSERT INTO %s.%s %s VALUES %s',
            $data['db'],
            $data['table'],
            $this->wrap($this->comma(array_keys($fields))),
            $this->wrap($this->comma(array_values($fields)))
        );

        return $this->execute($sql, $params + $data['params']);
    }
}
