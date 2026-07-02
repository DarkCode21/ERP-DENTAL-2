<?php
/**
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\IaeEmpresa;
use FacturaScripts\Plugins\Ticketbai\Lib\TbaiSignature;

/**
 * @author Daniel Fernández Giménez             <hola@danielfg.es>
 * @author Alayn Gortazar Huete - Barnetik Koop <alayn@barnetik.com>
 */
class Empresa
{
    public function delete(): Closure
    {
        return function () {
            $signatureFile = TbaiSignature::getSignatureFile($this);
            if (!empty($signatureFile) && !is_dir($signatureFile)) {
                unlink($signatureFile);
            }
        };
    }

    public function getIAEs(): Closure
    {
        return function () {
            $where = [new DataBaseWhere('idempresa', $this->idempresa)];
            return IaeEmpresa::all($where, [], 0, 0);
        };
    }

    public function getPaymentMethods(): Closure
    {
        return function () {
            $where = [new DataBaseWhere('idempresa', $this->idempresa)];
            return FormaPago::all($where, ['descripcion' => 'ASC'], 0, 0);
        };
    }

    public function test(): Closure
    {
        return function () {
            // escapamos el html de las nuevas columnas
            $this->tbai_developer = Tools::noHtml($this->tbai_developer);
            $this->tbai_license = Tools::noHtml($this->tbai_license);
            $this->tbai_password = Tools::noHtml($this->tbai_password);
            $this->tbai_supplier = Tools::noHtml($this->tbai_supplier);
            $this->tbai_version = Tools::noHtml($this->tbai_version);

            if (empty($this->tbai_signature_nif)) {
                $this->tbai_signature_nif = $this->cifnif;
            }
        };
    }
}
