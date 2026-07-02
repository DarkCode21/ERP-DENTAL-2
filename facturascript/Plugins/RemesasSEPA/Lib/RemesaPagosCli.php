<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA\Lib;

use DateTime;
use Digitick\Sepa\Exception\InvalidArgumentException;
use Digitick\Sepa\GroupHeader;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use Exception;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\RemesasSEPA\Lib\Accounting\CalculateSwift;
use FacturaScripts\Plugins\RemesasSEPA\Model\RemesaSEPA;

class RemesaPagosCli
{
    /**
     * @throws InvalidArgumentException
     */
    public static function getXML(RemesaSEPA $remesa): string
    {
        $header = new GroupHeader(date('Y-m-d-H-i-s'), self::sanitizeName($remesa->getNombre()));
        $header->setInitiatingPartyId($remesa->creditorid);
        $directDebit = TransferFileFacadeFactory::createDirectDebitWithGroupHeader($header, 'pain.008.001.02');

        // añadimos el pago en la cuenta de la empresa
        $fechaCargo = date('Y-m-d', strtotime($remesa->fechacargo));
        $bankAccount = $remesa->getBankAccount();
        $paymentInfo = [
            'id' => 'firstPayment',
            'dueDate' => new DateTime($fechaCargo),
            'creditorName' => self::sanitizeName($remesa->getNombre()),
            'creditorAccountIBAN' => $bankAccount->getIban(),
            'creditorAgentBIC' => CalculateSwift::getSwift($bankAccount->getIban(), $bankAccount->swift),
            'seqType' => PaymentInformation::S_RECURRING,
            'creditorId' => $remesa->creditorid,
            'localInstrumentCode' => $remesa->tipo
        ];
        if (empty($paymentInfo['creditorAgentBIC'])) {
            unset($paymentInfo['creditorAgentBIC']);
        }
        $directDebit->addPaymentInfo('firstPayment', $paymentInfo);

        // añadimos los cobros de los recibos
        foreach (self::getGroupedReceipts($remesa) as $item) {
            $transfer = [
                'amount' => $item['amount'],
                'debtorIban' => str_replace(' ', '', $item['debtorIban']),
                'debtorBic' => $item['debtorBic'],
                'debtorName' => $item['debtorName'],
                'debtorMandate' => $item['debtorMandate'],
                'debtorMandateSignDate' => $item['debtorMandateSignDate'],
                'remittanceInformation' => Tools::lang()->trans('invoice') . ' ' . implode(', ', $item['remittanceInformation']),
                'endToEndId' => end($item['endToEndId'])
            ];
            if (empty($transfer['debtorBic'])) {
                unset($transfer['debtorBic']);
            }
            $directDebit->addTransfer('firstPayment', $transfer);
        }

        return $directDebit->asXML();
    }

    /**
     * @throws Exception
     */
    protected static function getGroupedReceipts(RemesaSEPA $remittance): array
    {
        $items = [];
        foreach ($remittance->getReceipts() as $receipt) {
            $bankAccount = $receipt->getBankAccount();
            $fmandato = date('Y-m-d', strtotime($bankAccount->fmandato));
            $invoice = $receipt->getInvoice();

            if (!$remittance->agrupar) {
                $items[] = [
                    'amount' => $receipt->importe,
                    'debtorIban' => $receipt->iban,
                    'debtorBic' => $receipt->swift,
                    'debtorName' => self::sanitizeName($receipt->getSubject()->razonsocial),
                    'debtorMandate' => $bankAccount->primaryColumnValue(),
                    'debtorMandateSignDate' => new DateTime($fmandato),
                    'remittanceInformation' => [$invoice->codigo . '-' . $receipt->numero],
                    'endToEndId' => [$invoice->codigo . '-' . $receipt->numero]
                ];
                continue;
            }

            if (!isset($items[$receipt->codcliente])) {
                $items[$receipt->codcliente] = [
                    'amount' => 0,
                    'debtorIban' => $receipt->iban,
                    'debtorBic' => $receipt->swift,
                    'debtorName' => self::sanitizeName($receipt->getSubject()->razonsocial),
                    'debtorMandate' => $bankAccount->primaryColumnValue(),
                    'debtorMandateSignDate' => new DateTime($fmandato)
                ];
            }

            $items[$receipt->codcliente]['amount'] += $receipt->importe;
            $items[$receipt->codcliente]['remittanceInformation'][] = $invoice->codigo . '-' . $receipt->numero;
            $items[$receipt->codcliente]['endToEndId'][] = $invoice->codigo . '-' . $receipt->numero;
        }

        return $items;
    }

    protected static function sanitizeName(string $name): string
    {
        $changes = ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
            'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd',
            'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ő' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ű' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y',
            '&' => '&amp;', 'À' => 'A', 'Á' => 'A', 'È' => 'E', 'É' => 'E', 'Ì' => 'I',
            'Í' => 'I', 'Ò' => 'O', 'Ó' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Ü' => 'U',
            'Ñ' => 'N', 'Ç' => 'C'
        ];

        $newName = str_replace(array_keys($changes), $changes, $name);
        return substr($newName, 0, 70);
    }
}
