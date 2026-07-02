<?php
/**
 * This file is part of PagosMultiples plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 * PagosMultiples  Copyright (C) 2020-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\PagosMultiples\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\AgentSettlement;
use FacturaScripts\Dinamic\Model\CustomerBankCheck;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\PagoCliente;

/**
 * Class that manages the data model of the customer receipts group.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class CustomerReceiptGroup extends Base\PaymentReceiptGroup
{

    use ModelTrait;

    /**
     * Link to agent model.
     *
     * @var integer
     */
    public $idagent;

    /**
     * Get the agent associated to group.
     *
     * @return Agente
     */
    public function getAgent(): Agente
    {
        $agent = new Agente();
        $agent->loadFromCode($this->idagent);
        return $agent;
    }

    /**
     * Get the list of all the custom payments included in the multiple payment.
     *
     * @return CustomerBankCheck[]
     */
    public function getBankChecks(): array
    {
        $bankCheck = new CustomerBankCheck();
        $where = [new DataBaseWhere('idmultiple', $this->id)];
        return $bankCheck->all($where, [], 0, 0);
    }

    /**
     * Get a new receipt and load his data.
     *
     * @param int $code
     * @return PagoCliente
     */
    public function getPayment($code = 0): PagoCliente
    {
        $payment = new PagoCliente();
        if (false === empty($code)) {
            $payment->loadFromCode($code);
        }
        return $payment;
    }

    /**
     * Get a new receipt and load his data.
     *
     * @param int $code
     * @return ReciboCliente
     */
    public function getReceipt($code = 0): ReciboCliente
    {
        $receipt = new ReciboCliente();
        if (false === empty($code)) {
            $receipt->loadFromCode($code);
        }
        return $receipt;
    }

    /**
     * Get the list of all the receipts included in the multiple payment.
     *
     * @param array $orderby
     * @return ReciboCliente[]
     */
    public function getReceipts(array $orderby = []): array
    {
        $receipt = new ReciboCliente();
        $where = [new DataBaseWhere('idmultiple', $this->id)];
        return $receipt->all($where, $orderby, 0, 0);
    }

    /**
     * Get the agent settlement associated to group.
     *
     * @return AgentSettlement
     */
    public function getSettlement(): AgentSettlement
    {
        $settlement = new AgentSettlement();
        if (!empty($this->idagent)) {
            $settlement->loadFromCode($this->id);
        }
        return $settlement;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'ppmm_customer_receipts';
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     *
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, 'ListFacturaCliente?activetab=' . $list);
    }
}
