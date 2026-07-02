<?php
/**
 * Copyright (C) 2022-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Lib\ProductType;
use FacturaScripts\Dinamic\Lib\RegimenIVA;
use FacturaScripts\Dinamic\Lib\Vies;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CodigoIae;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Impuesto;

final class TbaiTools
{
    const FILE_PATH_TBAI = 'MyFiles/Ticketbai/';
    const FILE_PATH_TBAI_TMP = 'MyFiles/Tmp/Ticketbai/';

    public static function checkCompanyLicense(Empresa $company): bool
    {
        if (empty($company->tbai_license) ||
            empty($company->tbai_developer) ||
            empty($company->tbai_supplier) ||
            empty($company->tbai_version) ||
            empty($company->tbai_signature) ||
            empty($company->tbai_password)) {
            return false;
        }

        return true;
    }

    public static function getErrorToUTF8(string $message)
    {
        if (empty($message)) {
            return '';
        }

        // Detectar la codificación del mensaje
        $encoding = mb_detect_encoding($message, 'UTF-8, ISO-8859-1, ISO-8859-15', true);

        // Convertir a UTF-8 si la codificación no es UTF-8
        if ($encoding != 'UTF-8') {
            return mb_convert_encoding($message, 'UTF-8', $encoding);
        }

        return $message;
    }

    public static function getExceptionTax(array $lines, Empresa $company): string
    {
        // buscamos el tipo de excepción de iva de la línea
        $lineExcepcionIva = null;
        foreach ($lines as $line) {
            if (false === empty($line->excepcioniva)) {
                $lineExcepcionIva = $line->excepcioniva;
                break;
            }
        }

        // decidimos que excepción de iva usar, empresa o líneas
        if (false === empty($lineExcepcionIva) && false === empty($company->excepcioniva)
            || empty($lineExcepcionIva) && false === empty($company->excepcioniva)) {
            return $company->excepcioniva;
        }

        if (false === empty($lineExcepcionIva) && empty($company->excepcioniva)) {
            return $lineExcepcionIva;
        }

        return '';
    }

    public static function getForeignSubject(FacturaCliente $invoice): bool
    {
        // si el país de la factura es diferente a España, es extranjero
        if (false === in_array($invoice->codpais, ['ES', 'ESP'])) {
            return true;
        }

        return false;
    }

    public static function getTaxes(FacturaCliente $invoice, array $lines): array
    {
        // calculate total discount
        $totalDto = 1.0;
        foreach ([$invoice->dtopor1, $invoice->dtopor2] as $dto) {
            $totalDto *= 1 - $dto / 100;
        }

        $subtotals = [];
        foreach ($lines as $line) {
            if (false === $line->tbai_send || empty($line->descripcion)) {
                continue;
            }

            $pvptotal = $line->pvptotal * $totalDto;
            $key = $line->codimpuesto . '_' . $line->iva . '_' . $line->recargo;

            // comprobamos si hay excepción de IVA en la línea
            if (false === empty($line->excepcioniva)) {
                $key .= '_' . str_replace(' ', '_', $line->excepcioniva);
            }

            // comprobamos si existe producto de la línea y tiene tipo de producto
            $producto = $line->getProducto();
            if ($producto->exists() && false === empty($producto->tipo)) {
                $key .= '_' . str_replace(' ', '_', $producto->tipo);
            }

            if (!isset($subtotals[$key])) {
                $subtotals[$key] = [
                    'productType' => $producto->tipo,
                    'tax' => $key,
                    'taxbase' => 0,
                    'taxp' => $line->iva,
                    'taxamount' => 0,
                    'taxsurchargep' => $line->recargo,
                    'taxsurcharge' => 0,
                    'lines' => []
                ];

                $impuesto = new Impuesto();
                if ($line->codimpuesto && $impuesto->loadFromCode($line->codimpuesto)) {
                    $subtotals[$key]['tax'] = $impuesto->descripcion;
                }
            }

            $subtotals[$key]['taxbase'] += $pvptotal;
            $subtotals[$key]['taxamount'] += $pvptotal * $line->iva / 100;
            $subtotals[$key]['taxsurcharge'] += $pvptotal * $line->recargo / 100;
            $subtotals[$key]['lines'][] = $line;
        }

        return $subtotals;
    }

    public static function getTerritory(Empresa $company): string
    {
        if (in_array(mb_strtolower($company->provincia), ['alava', 'álava', 'araba'])) {
            return '01';
        }

        if (in_array(mb_strtolower($company->provincia), ['vizcaya', 'bizkaia'])) {
            return '02';
        }

        if (in_array(mb_strtolower($company->provincia), ['guipuzcoa', 'guipúzcoa', 'gipuzkoa'])) {
            return '03';
        }

        Tools::log()->warning('no-basque-country-company');
        return '';
    }

    public static function getTypeIvaCustomer(Empresa $company, Cliente $customer, array $lines, FacturaCliente $invoice): array
    {
        $result = [];

        // si alguna de las líneas tiene la excepción ES_141, añadimos 05
        foreach ($lines as $line) {
            if ($line->excepcioniva === 'ES_141') {
                $result[] = '05';
            }
        }

        // si la empresa tiene el regimen de impuestos en Simplificado, añadimos 52
        if ($company->regimeniva === RegimenIVA::TAX_SYSTEM_SIMPLIFIED) {
            $result[] = '52';
        }

        // hay que verificar las opciones y saber cuál sería el código del exento
        // si la empresa tiene RE y el cliente no, añadimos 51
        // si es 51 recorremos todas las líneas, si ninguna de las líneas es un producto de servicio, añadimos 01
        // si el cliente tiene RE entonces comprobamos

        $is51 = $customer->regimeniva !== RegimenIVA::TAX_SYSTEM_SURCHARGE
            && $company->regimeniva === RegimenIVA::TAX_SYSTEM_SURCHARGE;

        if ($is51) {
            $linesContNotRE = 0;
            foreach ($lines as $line) {
                $product = $line->getProducto();
                if (empty($product->idproducto)) {
                    continue;
                }
                if ($product->tipo === ProductType::SERVICE) {
                    $linesContNotRE++;
                }
            }

            // si todas las líneas son de servicios y no tenemos el código 05, añadimos 01
            // si no todas las líneas son de servicios, añadimos 51
            if ($linesContNotRE === count($lines) && false === in_array('05', $result)) {
                $result[] = '01';
            } elseif ($linesContNotRE !== count($lines)) {
                $result[] = '51';
            }
        }

        if ($invoice->operacion === InvoiceOperation::INTRA_COMMUNITY) {
            if (false === in_array('01', $result)) {
                $result[] = '01';
            }
        } elseif ($invoice->codpais === $company->codpais
            || in_array($invoice->codpais, Vies::EU_COUNTRIES)) {
            if (false === in_array('01', $result)) {
                $result[] = '01';
            }
        } else {
            $result[] = '02';
        }

        // si no tenemos valores, añadimos 01
        if (empty($result)) {
            $result[] = '01';
        }

        return $result;
    }

    public static function getVatIdType(Cliente $customer): string
    {
        switch ($customer->tipoidfiscal) {
            case 'CIF':
            case 'IFZ':
            case 'NIF':
                return '02';

            case 'Pasaporte':
                return '03';

            case 'DNI':
                return '04';

            case 'NIE':
                return '05';

            default:
                return '06';
        }
    }

    public static function isBasqueCountryCompany(Empresa $company): bool
    {
        return self::isCompanyBizkaia($company) || self::isCompanyAlava($company) || self::isCompanyGuipuzcoa($company);
    }

    public static function isCompanyAlava(Empresa $company): bool
    {
        return !empty($company->provincia) && in_array(mb_strtolower($company->provincia), ['alava', 'álava', 'araba']);
    }

    public static function isCompanyBizkaia(Empresa $company): bool
    {
        return !empty($company->provincia) && in_array(mb_strtolower($company->provincia), ['vizcaya', 'bizkaia']);
    }

    public static function isCompanyGuipuzcoa(Empresa $company): bool
    {
        return !empty($company->provincia) && in_array(mb_strtolower($company->provincia), ['guipuzcoa', 'guipúzcoa', 'gipuzkoa']);
    }

    public static function isPreviousInvoiceSigned(Empresa $company, FacturaCliente $invoice, ?FacturaCliente $previous, ?FacturaCliente $rectified): bool
    {
        // si existe la factura anterior y no está enviada, devolvemos false
        if ($previous && (empty($previous->tbai_sent_date))) {
            Tools::log()->warning('ticketbai-previous-not-generated', [
                '%code%' => $previous->codigo,
                'model-code' => $invoice->primaryColumnValue(),
                'model-class' => $invoice->modelClassName(),
            ]);
            return false;
        }

        // si no existe la factura inmediatamente anterior, buscamos cualquier factura anterior dentro del rango
        if (empty($previous)) {
            $wherePrevious = [
                new DataBaseWhere('idempresa', $company->idempresa),
                new DataBaseWhere('numero', $invoice->numero, '<'),
                new DataBaseWhere('fecha', $company->tbai_startdatesign, '>=')
            ];
            $order = ['idfactura' => 'DESC'];
            foreach (FacturaCliente::all($wherePrevious, $order, 0, 0) as $prev) {
                Tools::log()->warning('ticketbai-previous-not-generated', [
                    '%code%' => $prev->codigo,
                    'model-code' => $invoice->primaryColumnValue(),
                    'model-class' => $invoice->modelClassName(),
                ]);
                return false;
            }
        }

        // si hay rectificativa y la fecha de la rectificativa es igual o superior a la fecha configurada para el envío de facturas en la empresa
        // comprobamos si está firmada, si no está firmada, devolvemos false
        if ($rectified
            && strtotime($rectified->fecha) >= strtotime($rectified->getCompany()->tbai_startdatesign)
            && (empty($rectified->tbai_sent_date))) {
            Tools::log()->warning('ticketbai-rectified-not-generated', [
                'model-code' => $invoice->primaryColumnValue(),
                'model-class' => $invoice->modelClassName(),
            ]);
            return false;
        }

        return true;
    }

    public static function logMessageErros(FacturaCliente $invoice, array $errors, string $messageError): void
    {
        $messages = [];

        foreach ($errors as $error) {
            // obtenemos el mensaje
            $message = [
                'code' => $error['errorCode'] ?? '',
                'es' => $error['errorMessage']['es'] ?? '',
                'eu' => $error['errorMessage']['eu'] ?? '',
            ];

            // si todos los campos están vacíos, no hacemos nada
            if (empty($message['code']) && empty($message['es']) && empty($message['eu'])) {
                continue;
            }

            // si hay mensaje con el mismo código, saltamos
            if (isset($messages[$message['code']])) {
                continue;
            }

            // guardamos el mensaje
            $messages[$message['code']] = $message;
        }

        // si $messageError empieza por '[{' es un array, lo convertimos a array
        if (is_string($messageError) && strpos($messageError, '[{') === 0) {
            $messageError = json_decode($messageError, true);
        } elseif (is_string($messageError) && strpos($messageError, '{') === 0) {
            // si $messageError empieza por '{' es un array, lo convertimos a array
            $messageError = [json_decode($messageError, true)];
        } else {
            $messageError = [
                '000' => [
                    'code' => '000',
                    'es' => $messageError,
                    'eu' => $messageError,
                ]
            ];
        }

        foreach ($messageError as $msgError) {
            $message = [
                'code' => $msgError['codigo'] ?? '',
                'es' => $msgError['descripcion'] ?? '',
                'eu' => $msgError['azalpena'] ?? '',
            ];

            // si todos los campos están vacíos, no hacemos nada
            if (empty($message['code']) && empty($message['es']) && empty($message['eu'])) {
                continue;
            }

            // si hay mensaje con el mismo código, saltamos
            if (isset($messages[$message['code']])) {
                continue;
            }

            // guardamos el mensaje
            $messages[$message['code']] = $message;
        }

        foreach ($messages as $message) {
            $txt = Session::user()->langcode === 'eu_ES' ? $message['eu'] : $message['es'];
            Tools::log()->warning( $message['code'] . ': ' . $txt, [
                'model-code' => $invoice->primaryColumnValue(),
                'model-class' => $invoice->modelClassName(),
                'message' => $message,
            ]);
        }
    }

    public static function normaliceCifNif(?string $cif): string
    {
        return empty($cif) ? '' : str_replace([' ', '-', '_', '.', ',', '(', ')', '/'], '', strtoupper($cif));
    }

    public static function setBizkaiaIAE(array &$json, array $lines, FacturaCliente $invoice): bool
    {
        $IAEs = [];
        foreach ($lines as $line) {
            if (empty($line->tbai_idiae)) {
                Tools::log()->warning('ticketbai-bizkaia-line-without-iae', [
                    'model-code' => $invoice->primaryColumnValue(),
                    'model-class' => $invoice->modelClassName(),
                ]);
                return false;
            }

            $iaeModel = new CodigoIae();
            if (false === $iaeModel->loadFromCode($line->tbai_idiae)) {
                Tools::log()->warning('ticketbai-bizkaia-iae-not-found', [
                    '%code%' => $line->tbai_idiae,
                    'model-code' => $invoice->primaryColumnValue(),
                    'model-class' => $invoice->modelClassName(),
                ]);
                return false;
            }

            if (isset($IAEs[$iaeModel->iae])) {
                $IAEs[$iaeModel->iae] += $line->pvptotal;
                continue;
            }

            $IAEs[$iaeModel->iae] = $line->pvptotal;
        }

        // si no hay IAEs, terminamos
        if (empty($IAEs)) {
            Tools::log()->warning('ticketbai-bizkaia-natural-person-without-iae', [
                'model-code' => $invoice->primaryColumnValue(),
                'model-class' => $invoice->modelClassName(),
            ]);
            return false;
        }

        // si hay más de 10 epígrafes, terminamos
        if (count($IAEs) > 10) {
            Tools::log()->warning('ticketbai-bizkaia-natural-person-iae-limit', [
                '%limit%' => 10,
                'model-code' => $invoice->primaryColumnValue(),
                'model-class' => $invoice->modelClassName(),
            ]);
            return false;
        }

        // si solo hay un epígrafe, lo añadimos
        if (count($IAEs) === 1) {
            $json["batuzIncomeTaxes"]['incomeTaxDetails'] = [
                [
                    "epigraph" => array_keys($IAEs)[0]
                ]
            ];
            return true;
        }

        // si hay más de un epígrafe, añadimos todos
        foreach ($IAEs as $epigraph => $total) {
            $json["batuzIncomeTaxes"]['incomeTaxDetails'][] = [
                "epigraph" => $epigraph,
                "amount" => round($total, 2)
            ];
        }

        return true;
    }

    public static function setForeignDeliveryNotSubjectBreakdownItems(?string $operacion, array $subtotal, array $foreignDeliveryNotSubjectBreakdownItems): array
    {
        $foreignDeliveryNotSubjectBreakdownItems[] = [
            'amount' => round($subtotal['taxamount'], 2),
            'reason' => $operacion && $operacion === InvoiceOperation::INTRA_COMMUNITY ? 'RL' : 'OT'
        ];
        return $foreignDeliveryNotSubjectBreakdownItems;
    }

    public static function setForeignDeliverySubjectNotExemptBreakdownItems(array $subtotal, array $vatRegimes, array $foreignDeliverySubjectNotExemptBreakdownItems): array
    {
        // Solo se puede indicar nationalSubjectNotExemptBreakdownItems S2
        // si alguno de los vatRegimes es 01, 04, 05, 06, 07 o 12
        // o el taxamount es igual a 0 y vatRegimes es distinto de 03, 05 y 09

        if (in_array(['01', '04', '05', '06', '07', '12'], $vatRegimes)
            || false === in_array(['03', '05', '09'], $vatRegimes) && $subtotal['taxamount'] == 0) {
            $type = 'S2';
        } else {
            $type = 'S1';
        }

        $isREorSIMP = in_array('51', $vatRegimes) || in_array('52', $vatRegimes);

        $array = [
            'taxBase' => round($subtotal['taxbase'], 2),
            'taxRate' => $type === 'S2' ? 0 : $subtotal['taxp'],
            'taxQuota' => $type === 'S2' ? 0 : round($subtotal['taxamount'], 2),
            'equivalenceRate' => !empty($subtotal['taxsurchargep']) && !$isREorSIMP ? round($subtotal['taxsurchargep'], 2) : null,
            'equivalenceQuota' => !empty($subtotal['taxsurcharge']) && !$isREorSIMP ? round($subtotal['taxsurcharge'], 2) : null,
            'isEquivalenceOperation' => $subtotal['productType'] === ProductType::SERVICE ? false : $isREorSIMP,
        ];

        if (isset($foreignDeliverySubjectNotExemptBreakdownItems[$type])) {
            $foreignDeliverySubjectNotExemptBreakdownItems[$type]['vatDetails'][] = $array;
            return $foreignDeliverySubjectNotExemptBreakdownItems;
        }

        $foreignDeliverySubjectNotExemptBreakdownItems[$type] = [
            'type' => $type,
            'vatDetails' => [$array]
        ];
        return $foreignDeliverySubjectNotExemptBreakdownItems;
    }

    public static function setForeignDeliverySubjectExemptBreakdownItems(array $subtotal, string $excepcionIva, array $foreignDeliverySubjectExemptBreakdownItems): array
    {
        $array = ['taxBase' => round($subtotal['taxbase'], 2)];

        switch ($excepcionIva) {
            case 'ES_20':
                $array['reason'] = 'E1';
                break;

            case 'ES_21':
                $array['reason'] = 'E2';
                break;

            case 'ES_22':
                $array['reason'] = 'E3';
                break;

            case 'ES_23':
            case 'ES_24':
                $array['reason'] = 'E4';
                break;

            case 'ES_25':
                $array['reason'] = 'E5';
                break;

            case 'ES_26':
            default:
                $array['reason'] = 'E6';
                break;
        }

        $foreignDeliverySubjectExemptBreakdownItems[] = $array;
        return $foreignDeliverySubjectExemptBreakdownItems;
    }

    public static function setForeignServiceNotSubjectBreakdownItems(?string $operacion, array $subtotal, array $foreignServiceNotSubjectBreakdownItems): array
    {
        $foreignServiceNotSubjectBreakdownItems[] = [
            'amount' => round($subtotal['taxamount'], 2),
            'reason' => $operacion && $operacion === InvoiceOperation::INTRA_COMMUNITY ? 'RL' : 'OT'
        ];
        return $foreignServiceNotSubjectBreakdownItems;
    }

    public static function setForeignServiceSubjectExemptBreakdownItems(array $subtotal, string $excepcionIva, array $foreignServiceSubjectExemptBreakdownItems): array
    {
        $array = ['taxBase' => round($subtotal['taxbase'], 2)];

        switch ($excepcionIva) {
            case 'ES_20':
                $array['reason'] = 'E1';
                break;

            case 'ES_21':
                $array['reason'] = 'E2';
                break;

            case 'ES_22':
                $array['reason'] = 'E3';
                break;

            case 'ES_23':
            case 'ES_24':
                $array['reason'] = 'E4';
                break;

            case 'ES_25':
                $array['reason'] = 'E5';
                break;

            case 'ES_26':
            default:
                $array['reason'] = 'E6';
                break;
        }

        $foreignServiceSubjectExemptBreakdownItems[] = $array;
        return $foreignServiceSubjectExemptBreakdownItems;
    }

    public static function setForeignServiceSubjectNotExemptBreakdownItems(array $subtotal, array $vatRegimes, array $foreignServiceSubjectNotExemptBreakdownItems): array
    {
        // Solo se puede indicar nationalSubjectNotExemptBreakdownItems S2
        // si alguno de los vatRegimes es 01, 04, 05, 06, 07 o 12
        // o el taxamount es igual a 0 y vatRegimes es distinto de 03, 05 y 09

        if (in_array(['01', '04', '05', '06', '07', '12'], $vatRegimes)
            || false === in_array(['03', '05', '09'], $vatRegimes) && $subtotal['taxamount'] == 0) {
            $type = 'S2';
        } else {
            $type = 'S1';
        }

        $isREorSIMP = in_array('51', $vatRegimes) || in_array('52', $vatRegimes);

        $array = [
            'taxBase' => round($subtotal['taxbase'], 2),
            'taxRate' => $type === 'S2' ? 0 : $subtotal['taxp'],
            'taxQuota' => $type === 'S2' ? 0 : round($subtotal['taxamount'], 2),
            'equivalenceRate' => !empty($subtotal['taxsurchargep']) && !$isREorSIMP ? round($subtotal['taxsurchargep'], 2) : null,
            'equivalenceQuota' => !empty($subtotal['taxsurcharge']) && !$isREorSIMP ? round($subtotal['taxsurcharge'], 2) : null,
            'isEquivalenceOperation' => $subtotal['productType'] === ProductType::SERVICE ? false : $isREorSIMP,
        ];

        if (isset($foreignServiceSubjectNotExemptBreakdownItems[$type])) {
            $foreignServiceSubjectNotExemptBreakdownItems[$type]['vatDetails'][] = $array;
            return $foreignServiceSubjectNotExemptBreakdownItems;
        }

        $foreignServiceSubjectNotExemptBreakdownItems[$type] = [
            'type' => $type,
            'vatDetails' => [$array]
        ];
        return $foreignServiceSubjectNotExemptBreakdownItems;
    }

    public static function setNationalNotSubjectBreakdownItems(?string $operacion, array $subtotal, array $nationalNotSubjectBreakdownItems): array
    {
        $nationalNotSubjectBreakdownItems[] = [
            'amount' => round($subtotal['taxamount'], 2),
            'reason' => $operacion && $operacion === InvoiceOperation::INTRA_COMMUNITY ? 'RL' : 'OT'
        ];
        return $nationalNotSubjectBreakdownItems;
    }

    public static function setNationalSubjectExemptBreakdownItems(array $subtotal, string $excepcionIva, array $nationalSubjectExemptBreakdownItems): array
    {
        $array = ['taxBase' => round($subtotal['taxbase'], 2)];

        switch ($excepcionIva) {
            case 'ES_20':
                $array['reason'] = 'E1';
                break;

            case 'ES_21':
                $array['reason'] = 'E2';
                break;

            case 'ES_22':
                $array['reason'] = 'E3';
                break;

            case 'ES_23':
            case 'ES_24':
                $array['reason'] = 'E4';
                break;

            case 'ES_25':
                $array['reason'] = 'E5';
                break;

            case 'ES_26':
            default:
                $array['reason'] = 'E6';
                break;
        }

        $nationalSubjectExemptBreakdownItems[] = $array;
        return $nationalSubjectExemptBreakdownItems;
    }

    public static function setNationalSubjectNotExemptBreakdownItems(array $subtotal, array $vatRegimes, array $nationalSubjectNotExemptBreakdownItems): array
    {
        // Solo se puede indicar nationalSubjectNotExemptBreakdownItems S2
        // si alguno de los vatRegimes es 01, 04, 05, 06, 07 o 12
        // o el taxamount es igual a 0 y vatRegimes es distinto de 03, 05 y 09

        if (in_array(['01', '04', '05', '06', '07', '12'], $vatRegimes)
            || false === in_array(['03', '05', '09'], $vatRegimes) && $subtotal['taxamount'] == 0) {
            $type = 'S2';
        } else {
            $type = 'S1';
        }

        $isREorSIMP = in_array('51', $vatRegimes) || in_array('52', $vatRegimes);

        $array = [
            'taxBase' => round($subtotal['taxbase'], 2),
            'taxRate' => $type === 'S2' ? 0 : $subtotal['taxp'],
            'taxQuota' => $type === 'S2' ? 0 : round($subtotal['taxamount'], 2),
            'equivalenceRate' => !empty($subtotal['taxsurchargep']) && !$isREorSIMP ? round($subtotal['taxsurchargep'], 2) : null,
            'equivalenceQuota' => !empty($subtotal['taxsurcharge']) && !$isREorSIMP ? round($subtotal['taxsurcharge'], 2) : null,
            'isEquivalenceOperation' => $subtotal['productType'] === ProductType::SERVICE ? false : $isREorSIMP,
        ];

        if (isset($nationalSubjectNotExemptBreakdownItems[$type])) {
            $nationalSubjectNotExemptBreakdownItems[$type]['vatDetails'][] = $array;
            return $nationalSubjectNotExemptBreakdownItems;
        }

        $nationalSubjectNotExemptBreakdownItems[$type] = [
            'type' => $type,
            'vatDetails' => [$array]
        ];
        return $nationalSubjectNotExemptBreakdownItems;
    }
}
