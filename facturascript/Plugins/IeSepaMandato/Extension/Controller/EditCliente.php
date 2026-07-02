<?php
namespace FacturaScripts\Plugins\IeSepaMandato\Extension\Controller;

use Closure;
use Mpdf\Mpdf;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\CuentaBancoCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;

class EditCliente
{

    protected $empresa;
    protected $cuentabancocliente;
    protected $cliente;
    protected $contacto;

    protected function createViews(): Closure
    {
        return function(){
            $this->addButton('EditCuentaBancoCliente', [
                'action' => 'print-sepa',
                'color' => 'success',
                'icon' => 'fa-solid fa-file-signature',
                'label' => 'print-sepa'
            ]);
        };
    }

    protected function execPreviousAction(): Closure
    {
        return function($action) {
            
            if ($action == 'print-sepa') {

                $this->empresa = new Empresa();
                $idempresa = Tools::settings('default', 'idempresa');
                $this->empresa->loadFromCode($idempresa);

                $this->cuentabancocliente = new CuentaBancoCliente();
                $codcuenta = $this->request->request->get('codcuenta');
                $this->cuentabancocliente->loadFromCode($codcuenta);
                $codcliente = $this->cuentabancocliente->codcliente;

                $this->cliente = new Cliente();
                $this->cliente->loadFromCode($codcliente);

                $this->contacto = new Contacto();
                $contacto = $this->cliente->idcontactofact;
                $this->contacto->loadFromCode($contacto);

                $this->setTemplate(false);
                $this->response->headers->set('Content-Type', 'application/pdf');
                $this->response->headers->set('Content-Disposition', 'inline; filename="SEPA_Mandate.pdf"');
                $mpdf = new Mpdf();

                $html = '<html><head><style>
                            .exterior {
                                border: 1px solid black;
                                padding: 10px; /* Añadir padding para aumentar la distancia a los bordes exteriores */
                                height: 1000px;
                            }   
                            .border {
                                border: 1px solid black;
                                padding: 10px; /* Añadir padding para aumentar la distancia a los bordes exteriores */
                                border-spacing: 0; /* Quitar espaciado entre las celdas */
                            }   
                            .underline {
                                border-bottom: 1px solid black;
                                vertical-align: bottom;
                            }
                            .spa-text {
                                font-size: 12px;
                                font-family: Times New Roman, serif;
                                line-height: 0.5; /* Une las celdas verticalmente */
                            }
                            .eng-text {
                                font-size: 9px;
                                font-family: Times New Roman, serif;
                                line-height: 0.5;
                            }
                            .data-text {
                                font-size: 12px;
                                font-family: Arial;
                                line-height: 0.5;
                            }
                            .conditions-spa-text {
                                font-size: 12px;
                                font-family: Times New Roman, serif;
                                line-height: 1;
                            }
                            .conditions-eng-text {
                                font-size: 9px;
                                font-family: Times New Roman, serif;
                                line-height: 1;
                            }
                         </style></head><body>';
                
                $html .= '<table width="100%"><tr><td class="exterior">';

                $html .= '<table width="100%" cellspacing="0" cellpadding="8" align="center"><tr><th><h3>Orden de domiciliación de adeudo directo SEPA</h3>'
                    . '<i class="spa-text">SEPA Direct Debit Mandate</i></th></tr></table>';

                $html .= '<span class="conditions-spa-text">A cumplimentar por el acreedor / <i class="conditions-eng-text">To be competed by creditor</i></p>';

                $html .= '<table width="100%"><tr><td class="border">'
                    . '<table width="100%">'
                
                    . '<tr><td width="50%"><b><i class="spa-text">Referencia de la orden de domiciliación:</i><br>'
                    . '<i class="eng-text">Mandate reference</i></b></td>'
                    . '<td class="underline data-text">' . $this->request->request->get('mandato') . '</td></tr>'

                    . '<tr><td><b><i class="spa-text">Identificador del acreedor:</i><br>'
                    . '<i class="eng-text">Creditor Identifier</i></b></td>'
                    . '<td class="underline data-text">' . $this->getCreditorId($this->empresa) . '</td></tr>'

                    . '<tr><td colspan="2"><b><i class="spa-text">Nombre del acreedor / </i></b>'
                    . '<b><i class="eng-text">Creditor\'s name</i></b></td></tr>'
                    . '<tr><td colspan="2" class="underline data-text">' . $this->empresa->nombre . '</td></tr>'
                    
                    . '<tr><td colspan="2"><b><i class="spa-text">Dirección / </i></b>'
                    . '<b><i class="eng-text">Address</i></b></td></tr>'
                    . '<tr><td colspan="2" class="underline data-text">' . $this->empresa->direccion . '</td></tr>'

                    . '<tr><td colspan="2"><b><i class="spa-text">Código postal - Población - Provincia / </i></b>'
                    . '<b><i class="eng-text">Postal code - City - Town</i></b></td></tr>'
                    . '<tr><td colspan="2" class="underline data-text">' . $this->empresa->codpostal
                    . ' - ' . $this->empresa->ciudad . ' - ' . $this->empresa->provincia . '</td></tr>'

                    . '<tr><td colspan="2"><b><i class="spa-text">País / </i></b>'
                    . '<b><i class="eng-text">Country</i></b></td></tr>'
                    . '<tr><td colspan="2" class="underline data-text">' . $this->getCountry($this->empresa->codpais) . '</td></tr>'

                    . '</table>'
                    . '</td></tr></table>';

                $html .= '<p class="conditions-spa-text">Mediante la firma de esta orden de domiciliación, el 
                    deudor autoriza (A) al acreedor a enviar instrucciones a la ent idad del deudor para adeudar su 
                    cuentay (B) a la entidad para efectuar los adeudos en su cuenta siguiendo las instrucciones del 
                    acreedor. Como parte de sus derechos, el deudor está legitimado alreembolso por su entidad en los 
                    términos y condiciones del contrato suscrito con la misma. La solicitud de reembolso deberá 
                    efectuarse dentro de las ocho semanas que siguen a la fecha de adeudo en cuenta. Puede obtener 
                    información adicional sobre sus derechos en su entidad financiera</p>';

                $html .= '<p class="conditions-eng-text"><i>By signing this mandate form, you au thorise (A) the Creditor 
                    to send instructions to your bank to debit your account and (B) your bank to debit your account 
                    in accordance with the instructions from theCreditor. As part of your rights, you are entitled 
                    to a refund from your bank under the terms and conditions of your agreement with your bank. A 
                    refund must be claimed within eigth weeks starting from the date on which youraccount was debited. 
                    Your rights are explained in a statement that you can obtain from your bank.</i></p>';

                $html .= '<span class="conditions-spa-text">A cumplimentar por el deudor / <i class="conditions-eng-text">To be competed by debtor</i></p>';

                $html .= '<table width="100%"><tr><td class="border">'
                    . '<table width="100%">'
                
                    . '<tr><td colspan="6"><b><i class="spa-text">Nombre del deudor/es / </i></b>'
                    . '<b><i class="eng-text">Debtor\'s name</i></b></td></tr>'
                    . '<tr><td colspan="6" class="underline data-text">' . $this->cliente->razonsocial . '</td></tr>'

                    . '<tr><td colspan="6"><b><i class="spa-text">Dirección del deudor / </i></b>'
                    . '<b><i class="eng-text">Address of the debtor</i></b></td></tr>'
                    . '<tr><td colspan="6" class="underline data-text">' . $this->contacto->direccion . '</td></tr>'    

                    . '<tr><td colspan="6"><b><i class="spa-text">Código postal - Población - Provincia / </i></b>'
                    . '<b><i class="eng-text">Postal code - City - Town</i></b></td></tr>'
                    . '<tr><td colspan="6" class="underline data-text">' . $this->contacto->codpostal
                    . ' - ' . $this->contacto->ciudad . ' - ' . $this->contacto->provincia . '</td></tr>'

                    . '<tr><td colspan="6"><b><i class="spa-text">País / </i></b>'
                    . '<b><i class="eng-text">Country</i></b></td></tr>'
                    . '<tr><td colspan="6" class="underline data-text">' . $this->getCountry($this->contacto->codpais) . '</td></tr>'

                    . '<tr><td colspan="6"><b><i class="spa-text">Swift BIC / </i></b>'
                    . '<b><i class="eng-text">Swift BIC (puede contener 8 u 11 posiciones) / Swift BIC (up to 8 or 11 characters)</i></b></td></tr>'
                    . '<tr><td colspan="4" class="underline data-text">' . $this->cuentabancocliente->swift . '</td></tr>'

                    . '<tr><td colspan="4"><b><i class="spa-text"><br>Número de cuenta - IBAN / </i></b>'
                    . '<b><i class="eng-text">Account number - IBAN</i></b></td></tr>'
                    . '<tr><td colspan="4" class="underline data-text">' . $this->cuentabancocliente->iban . '</td></tr>'
                    . '<tr><td colspan="4" align="center" class="conditions-eng-text">En España el IBAN consta de 24 posiciones comenzando siempre por ES</td></tr>'
                    . '<tr><td colspan="4" align="center" class="conditions-eng-text"><i>Spanish IBAN of24 positions always starting ES</i></td></tr>'

                    . '<tr>'
                    . '<td><b><i class="spa-text"><br>Tipo de pago:</i><br><i class="eng-text">Type of payment</i></b></td>'
                    . '<td align="right"><input type="checkbox" checked="true"></td>'
                    . '<td><b><i class="spa-text"><br>Pago recurrente</i><br><i class="eng-text">Recurrement payment</i></b></td>'
                    . '<td align="center"><b><i class="spa-text"><br>o</i><br><i class="eng-text">or</i></b></td>'
                    . '<td align="right"><input type="checkbox">'
                    . '<td><b><i class="spa-text"><br>Pago único</i><br><i class="eng-text">One-off payment</i></b></td>'
                    . '</tr>'

                    . '<tr><td colspan="2"><b><i class="spa-text"><br>Fecha - Localidad</i></b><br>'
                    . '<b><i class="eng-text">Date - location in which you are signing</i></b></td>'
                    . '<td colspan="4" class="underline data-text">' . $this->contacto->ciudad . ' - ' . $this->cuentabancocliente->fmandato . '</td></tr>'

                    . '<tr><td colspan="2"><b><i class="spa-text"><br>Firma del deudor</i></b><br>'
                    . '<b><i class="eng-text">Signature of the debtor</i></b></td>'
                    . '<td colspan="4" class="underline data-text"></td></tr>'

                    . '</table>'
                    . '</td></tr></table>';

                $html .= '<table width="100%"><tr><td align="center" class="conditions-spa-text">'
                    . 'TODOS LOS CAMPOS HAN DE SER CUMPLIMENTADOS OBLIGATORIAMENTE.<br>'
                    . 'UNA VEZ FIRMADA ESTA ORDEN DE DOMICILIACIÓN DEBE SER ENVIADA AL ACREEDOR PARA SU CUSTODIA.<br>'
                    . '<i class="conditions-eng-text">ALL GAPS ARE MANDATORY. ONCE THIS MANDATE HAS BEEN SIGNED MUST BE'
                    . 'SENT TO CREDITOR FOR STORAGE.</i>'
                    . '</td></tr></table>';

                $html .= '</td></tr></table>';

                $html .= '</body></html>';

                $mpdf->WriteHTML($html);
                $this->response->setContent($mpdf->Output('', 'S'));
            }
        };
    }

