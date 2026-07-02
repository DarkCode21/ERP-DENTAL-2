<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones\Lib\Amortizaciones;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Amortizaciones\Model\AmortizacionTabla;
use FacturaScripts\Plugins\Amortizaciones\Model\AmortizacionSubcuenta;
use FacturaScripts\Plugins\Amortizaciones\Model\Join\AmortizacionSubcuenta as JoinAmortizacionSubcuenta;

/**
 * Description of AmortizationInfo
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AmortizationInfo
{

    public function getSubAccountGroupType(): array
    {
        return AmortizacionSubcuenta::groupTypeList();
    }

    public function getSubAccountGroup(): array
    {
        return AmortizacionSubcuenta::groupList();
    }

    public function getSubAccountDescription(int $grouptype): array
    {
        $where = [ new DataBaseWhere('grouptype', $grouptype) ];
        $order = [ 'groupid' => 'ASC', 'code' => 'ASC' ];
        $model = new JoinAmortizacionSubcuenta();

        $result = [];
        $rows = 0;
        $index = 0;
        $groupid = 0;
        foreach ($model->all($where, $order, 0, 0) as $data) {
            if ($rows === 0 || ($index === $rows && $groupid == $data->groupid)) {
                $groupid = $data->groupid;
                foreach ($this->getSubAccountGroup() as $key => $value) {
                    $result[$rows][$key][0] = '';
                    $result[$rows][$key][1] = '';
                }
                ++$rows;
            }

            if ($groupid != $data->groupid) {
                $groupid = $data->groupid;
                $index = 0;
            }

            $result[$index][$data->groupid][0] = $data->code;
            $result[$index][$data->groupid][1] = $data->description;
            ++$index;
        }
        return $result;
    }

    public function getTableGroupType(): array
    {
        $i18n = Tools::lang();
        return [
            AmortizacionTabla::GROUP_TYPE_SIMPLE => $i18n->trans('simplified-direct-estimation'),
            AmortizacionTabla::GROUP_TYPE_NORMAL => $i18n->trans('normal-direct-estimate'),
        ];
    }

    public function getTableGroup(int $grouptype): array
    {
        $i18n = Tools::lang();
        switch ($grouptype) {
        case AmortizacionTabla::GROUP_TYPE_SIMPLE:
            return [
                AmortizacionTabla::GROUP_INVESTMENTS => $i18n->trans('type-investments-assets'),
            ];

        case AmortizacionTabla::GROUP_TYPE_NORMAL:
            return [
                AmortizacionTabla::GROUP_CIVIL => $i18n->trans('civil-works'),
                AmortizacionTabla::GROUP_CENTRAL => $i18n->trans('centrals'),
                AmortizacionTabla::GROUP_BUILDINGS => $i18n->trans('buildings'),
                AmortizacionTabla::GROUP_FACILITIES => $i18n->trans('facilities'),
                AmortizacionTabla::GROUP_TRANSPORT => $i18n->trans('transport-elements'),
                AmortizacionTabla::GROUP_FURNITURE => $i18n->trans('furniture-fixtures'),
                AmortizacionTabla::GROUP_COMPUTER => $i18n->trans('electronic-computer'),
            ];
        }
    }

    public function getTableDescription(int $grouptype, int $group): array
    {
        $where = [
            new DataBaseWhere('grouptype', $grouptype),
            new DataBaseWhere('groupid', $group),
        ];
        $order = [ 'name' => 'ASC' ];
        $model = new AmortizacionTabla();
        return $model->all($where, $order, 0, 0);
    }
}