<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2022 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Model\Base;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\CompanyRelationTrait;
use FacturaScripts\Core\Model\Base\CurrencyRelationTrait;
use FacturaScripts\Core\Model\Base\PaymentRelationTrait;
use FacturaScripts\Core\Model\Base\SerieRelationTrait;
use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Plugins\DocumentosRecurrentes\Lib\DocumentosRecurrentes\DocRecurringTools;

/**
 * Model template for DocRecurring Purchase and Sale
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
abstract class DocRecurring extends ModelClass
{

    use CompanyRelationTrait,
        CurrencyRelationTrait,
        PaymentRelationTrait,
        SerieRelationTrait;

    public const TERM_TYPE_DAYS = 1;
    public const TERM_TYPE_WEEKS = 2;
    public const TERM_TYPE_MONTHS = 3;
    public const TERM_TYPE_MANUAL = 99;

    /**
     * Link to Almacen model
     *
     * @var string
     */
    public $codalmacen;

    /**
     * Days left to generate the next document.
     *
     * @var int|null
     */
    public $days;

    /**
     * End date of the automatic generation period.
     *
     * @var string
     */
    public $enddate;

    /**
     * Date for first document.
     *
     * @var string
     */
    public $firstdate;

    /**
     * Indicates if the generation date has to be forced based on
     * the date of the first document.
     *
     * @var bool
     */
    public $firstforce;

    /**
     * Percentage of total for the first document.
     *
     * @var float
     */
    public $firstpct;

    /**
     * Identifier of the type of document to be generated.
     *
     * @var string
     */
    public $generatedoc;

    /**
     * Primary Key.
     *
     * @var int
     */
    public $id;

    /**
     * Link to EstadoDocumento
     *
     * @var int
     */
    public $idstatus;

    /**
     * Date indicating the last time the document was generated.
     *
     * @var string
     */
    public $lastdate;

    /**
     * Human description that identifies the template.
     *
     * @var string
     */
    public $name;

    /**
     * Date that indicates when the next automatic generation
     * of the document will be.
     *
     * @var string
     */
    public $nextdate;

    /**
     * User who created this document. User model.
     *
     * @var string
     */
    public $nick;

    /**
     * Any kind of note, clarification or reminder.
     *
     * @var text
     */
    public $notes;

    /**
     * Notes or observations carried over to the new generated document.
     *
     * @var text
     */
    public $notesdocument;

    /**
     * Type of term for the automatic generation of the document.
     * See TERM_TYPE_* conts for more info.
     *
     * @var int
     */
    public $termtype;

    /**
     * Amount that is applied to the term type.
     *
     * @var int
     */
    public $termunits;

    /**
     * Indicates if the new document is sent by email automatically.
     *
     * @var bool
     */
    public $sendmail;

    /**
     * Start date of the automatic generation period.
     *
     * @var string
     */
    public $startdate;

    /**
     * Returns the lines associated with the document.
     */
    abstract public function getLines();

    /**
     * Returns a new line for the document.
     */
    abstract public function getNewLine(array $data = [], array $exclude = ['id', 'iddoc']);

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->days = null;
        $this->firstforce = false;
        $this->firstpct = 100;
        $this->termtype = self::TERM_TYPE_MONTHS;
        $this->termunits = 1;
        $this->sendmail = false;
        $this->startdate = date(self::DATE_STYLE);

        // Default values.
        $appSettings = $this->toolBox()->appSettings();
        $this->codserie = $appSettings->get('default', 'codserie');
        $this->coddivisa = $appSettings->get('default', 'coddivisa');
        $this->codalmacen = $appSettings->get('default', 'codalmacen');
        $this->codpago = $appSettings->get('default', 'codpago');
    }

    /**
     * Returns all avaliable status for this type of document.
     * Return empty result becouse Document Recurring don't have status.
     *
     * @return []
     */
    public function getAvailableStatus(): array
    {
        return [];
    }

    /**
     * Assign the values of the $data array to the model properties.
     *
     * @param array $data
     * @param array $exclude
     */
    public function loadFromData(array $data = array(), array $exclude = array())
    {
        parent::loadFromData($data, $exclude);
        if (($this->termtype !== self::TERM_TYPE_MANUAL) && (!empty($this->nextdate))) {
            $currentDay = date(self::DATE_STYLE);
            $this->days = $this->daysBetween($currentDay, $this->nextdate);
        }
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Returns the name of the column that describes the model, such as name, description...
     *
     * @return string
     */
    public function primaryDescriptionColumn(): string
    {
        return 'name';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        if ($this->firstforce && empty($this->firstdate)) {
            $this->toolBox()->i18nLog()->warning('firstdate-can-not-be-null');
            return false;
        }

        if ($this->firstpct > 100.00) {
            $this->firstpct = 100.00;
        }

        if ($this->firstpct < 0.00) {
            $this->firstpct = 0.00;
        }

        $tools = new DocRecurringTools($this);
        $this->name = $this->toolBox()->utils()->noHtml($this->name);
        $this->notes = $this->toolBox()->utils()->noHtml($this->notes);
        $this->notesdocument = $this->toolBox()->utils()->noHtml($this->notesdocument);
        $this->idempresa = $this->calculateCompany();
        $this->lastdate = $tools->calculateLastDate();
        $this->nextdate = $tools->calculateNextDate();
        return parent::test();
    }

    /**
     * Calculate id company from selected warehouse
     *
     * @return int
     */
    private function calculateCompany()
    {
        $warehouse = new Almacen();
        $warehouse->loadFromCode($this->codalmacen);
        return $warehouse->idempresa;
    }

    /**
     * Calculate number days between two dates
     *
     * @param string $start
     * @param string $end
     * @param bool $increment
     * @return integer
     */
    private function daysBetween($start, $end, $increment = false): int
    {
        if (empty($start) || empty($end)) {
            return 0;
        }

        $diff = strtotime($end) - strtotime($start);
        $result = ceil($diff / 86400);
        if ($increment) {
            ++$result;
        }
        return $result;
    }
}
