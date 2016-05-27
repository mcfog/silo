<?php
namespace Silo\Interfaces;

use Silo\Builder\AbstractBuilder;

interface IConnectionFactory
{
    /**
     * @param AbstractBuilder $builder
     * @return IDriver $connection
     */
    public function getConnection(AbstractBuilder $builder);
}