<?php namespace Silo\Builder;
use Silo\Interfaces\IModel;

/**
 * User: mcfog
 * Date: 14/12/28
 */
class Schema extends AbstractSchema
{
    /**
     * @var string
     */
    protected $dbName;
    /**
     * @var string
     */
    protected $tableName;
    /**
     * @var string
     */
    protected $modelName;

    /**
     * Schema constructor.
     * @param string $dbName
     * @param string $tableName
     * @param string $modelName
     */
    public function __construct($dbName, $tableName, $modelName, array $input = [])
    {
        $this->dbName = $dbName;
        $this->tableName = $tableName;
        $this->modelName = $modelName;

        if (!class_exists($modelName) || !is_subclass_of($modelName, IModel::class)) {
            throw new \InvalidArgumentException($modelName . ' is not a model class');
        }

        parent::__construct($input + [
                'table' => $this->tableName,
                'db' => $this->dbName,
            ]
        );
    }

    protected function getHydrator()
    {
        return [$this->modelName, 'hydrator'];
    }
}
