<?php namespace Silo\Traits;

trait SchemaTrait
{
    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function eq($field, $value)
    {
        return $this->whereFieldOp($field, '=', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    protected function whereFieldOp($field, $op, $value)
    {
        return $this->andWhere($field, $op, $this->param($value));
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function neq($field, $value)
    {
        return $this->whereFieldOp($field, '<>', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function gt($field, $value)
    {
        return $this->whereFieldOp($field, '>', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function lt($field, $value)
    {
        return $this->whereFieldOp($field, '<', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */

    public function gte($field, $value)
    {
        return $this->whereFieldOp($field, '>=', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function lte($field, $value)
    {
        return $this->whereFieldOp($field, '<=', $value);
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function in($field, array $values)
    {
        if (empty($values)) {
            return $this->andWhere('1=0');
        } else {
            return $this->andWhere($field, 'IN', $this->params($values));
        }
    }
}
