<?php namespace Silo\Builder;


class QueryBuilder extends AbstractBuilder
{
    public function select($fields)
    {
        $this->fields = $fields;
        return $this;
    }

    public function from($table)
    {
        $this->table = $table;
        return $this;
    }

    public function content(array $data)
    {
        $this->content = $data;
        return $this;
    }
}
