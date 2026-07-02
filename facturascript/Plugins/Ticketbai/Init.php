<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai;

use FacturaScripts\Core\Base\AjaxForms\SalesLineHTML;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Html;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Controller\SendTicket;
use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Lib\RegimenIVA;
use FacturaScripts\Dinamic\Lib\Tickets\TicketBai;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\Ticketbai\Lib\TbaiTools;
use Twig\TwigFunction;

require_once __DIR__ . '/vendor/autoload.php';

final class Init extends InitClass
{
    public function init(): void
    {
        // añadimos las extensiones
        $this->loadExtension(new Extension\Controller\DocumentStitcher());
        $this->loadExtension(new Extension\Controller\ListFacturaCliente());
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
        $this->loadExtension(new Extension\Controller\EditEmpresa());
        $this->loadExtension(new Extension\Controller\EditSettings());
        $this->loadExtension(new Extension\Model\Empresa());
        $this->loadExtension(new Extension\Model\FacturaCliente());

        Html::addFunction(new TwigFunction('wrapText', function ($text, $width = 50) {
            return wordwrap($text, $width, "<br>", true);
        }));

        // tickets
        if (Plugins::isEnabled('Tickets')) {
            SendTicket::addFormat(TicketBai::class, 'FacturaCliente', 'ticketbai');
        }

        // añadimos los Mods
        SalesLineHTML::addMod(new Mod\SalesLineMod());

        // añadimos los regímenes de IVA
        RegimenIVA::addException('ES_141', 'es-tax-exception-141');

        // evitamos copiar estás columnas de la factura al copiar, duplicar o rectificar la factura
        BusinessDocument::dontCopyField('tbaicodbar');
        BusinessDocument::dontCopyField('tbai_canceled');
        BusinessDocument::dontCopyField('tbai_canceled_date');
        BusinessDocument::dontCopyField('tbaiurl');
        BusinessDocument::dontCopyField('tbaisignature');
        BusinessDocument::dontCopyField('tbai_sent_date');
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        $db = new DataBase();
        $this->setPlantillasPDF();
        $this->copySettingsToCompany();
        $this->updateSignedInvoices($db);
        $this->updateAnnulledInvoices($db);
        $this->removeColumnSignatureDate($db);
        $this->removeColumnTbaiXmlSigned($db);

        if (Kernel::version() >= 2023.03) {
            $this->updateColumnExceptionVat($db);
            $this->updateProductService($db);
        }
    }

    // copiamos los datos del settings a las empresas
    // solo si la empresa no tiene datos de Ticketbai
    protected function copySettingsToCompany(): void
    {
        $fieldsSettings = ['signature', 'license', 'developer', 'supplier', 'version', 'password', 'startdatesign'];
        $fieldsCompany = [
            'tbai_signature', 'tbai_license', 'tbai_developer', 'tbai_supplier', 'tbai_version', 'tbai_password',
            'tbai_startdatesign'
        ];

        foreach (Empresas::all() as $company) {
            // si la empresa no es del país vasco, continuamos
            if (false === TbaiTools::isBasqueCountryCompany($company)) {
                continue;
            }

            // si la empresa ya tiene datos de Ticketbai, continuamos
            if (TbaiTools::checkCompanyLicense($company)) {
                continue;
            }

            // copiamos los datos de settings a la empresa
            foreach ($fieldsSettings as $index => $value) {
                $company->{$fieldsCompany[$index]} = Tools::settings('ticketbai', $value);
            }
            $company->tbai_debugmode = (bool)Tools::settings('ticketbai', 'debugmode', false);
            $company->save();
        }
    }

    protected function removeColumnSignatureDate(DataBase $db): void
    {
        // si la columna tbai_signature_date en las facturas de cliente no existe, terminamos
        $sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . FS_DB_NAME
            . "' AND TABLE_NAME = 'facturascli' AND COLUMN_NAME = 'tbai_signature_date';";
        if (false === empty($db->select($sql))) {
            // eliminamos la columna tbai_signature_date
            $sql = "ALTER TABLE facturascli DROP COLUMN tbai_signature_date;";
            $db->exec($sql);
        }
    }

    protected function removeColumnTbaiXmlSigned(DataBase $db): void
    {
        // si la columna tbaixmlsigned en las facturas de cliente no existe, terminamos
        $sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . FS_DB_NAME
            . "' AND TABLE_NAME = 'facturascli' AND COLUMN_NAME = 'tbaixmlsigned';";
        if (false === empty($db->select($sql))) {
            // eliminamos la columna tbaixmlsigned
            $sql = "ALTER TABLE facturascli DROP COLUMN tbaixmlsigned;";
            $db->exec($sql);
        }
    }

