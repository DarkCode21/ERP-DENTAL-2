<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Lib\DocumentosRecurrentes;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Lib\CodePatterns as CodePatternsParent;

/**
 * Class to apply patterns.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class CodePatterns extends CodePatternsParent
{

    /**
     * Transform a text according to patterns and indicated format.
     * The options parameter can contain the name of the field to use for each pattern.
     * If not reported, field names will be used by default.
     * eg: ['date' => 'creationdate']
     *
     * @param string $text
     * @param object $model
     * @param array $options
     *
     * @return string
     */
    public static function trans(string $text, &$model, array $options = array()): string {
        $date = $model->{'fecha'} ?? date(self::DATE_STYLE);
        $newText = strtr($text, [
            '{NUMSEMANA}' => date('W', strtotime($date)),
            '{01FECHA}' => self::firstDayMonth($date),
            '{31FECHA}' => self::lastDayMonth($date),
            '{01FECHA+1}' => self::firstDayMonth(date(self::DATE_STYLE, self::nextMonth($date))),
            '{31FECHA+1}' => self::lastDayMonth(date(self::DATE_STYLE, self::nextMonth($date))),
            '{NOMBREMES+1}' => self::nextMonthName($date),
            '{NOMBREMESANYO+1}' => self::nextMonthYearName($date),
        ]);
        return parent::trans($newText, $model, $options);
    }

    /**
     * Return the date located at first day of the month.
     *
     * @param string $date
     * @return string
     */
    private static function firstDayMonth($date)
    {
        return date('01-m-Y', strtotime($date));
    }

    /**
     * Calculate last day for one month.
     *
     * @param string $date
     * @return string
     */
    private static function lastDayMonth($date)
    {
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));
        $lastDay = date('d', mktime(0, 0, 0, $month+1, 1, $year)-1);
        return $lastDay . '-' . $month . '-' . $year;
    }

    /**
     * Add one month to date.
     *
     * @param string $date
     * @return int
     */
    private static function nextMonth($date)
    {
        return strtotime('+1 month', strtotime($date));
    }

    /**
     * Return name of the next month of date.
     *
     * @param string $date
     * @return string
     */
    private static function nextMonthName($date)
    {
        $nextDate = self::nextMonth($date);
        return ToolBox::i18n()->trans(strtolower(date('F', $nextDate)));
    }

    /**
     * Return period in formar mounth/year of the next month of date.
     *
     * @param string $date
     * @return string
     */
    private static function nextMonthYearName($date)
    {
        $nextDate = self::nextMonth($date);
        return ToolBox::i18n()->trans(strtolower(date('F', $nextDate)))
            . '/'
            . date('Y', strtotime($nextDate));
    }
}
