<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Model\Join;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Model\Base\JoinModel;
use FacturaScripts\Dinamic\Model\PortalFavorite as MasterModel;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalFavorite extends JoinModel
{
    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->setMasterModel(new MasterModel());
    }

    public function count(array $where = []): int
    {
        $fields = '';
        foreach ($this->getFields() as $key => $value) {
            $fields .= $value . ' ' . $key . ', ';
        }

        // buscamos en caché
        $cacheKey = 'join-model-' . md5($this->getSQLFrom()) . '-count';
        if (empty($where)) {
            $count = Cache::get($cacheKey);
            if (is_numeric($count)) {
                return $count;
            }
        }

        $arrayWhere = [];
        $arrayHaving = [];
        $columns = self::$dataBase->getColumns('portal_favorites');

        // recorremos el where eliminando los filtros que no son columnas de la tabla
        foreach ($where as $filter) {
            foreach ($columns as $key => $column) {
                if ($key == $filter->fields) {
                    $arrayWhere[] = $filter;
                    continue 2;
                }
            }

            $arrayHaving[] = $filter;
        }

        // creamos la sql del where y having
        $sqlWhere = DataBaseWhere::getSQLWhere($arrayWhere);
        $sqlHaving = DataBaseWhere::getSQLWhere($arrayHaving);

        // cambiamos WHERE por HAVING
        $sqlHaving = str_replace('WHERE', 'HAVING', $sqlHaving);

        $sql = 'SELECT ' . $fields . 'COUNT(id) count_total'
            . ' FROM ' . $this->getSQLFrom()
            . $sqlWhere
            . ' GROUP BY ' . $this->getGroupFields()
            . $sqlHaving;

        $data = self::$dataBase->select($sql);
        $count = count($data);
        $final = $count;

        // guardamos en caché
        if (empty($where)) {
            Cache::set($cacheKey, $final);
        }

        return $final;
    }

    public function primaryColumnValue()
    {
        return $this->idcontacto;
    }

    protected function getFields(): array
    {
        return [
            'idcontacto' => 'idcontacto',
            'creation_date' => 'MIN(creation_date)',
            'products' => 'COUNT(id)',
        ];
    }

    protected function getGroupFields(): string
    {
        return 'idcontacto';
    }

    protected function getSQLFrom(): string
    {
        return 'portal_favorites';
    }

    protected function getTables(): array
    {
        return ['portal_favorites'];
    }
}