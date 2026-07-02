<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Lib\DocumentosRecurrentes;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Dinamic\Model\Base\DocRecurring;
use FacturaScripts\Dinamic\Model\DocTransformation;

/**
 * Description of DocRecurringTools
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class DocRecurringTools
{
    private const MODEL_NAMESPACE = '\\FacturaScripts\\Dinamic\\Model\\';

    /**
     *
     * @var DocRecurring
     */
    private $docRecurring;

    /**
     *
     * @param DocRecurring $docRecurring
     */
    public function __construct(&$docRecurring)
    {
        $this->docRecurring = $docRecurring;
    }

    /**
     * Calculate the last date from generated recurring documents.
     *
     * @return string|null
     */
    public function calculateLastDate()
    {
        $children = $this->childrenDocuments(1);
        if (empty($children)) {
            return null;
        }

        $docDate = strtotime($children[0]->fecha);
        $startDate = strtotime($this->docRecurring->startdate);
        if ($docDate < $startDate) {
            return null;
        }

        return $children[0]->fecha;
    }

    /**
     * Calculate the next date to generate the recurring document.
     *   - if it's manual      : there is no next date (return null)
     *   - force first document: use the date as the first document, if exists
     *   - its first document  : use the date as the first document, if exists
     *   - if it's monthly     : calculate respecting the day of the month
     *   - else                : calculate adding the period
     *
     * @return string|null
     */
    public function calculateNextDate()
    {
        if ($this->docRecurring->termtype === DocRecurring::TERM_TYPE_MANUAL) {
            return null;
        }

        if ($this->docRecurring->firstforce || empty($this->docRecurring->lastdate)) {
            if (false === empty($this->docRecurring->firstdate)) {
                return $this->docRecurring->firstdate;
            }
        }

        switch ($this->docRecurring->termtype) {
            case DocRecurring::TERM_TYPE_MONTHS:
                $prevDate = $this->getPrevDate();
                $nextDate = $this->getNextMonthDate($prevDate);
                break;

            default:
                $prevDate = $this->getPrevDate();
                $nextDate = strtotime($this->getTermTypeDateFormat(), $prevDate);
                break;
        }

        if (empty($this->docRecurring->enddate) || strtotime($this->docRecurring->enddate) >= $nextDate) {
            return date(DocRecurring::DATE_STYLE, $nextDate);
        }

        return null;
    }

    /**
     * Returns all children documents of this one.
     *
     * @param string $sqlWhere
     * @param int $limit
     * @return BussinesDocument[]
     */
    public function childrenAllDocuments(string $sqlWhere = '', int $limit = 0)
    {
        if (empty($this->docRecurring->id)) {
            return [];
        }

        $sql = "SELECT DISTINCT model2, iddoc2"
            . " FROM " . DocTransformation::tableName()
            . " WHERE model1 = '" . $this->docRecurring->modelClassName() . "'"
            .   " AND iddoc1 = " . $this->docRecurring->id
            .   " " . $sqlWhere
            . " ORDER BY model2 ASC, iddoc2 DESC";

        $children = [];
        $dataBase = new DataBase();
        foreach ($dataBase->selectLimit($sql, $limit, 0) as $row) {
            $newModelClass = self::MODEL_NAMESPACE . $row['model2'];
            $newModel = new $newModelClass();
            if ($newModel->loadFromCode($row['iddoc2'])) {
                $children[] = $newModel;
            }
        }
        return $children;
    }

    /**
     * We calculate the next date for monthly calculation.
     *
     * If it is the last day of the month, we adjust the starting day
     * to the last day of the month.
     * Otherwise we take the same day as the start date.
     *
     * Once the theoretical day has been calculated go back the necessary
     * days until the date is correct with the calculated day.
     *
     * @param int $prevDate
     * @return int
     */
    public function getNextMonthDate($prevDate)
    {
        $year = date('Y', $prevDate);
        $month = date('m', $prevDate) + $this->docRecurring->termunits;
        if ($month > 12) {
            $month = $month - 12;
            ++$year;
        }

        $startDate = (empty($this->docRecurring->firstdate))
            ? strtotime($this->docRecurring->startdate)
            : strtotime($this->docRecurring->firstdate);

        $day = ($startDate == strtotime(date("Y-m-t", $startDate)))
            ? 31
            : date('d', strtotime($this->docRecurring->startdate));

        while (false === checkdate($month, $day, $year)) {
            $day = ($day > 2) ? ($day - 1) : 1;
        }

        return strtotime($year . '-' . $month . '-' . $day);
    }

    /**
     * Returns all children documents of this one,
     * of the document type selected.
     *
     * @param int $limit
     * @return array
     */
    private function childrenDocuments(int $limit = 0)
    {
        $where = "AND model2 = '" . $this->docRecurring->generatedoc . "'";
        return $this->childrenAllDocuments($where, $limit);
    }

    /**
     * Calculates the date from which to start for the application of the period.
     * If there is a previous date:
     *   - if it is daily then use last date
     *   - else take that last date but adjust the day to the initial day
     *     of the period, in case any adjustment has been applied.
     *     This happens through the difference in the final days
     *     between months/years.
     *
     * @return int
     */
    private function getPrevDate(): int
    {
        if (empty($this->docRecurring->lastdate)) {
            return strtotime($this->docRecurring->startdate);
        }

        if ($this->docRecurring->termtype === DocRecurring::TERM_TYPE_DAYS) {
            return strtotime($this->docRecurring->lastdate);
        }

        $selectDate = empty($this->docRecurring->firstdate)
            ? $this->docRecurring->startdate
            : $this->docRecurring->firstdate;

        $year = date('Y', strtotime($this->docRecurring->lastdate));
        $month = date('m', strtotime($this->docRecurring->lastdate));
        $day = date('d', strtotime($selectDate));
        while (false === checkdate($month, $day, $year)) {
            $day = ($day > 2) ? ($day - 1) : 1;
        }
        return strtotime($year . '-' . $month . '-' . $day);
    }

    /**
     *
     * @return string
     */
    private function getTermTypeDateFormat(): string
    {
        $format = '+' . $this->docRecurring->termunits . ' ';
        switch ($this->docRecurring->termtype) {
            case DocRecurring::TERM_TYPE_DAYS:
                return $format . ' days';

            case DocRecurring::TERM_TYPE_WEEKS:
                return $format . ' weeks';

            case DocRecurring::TERM_TYPE_MONTHS:
                return $format . ' months';

            default:
                return '';
        }
    }
}
