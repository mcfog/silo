<?php namespace Silo\Builder;

use Silo\Interfaces\IModel;

abstract class AbstractSchema extends AbstractBuilder
{
    public static function save(IModel $model)
    {
        $q = static::query();
        if ($model->isRowExists()) {
            $q->content = $model->getRowDataForUpdate();

            if (empty($q->content)) {
                return 0;
            }

            return $q->locate($model)->runUpdate();
        } else {
            $q->content = $model->getRowData();
            return $model->onInserted($q->runInsert());
        }
    }

    /**
     * @param $target
     * @return $this
     * @throws \Exception
     */
    public function locate($target)
    {
        $driver = static::getDriver();
        $target = $this->convertLocateTarget($target);

        switch (count(static::$pk)) {
            case 0:
                throw new \Exception('PK undefined');
            default:
                array_walk(static::$pk, function ($fld) use ($target, $driver) {
                    call_user_func([$this, 'andWhere'], $driver->quote($fld), '=', $this->param($target[$fld]));
                });
        }

        return $this;
    }

    /**
     * @param $target
     * @return array
     */
    protected function convertLocateTarget($target)
    {
        switch (true) {
            case $target instanceof IModel:
                $target = $target->getRowData();
                break;
            case is_array($target) || ($target instanceof \ArrayAccess):
                break;
            default:
                throw new \InvalidArgumentException;
        }

        return $target;
    }

    public static function remove(IModel $model)
    {
        $q = static::query();

        return $q->locate($model)->limit(1)->runDelete();
    }

    public function locateMulti(array $targets)
    {
        $driver = static::getDriver();

        $keys = array_map(
            function ($target) use ($driver) {
                $target = $this->convertLocateTarget($target);

                $values = array_map(function ($fld) use ($target) {
                    return $this->param($target[$fld]);
                }, static::$pk);

                return $driver->wrap($driver->comma($values));
            }, $targets);

        return $this->andWhere($driver->wrap($driver->comma(static::$pk)), 'IN', $driver->wrap($driver->comma($keys)));
    }

    /**
     * @return IModel|null
     */
    public function findFirst()
    {
        foreach ($this->limit(1)->find() as $result) {
            return $result;
        }

        return null;
    }

    /**
     * @return IModel[]
     */
    public function find()
    {
        foreach ($this->runSelect() as $row) {
            yield call_user_func($this->getHydrator(), $row, true);
        }
    }

    /**
     * @return callable
     */
    abstract protected function getHydrator();
}