    protected function setPlantillasPDF(): void
    {
        if (Plugins::isEnabled('PlantillasPDF')) {
            Tools::settings('plantillaspdf', 'qrfield', 'ticketbai');
            Tools::settings('plantillaspdf', 'qrsize', 160);
            Tools::settings('plantillaspdf', 'qrpositionx', 130);
            Tools::settings('plantillaspdf', 'qrpositiony', 15);
            Tools::settingsSave();
        }
    }

    private function updateAnnulledInvoices(DataBase $db): void
    {
        // si no existe la columna tbai_canceled en las facturas de cliente, terminamos
        $sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . FS_DB_NAME
            . "' AND TABLE_NAME = 'facturascli' AND COLUMN_NAME = 'tbai_canceled';";
        if (false === empty($db->select($sql))) {
            return;
        }

        // todas las facturas que tengan algo en la columna tbaicodbar_canceled
        // ponemos la columna tbai_canceled a true
        $sql = "UPDATE facturascli SET tbai_canceled = 1 WHERE tbaicodbar_canceled IS NOT NULL;";
        $db->exec($sql);

        // eliminamos la columna tbaicodbar_canceled
        $sql = "ALTER TABLE facturascli DROP COLUMN tbaicodbar_canceled;";
        $db->exec($sql);
    }

    private function updateColumnExceptionVat(DataBase $db): void
    {
        $tables = ['productos', 'lineaspresupuestoscli', 'lineaspedidoscli', 'lineasalbaranescli', 'lineasfacturascli'];
        foreach ($tables as $table) {
            $existIVA = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . FS_DB_NAME
                . "' AND TABLE_NAME = '" . $table . "' AND COLUMN_NAME = 'excepcioniva';";
            $existVAT = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . FS_DB_NAME
                . "' AND TABLE_NAME = '" . $table . "' AND COLUMN_NAME = 'exceptionvat';";

            // comprobamos si existe la columna excepcioniva en la tabla
            // si no existe, pero si existe la columna exceptionvat
            // renombramos la columna exceptionvat por excepcioniva de la tabla
            if (empty($db->select($existIVA)) && false === empty($db->select($existVAT))) {
                $sql = "ALTER TABLE " . $table . " CHANGE exceptionvat excepcioniva VARCHAR(20) NULL DEFAULT NULL;";
                $db->exec($sql);
                return;
            }

            // si existe la columna excepcioniva y exceptionvat,
            // copiamos el valor de la columna exceptionvat a la columna excepcioniva
            // y eliminamos la columna exceptionvat
            if (false === empty($db->select($existIVA)) && false === empty($db->select($existVAT))) {
                $sql = "UPDATE " . $table . " SET excepcioniva = exceptionvat;";
                $db->exec($sql);
                $sql = "ALTER TABLE " . $table . " DROP COLUMN exceptionvat;";
                $db->exec($sql);
                return;
            }
        }
    }

    private function updateProductService(DataBase $db): void
    {
        // comprobamos si existe la columna isservice en la tabla productos
        // si no existe, terminamos
        $existService = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . FS_DB_NAME
            . "' AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'isservice';";
        if (empty($db->select($existService))) {
            return;
        }

        // buscamos los productos con la columna isservice a true
        // marcamos los productos con el tipo de servicio
        $sql = "SELECT * FROM productos WHERE isservice = true;";
        foreach ($db->select($sql) as $product) {
            $sql = "UPDATE productos SET tipo = " . $db->var2str(ProductType::SERVICE)
                . " WHERE idproducto = " . $db->var2str($product['idproducto']) . ";";
            $db->exec($sql);
        }

        // eliminamos la columna isservice
        $sql = "ALTER TABLE productos DROP COLUMN isservice;";
        $db->exec($sql);
    }

    private function updateSignedInvoices(DataBase $db): void
    {
        // recorremos las facturas enviadas que no tenga fecha de envío
        $whereNotSent = [
            new DataBaseWhere('tbaicodbar', null, 'IS NOT'),
            new DataBaseWhere('tbaisignature', null, 'IS NOT'),
            new DataBaseWhere('tbaiurl', null, 'IS NOT'),
            new DataBaseWhere('tbai_sent_date', null),
        ];
        foreach (FacturaCliente::all($whereNotSent, ['idfactura' => 'DESC'], 0, 0) as $invoice) {
            $db->exec('UPDATE facturascli SET tbai_sent_date = ' . $db->var2str($invoice->fecha . ' '
                    . $invoice->hora) . ' WHERE idfactura = ' . $invoice->idfactura . ';');
        }
    }
}
