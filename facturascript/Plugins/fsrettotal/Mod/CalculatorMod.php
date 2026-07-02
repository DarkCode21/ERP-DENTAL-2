<?php
/**
 * Modification for calculate irpf from total of documents.
 * @author Raúl Jiménez <raljopa@gmail.com>
 */
namespace FacturaScripts\Plugins\fsrettotal\Mod;

use FacturaScripts\Core\Base\Contract\CalculatorModInterface;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\BusinessDocumentLine;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Core\Model\Impuesto;
use FacturaScripts\Core\Model\ImpuestoZona;
/**
 * Add retention over total of Documents
 * @author Raúl Jiménez Jiménez <raljopa@gmail.com>
 * 
 */
class CalculatorMod implements CalculatorModInterface{
    public function apply(BusinessDocument &$doc, array &$lines): bool{
        return true;
    }    
    public function calculate(BusinessDocument &$doc, array &$lines): bool{
        return true;
    }
    

    public function calculateLine(BusinessDocument $doc, BusinessDocumentLine &$line): bool
    {
      return true;
    }
    public function clear(BusinessDocument &$doc, array &$lines): bool{
        return true;
    }
    public function getSubtotals(array &$subtotals, BusinessDocument $doc, array $lines): bool{
        $subtotals = [
            'irpf' => 0.0,
            'iva' => [],
            'neto' => 0.0,
            'netosindto' => 0.0,
            'total' => 0.0,
            'totalirpf' => 0.0,
            'totaliva' => 0.0,
            'totalrecargo' => 0.0,
            'totalsuplidos' => 0.0
        ];
        if(\property_exists($doc,'codcliente')){
            $sujetoPasivo = new \FacturaScripts\Dinamic\Model\Cliente();
            if(!$sujetoPasivo->loadFromCode($doc->codcliente)){
                return false;
            }
        }
        else{
            $sujetoPasivo = new \FacturaScripts\Dinamic\Model\Proveedor();
            if(!$sujetoPasivo->loadFromCode($doc->codproveedor)){
                return false;
            }
        }
        
        // acumulamos por cada línea
        foreach ($lines as $line) {
           
            $pvpTotal = $line->pvptotal * (100 - $doc->dtopor1) / 100 * (100 - $doc->dtopor2) / 100;
    
            if (empty($pvpTotal)) {
                continue;
            } elseif ($line->suplido) {
                $subtotals['totalsuplidos'] += $pvpTotal;
                continue;
            }

            // IRPF
            $subtotals['irpf'] = max([$line->irpf, $subtotals['irpf']]);
            if($sujetoPasivo->irpf_sobre_total)
        {
            $totalconIVA=$line->pvptotal + ($line->pvptotal* $line->iva/100);
            
            $subtotals['totalirpf'] += $line->irpf * $totalconIVA / 100.0;
        }else{
            $subtotals['totalirpf'] += $pvpTotal * $line->irpf / 100;
        }
            

            // IVA
            $ivaKey = $line->iva . '|' . $line->recargo;
            if (false === array_key_exists($ivaKey, $subtotals['iva'])) {
                $subtotals['iva'][$ivaKey] = [
                    'iva' => $line->iva,
                    'neto' => 0.0,
                    'netosindto' => 0.0,
                    'recargo' => $line->recargo,
                    'totaliva' => 0.0,
                    'totalrecargo' => 0.0
                ];
            }

            $subtotals['iva'][$ivaKey]['neto'] += $pvpTotal;
            $subtotals['iva'][$ivaKey]['netosindto'] += $line->pvptotal;

            if ($line->iva > 0) {
                $subtotals['iva'][$ivaKey]['totaliva'] += $line->getTax()->tipo === Impuesto::TYPE_FIXED_VALUE ?
                    $pvpTotal * $line->iva :
                    $pvpTotal * $line->iva / 100;
            }

            // recargo de equivalencia
            if ($line->recargo > 0) {
                $subtotals['iva'][$ivaKey]['totalrecargo'] += $line->getTax()->tipo === Impuesto::TYPE_FIXED_VALUE ?
                    $pvpTotal * $line->recargo :
                    $pvpTotal * $line->recargo / 100;
            }
        }

        // redondeamos los IVAs
        foreach ($subtotals['iva'] as $key => $value) {
            $subtotals['iva'][$key]['neto'] = round($value['neto'], FS_NF0);
            $subtotals['iva'][$key]['netosindto'] = round($value['netosindto'], FS_NF0);
            $subtotals['iva'][$key]['totaliva'] = round($value['totaliva'], FS_NF0);
            $subtotals['iva'][$key]['totalrecargo'] = round($value['totalrecargo'], FS_NF0);

            // trasladamos a los subtotales
            $subtotals['neto'] += round($value['neto'], FS_NF0);
            $subtotals['netosindto'] += round($value['netosindto'], FS_NF0);
            $subtotals['totaliva'] += round($value['totaliva'], FS_NF0);
            $subtotals['totalrecargo'] += round($value['totalrecargo'], FS_NF0);
        }

        // redondeamos los subtotales
        $subtotals['neto'] = round($subtotals['neto'], FS_NF0);
        $subtotals['netosindto'] = round($subtotals['netosindto'], FS_NF0);
        $subtotals['totalirpf'] = round($subtotals['totalirpf'], FS_NF0);
        $subtotals['totaliva'] = round($subtotals['totaliva'], FS_NF0);
        $subtotals['totalrecargo'] = round($subtotals['totalrecargo'], FS_NF0);
        $subtotals['totalsuplidos'] = round($subtotals['totalsuplidos'], FS_NF0);

        // calculamos el total
        $subtotals['total'] = $subtotals['neto'] + $subtotals['totalsuplidos'] + $subtotals['totaliva']
            + $subtotals['totalrecargo'] - $subtotals['totalirpf'];

        
       
        return true;
    }
}