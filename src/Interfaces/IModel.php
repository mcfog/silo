<?php namespace Silo\Interfaces;

interface IModel
{
    /**
     * hydrate model instance
     *
     * @param array $row
     * @param bool $exist
     * @return static
     */
    public static function hydrate(array $row, $exist);

    /**
     * get data stored in db
     *
     * @return array
     */
    public function getRowData();

    /**
     * check whether this row exists in db
     *
     * @return bool
     */
    public function isRowExists();

    /**
     * @param null $id
     * @return mixed
     */
    public function onInserted($id = null);

    /**
     * get changed data
     *
     * @return array
     */
    public function getRowDataForUpdate();
}