    public function getCreditorId()
    {
        return function ($company){
            $cif = str_replace([' ', '-'], ['', ''], ltrim($company->cifnif, '0'));

            // Remove ES from the beginning of the CIF if it exists
            if (substr(strtoupper($cif), 0, 2) === 'ES') {
                $cif = substr($cif, 2);
            }
    
            $pais = new Pais();
            $pais->loadFromCode($company->codpais);
            $codiso = empty($pais->codiso) ? 'ES' : $pais->codiso;
    
            // calculate control digits
            $cifAux = $this->words2numbers($cif . $codiso . '00');
            $total = 98 - ($cifAux % 97);
    
            $sufijo = empty($this->getBankAccount()->sufijosepa) ? '000' : $this->getBankAccount()->sufijosepa;
            
            return $codiso . sprintf('%02s', $total) . $sufijo . $cif;
        };
    }

    public function words2numbers()
    {
        return function($txt): int
        {
            $data = [
                'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'E' => 14, 'F' => 15, 'G' => 16, 'H' => 17,
                'I' => 18, 'J' => 19, 'K' => 20, 'L' => 21, 'M' => 22, 'N' => 23, 'O' => 24, 'P' => 25,
                'Q' => 26, 'R' => 27, 'S' => 28, 'T' => 29, 'U' => 30, 'V' => 31, 'W' => 32, 'X' => 33,
                'Y' => 34, 'Z' => 35
            ];

            $number = '';
            $pos = 0;
            while ($pos < strlen($txt)) {
                $char = substr($txt, $pos, 1);
                $number .= isset($data[$char]) ? $data[$char] : $char;
                $pos++;
            }

            return (int)$number;
        };
    }

    public function getBankAccount()
    {
        return function(){
            $bank = new CuentaBanco();
            $bank->loadFromCode(1);
            return $bank;
        };
    }

    public function getCountry()
    {
        return function($codpais){
            $country = new Pais();
            $country->loadFromCode($codpais);
            return $country->nombre;
        };
    }

}
