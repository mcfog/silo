<?php namespace Silo\Interfaces;

use Silo\Builder\AbstractBuilder;

interface IDriver
{
    /**
     * @param AbstractBuilder $builder
     * @return array
     */
    public function selectFirst(AbstractBuilder $builder);

    /**
     * @param AbstractBuilder $builder
     * @return \Traversable
     */
    public function select(AbstractBuilder $builder);

    /**
     * @param AbstractBuilder $builder
     * @return int
     */
    public function count(AbstractBuilder $builder);

    /**
     * @param \Silo\Builder\AbstractBuilder $builder
     * @return int
     */
    public function delete(AbstractBuilder $builder);


    /**
     * @param AbstractBuilder $builder
     * @return int affected row count
     */
    public function update(AbstractBuilder $builder);

    /**
     * @param AbstractBuilder $builder
     * @param array $pk
     * @return mixed last insert id
     */
    public function insert(AbstractBuilder $builder, array $pk);

    /**
     * @param string[] $values
     * @return string
     */
    public function comma(array $values);

    /**
     * @param string $inner
     * @return string
     */
    public function wrap($inner);

    /**
     * @param string $inner
     * @return string
     */
    public function quote($inner);
}