<?php

/**
 * Generador de tickets ESC/POS para impresoras térmicas.
 * Compatible con impresoras 80 mm tipo 10POS RP-12N y similares.
 * Adaptado para TPVneo.
 */

namespace FacturaScripts\Plugins\TPVneo\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\FormatoDocumento;

class TicketESCPos
{
    // Comandos ESC/POS
    private const ESC = "\x1B";
    private const GS  = "\x1D";
    private const LF  = "\n";

    // Ancho en caracteres conservador para evitar saltos en impresoras estrechas
    private const WIDTH = 42;

    private string $buffer = '';
    private int    $lastErrno  = 0;
    private string $lastErrstr = '';

    // Columnas para productos (Descripcion + IMP)
    private const PRODUCT_IMP_WIDTH = 10;
    private const PRODUCT_DESC_WIDTH = self::WIDTH - self::PRODUCT_IMP_WIDTH;

    // Columnas compactas para tabla IVA (suma = WIDTH)
    private const IVA_W1 = 7;
    private const IVA_W2 = 11;
    private const IVA_W3 = 12;
    private const IVA_W4 = 12;

    public function getLastError(): string
    {
        return $this->lastErrno . ' - ' . $this->lastErrstr;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    // ─────────────────────────────────────────────────────────────
    //  Comandos primitivos
    // ─────────────────────────────────────────────────────────────

    public function init(): self
    {
        $this->buffer  = self::ESC . '@';
        $this->buffer .= self::ESC . 't' . "\x13"; // CP858 (español + €)
        return $this;
    }

    public function alignLeft(): self
    {
        $this->buffer .= self::ESC . 'a' . "\x00";
        return $this;
    }

    public function alignCenter(): self
    {
        $this->buffer .= self::ESC . 'a' . "\x01";
        return $this;
    }

    public function bold(bool $on): self
    {
        $this->buffer .= self::ESC . 'E' . ($on ? "\x01" : "\x00");
        return $this;
    }

    public function feed(int $lines = 3): self
    {
        $this->buffer .= self::ESC . 'd' . chr(min($lines, 255));
        return $this;
    }

    public function cut(): self
    {
        $this->buffer .= self::GS . 'V' . "\x41" . "\x00";
        return $this;
    }

    public function line(string $text = ''): self
    {
        $this->buffer .= $this->encode($text) . self::LF;
        return $this;
    }

    public function separator(string $char = '-'): self
    {
        $this->buffer .= str_repeat($char, self::WIDTH) . self::LF;
        return $this;
    }

    public function twoColumns(string $left, string $right, bool $bold = false): self
    {
        $rLen = mb_strlen($right);
        $lMax = self::WIDTH - $rLen - 1;
        if (mb_strlen($left) > $lMax) {
            $left = mb_substr($left, 0, $lMax);
        }
        $pad = self::WIDTH - mb_strlen($left) - $rLen;
        $row = $left . str_repeat(' ', max(1, $pad)) . $right;
        if ($bold) {
            $this->bold(true);
        }
        $this->buffer .= $this->encode($row) . self::LF;
        if ($bold) {
            $this->bold(false);
        }
        return $this;
    }

    public function fourColumnsHeader(string $c1, string $c2, string $c3, string $c4): self
    {
        // Cabecera IVA: IVA, NETO, TOTAL IVA, BRUTO
        $w1 = 8;
        $w2 = 13;
        $w3 = 13;
        $w4 = 14;
        
        $row = str_pad($c1, $w1)
             . str_repeat(' ', max(0, $w2 - mb_strlen($c2))) . $c2
             . str_repeat(' ', max(0, $w3 - mb_strlen($c3))) . $c3
             . str_repeat(' ', max(0, $w4 - mb_strlen($c4))) . $c4;
        
        $this->bold(true);
        $this->buffer .= $this->encode($row) . self::LF;
        $this->bold(false);
        return $this;
    }

    public function fourColumnsData(string $c1, string $c2, string $c3, string $c4): self
    {
        // Datos IVA: columnas alineadas con Header
        $w1 = 8;
        $w2 = 13;
        $w3 = 13;
        $w4 = 14;
        
        $row = str_pad($c1, $w1)
             . str_repeat(' ', max(0, $w2 - mb_strlen($c2))) . $c2
             . str_repeat(' ', max(0, $w3 - mb_strlen($c3))) . $c3
             . str_repeat(' ', max(0, $w4 - mb_strlen($c4))) . $c4;
        
        $this->buffer .= $this->encode($row) . self::LF;
        return $this;
    }

    public function productLine(string $desc, string $qty, string $pvu, string $total): self
    {
        $c1 = 24;
        $c2 = 5;
        $c3 = 9;
        $c4 = 10;
        $lines  = $this->wrapText($desc, $c1);
        $first  = array_shift($lines);
        $eFirst = $this->encode($first);
        $eQty   = $this->encode($qty);
        $ePvu   = $this->encode($pvu);
        $eTotal = $this->encode($total);
        $row = str_pad($eFirst, $c1)
            . str_pad($eQty,   $c2, ' ', STR_PAD_LEFT)
            . str_pad($ePvu,   $c3, ' ', STR_PAD_LEFT)
            . str_pad($eTotal, $c4, ' ', STR_PAD_LEFT);
        $this->buffer .= $row . self::LF;
        foreach ($lines as $wrap) {
            $this->buffer .= $this->encode('  ' . $wrap) . self::LF;
        }
        return $this;
    }

    public function logo(string $filePath): self
    {
        if (!function_exists('imagecreatefrompng') || !file_exists($filePath)) {
            return $this;
        }
        $mime = mime_content_type($filePath);
        $img  = null;
        if ($mime === 'image/png')       $img = @imagecreatefrompng($filePath);
        elseif ($mime === 'image/jpeg')  $img = @imagecreatefromjpeg($filePath);
        elseif ($mime === 'image/gif')   $img = @imagecreatefromgif($filePath);
        if (!$img) return $this;

        $origW = imagesx($img);
        $origH = imagesy($img);
        $maxW  = 384;
        if ($origW > $maxW) {
            $newW = $maxW;
            $newH = (int)round($origH * $maxW / $origW);
            $resized = imagecreatetruecolor($newW, $newH);
            imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($img);
            $img = $resized;
            $origW = $newW;
            $origH = $newH;
        }

        $widthBytes = (int)ceil($origW / 8);
        $data = '';
        for ($y = 0; $y < $origH; $y++) {
            for ($bx = 0; $bx < $widthBytes; $bx++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = $bx * 8 + $bit;
                    if ($x < $origW) {
                        $rgb = imagecolorat($img, $x, $y);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8)  & 0xFF;
                        $b = $rgb & 0xFF;
                        $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                        if ($lum < 128) {
                            $byte |= (0x80 >> $bit);
                        }
                    }
                }
                $data .= chr($byte);
            }
        }
        imagedestroy($img);

        $xL = $widthBytes % 256;
        $xH = (int)($widthBytes / 256);
        $yL = $origH % 256;
        $yH = (int)($origH / 256);
        $this->alignCenter();
        $this->buffer .= self::GS . 'v' . '0' . "\x00" . chr($xL) . chr($xH) . chr($yL) . chr($yH) . $data;
        $this->buffer .= self::LF;
        $this->alignLeft();
        return $this;
    }

    public function barcode(string $data): self
    {
        if ($data === '') {
            return $this;
        }
        $this->alignCenter();
        $this->buffer .= self::GS . 'h' . chr(60);
        $this->buffer .= self::GS . 'w' . chr(2);
        $this->buffer .= self::GS . 'H' . chr(2);
        $this->buffer .= self::GS . 'f' . chr(1);
        $this->buffer .= self::GS . 'k' . chr(0x49) . chr(strlen($data)) . $data;
        $this->buffer .= self::LF;
        $this->alignLeft();
        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    //  Envío por red
    // ─────────────────────────────────────────────────────────────

    public function sendToNetwork(string $ip, int $port = 9100, int $timeoutSec = 5): bool
    {
        $this->lastErrno  = 0;
        $this->lastErrstr = '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->lastErrstr = 'IP inválida';
            return false;
        }
        $port = max(1, min(65535, $port));
        $sock = @fsockopen($ip, $port, $this->lastErrno, $this->lastErrstr, $timeoutSec);
        if (!$sock) {
            return false;
        }
        fwrite($sock, $this->buffer);
        fclose($sock);
        return true;
    }

    // ─────────────────────────────────────────────────────────────
    //  Constructor de ticket de venta (Factura o Albarán)
    // ─────────────────────────────────────────────────────────────

    /**
     * Construye un ticket ESC/POS a partir de un documento de venta.
     * Compatible con FacturaCliente y AlbaranCliente.
     *
     * @param FacturaCliente|AlbaranCliente $doc
     */
    public static function fromDoc(
        FacturaCliente $doc,
        Empresa        $empresa,
        FormaPago      $formaPago,
        string         $logoPath = ''
    ): self {
        $efectivo = (float)($doc->tpv_efectivo ?? 0.0);
        $cambio   = (float)($doc->tpv_cambio   ?? 0.0);
        $t = new self();
        $t->init();

        // ── Logo ──────────────────────────────────────────────
        if ($logoPath !== '') {
            $t->logo($logoPath);
        }

        // ── Cabecera: usuario + empresa (igual que el HTML) ──
        $t->alignCenter();
        $t->bold(true);
        $t->line(strtoupper((string)$doc->nick));
        $t->bold(false);
        if (!empty($empresa->nombrecorto)) {
            $t->line((string)$empresa->nombrecorto);
        }
        if (!empty($empresa->direccion)) {
            $t->line((string)$empresa->direccion);
        }
        if (!empty($empresa->telefono1)) {
            $t->line('Tel: ' . (string)$empresa->telefono1);
        }
        if (!empty($empresa->cifnif)) {
            $t->line('NIF/CIF: ' . (string)$empresa->cifnif);
        }
        $t->separator();

        // ── Tipo de documento ─────────────────────────────────
        $t->bold(true);
        $t->line('Factura Simplificada');
        $t->bold(false);
        $t->alignLeft();
        $t->line();
        $fecha = date('d-m-Y', strtotime((string)$doc->fecha));
        $hora  = $doc->hora ? date('H:i:s', strtotime((string)$doc->hora)) : '';
        $t->line('Fecha: ' . $fecha . ($hora ? ' ' . $hora : ''));
        $t->line('Documento: ' . (string)$doc->codigo);
        $t->separator();

        // ── Cabecera de columnas ──────────────────────────────
        $header = str_pad('Descripcion', self::PRODUCT_DESC_WIDTH)
            . str_pad('IMP', self::PRODUCT_IMP_WIDTH, ' ', STR_PAD_LEFT);
        $t->bold(true);
        $t->line($header);
        $t->bold(false);
        $t->line();

        // ── Líneas de producto ────────────────────────────────
        $ivaGroups = [];
        foreach ($doc->getLines() as $item) {
            $totalStr = number_format($item->pvptotal, 2, '.', '') . ' €';
            $descLines = $t->wrapText((string)($item->descripcion ?? ''), self::PRODUCT_DESC_WIDTH);
            $first = array_shift($descLines);
            $t->buffer .= str_pad($t->encode($first), self::PRODUCT_DESC_WIDTH)
                . str_pad($t->encode($totalStr), self::PRODUCT_IMP_WIDTH, ' ', STR_PAD_LEFT)
                . self::LF;
            foreach ($descLines as $wrap) {
                $t->buffer .= $t->encode('  ' . $wrap) . self::LF;
            }
            $rate = (float)$item->iva;
            $rateKey = (string)$rate;
            if (!isset($ivaGroups[$rateKey])) {
                $ivaGroups[$rateKey] = ['neto' => 0.0, 'iva' => 0.0];
            }
            $baseItem = $item->pvptotal / (1 + $item->iva / 100);
            $ivaItem = $item->pvptotal - $baseItem;
            $ivaGroups[$rateKey]['neto'] += $baseItem;
            $ivaGroups[$rateKey]['iva'] += $ivaItem;
        }
        $t->separator();

        // ── Totales ───────────────────────────────────────────
        $t->twoColumns('TOTAL:', number_format($doc->total, 2, '.', '') . ' €', true);
        $t->line();
        
        // ── Tabla de desglose IVA ─────────────────────────────
        if (!empty($ivaGroups)) {
            $t->bold(true);
            $t->line($t->formatIvaRow('IVA', 'NETO', 'TOTAL IVA', 'BRUTO'));
            $t->bold(false);
            ksort($ivaGroups);
            foreach ($ivaGroups as $rate => $data) {
                $neto = $data['neto'];
                $iva = $data['iva'];
                $bruto = $neto + $iva;
                $col1 = number_format($rate, 2, ',', '') . '%';
                $col2 = number_format($neto, 2, ',', '') . ' €';
                $col3 = number_format($iva, 2, ',', '') . ' €';
                $col4 = number_format($bruto, 2, ',', '') . ' €';

                $t->line($t->formatIvaRow($col1, $col2, $col3, $col4));
            }
            $t->line();
        }
        $label = !empty($formaPago->descripcion) ? strtoupper((string)$formaPago->descripcion) . ':' : '';
        if ($efectivo > 0.0) {
            $t->twoColumns($label, number_format($efectivo, 2, '.', '') . ' €');
            $t->twoColumns('CAMBIO:', number_format($cambio, 2, '.', '') . ' €');
        } else {
            $t->twoColumns($label, '0.00');
            $t->twoColumns('CAMBIO:', '0.00 €');
        }
        $t->separator();

        // ── Pie ───────────────────────────────────────────────
        $formato = new FormatoDocumento();
        $whereFormato = [
            new DataBaseWhere('codserie', 'S'),
            new DataBaseWhere('idempresa', $doc->idempresa)
        ];
        $flagFooter = $formato->loadFromCode('', $whereFormato);

        $t->alignCenter();
        $t->bold(true);
        $t->line('Gracias por su compra.');
        $t->bold(false);
        if ($flagFooter && !empty($formato->texto)) {
            $t->line((string)$formato->texto);
        }
        $t->feed(3);
        $t->cut();

        return $t;
    }

    // ─────────────────────────────────────────────────────────────
    //  Preview HTML (simula papel térmico, sin imprimir)
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve el ticket como HTML con apariencia de papel térmico.
     * Útil para previsualizar el diseño sin necesidad de imprimir.
     *
     * @param FacturaCliente|AlbaranCliente $doc
     */
    public static function previewHtml(
        $doc,
        Empresa   $empresa,
        FormaPago $formaPago,
        string    $logoPath = ''
    ): string {
        $efectivo = (float)($doc->tpv_efectivo ?? 0.0);
        $cambio   = (float)($doc->tpv_cambio   ?? 0.0);
        $w = self::WIDTH;

        $lines  = [];
        $bold   = false;
        $align  = 'left';

        $pushLine = function (string $text = '') use (&$lines, &$bold, &$align) {
            $lines[] = [
                'text'  => $text,
                'bold'  => $bold,
                'align' => $align,
            ];
        };

        $separator = function (string $char = '-') use (&$lines, $w) {
            $lines[] = [
                'text'  => str_repeat($char, $w),
                'bold'  => false,
                'align' => 'left',
                'pre'   => true,
            ];
        };

        $twoCol = function (string $left, string $right, bool $b = false) use (&$lines) {
            $lines[] = ['type' => 'row', 'left' => $left, 'right' => $right, 'bold' => $b];
        };

        $fourColHeader = function (string $c1, string $c2, string $c3, string $c4) use (&$lines) {
            $lines[] = ['type' => 'four-header', 'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4];
        };

        $fourColData = function (string $c1, string $c2, string $c3, string $c4) use (&$lines) {
            $lines[] = ['type' => 'four-data', 'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4];
        };

        $productLineHtml = function (string $desc, string $qty, string $pvu, string $total) use (&$lines, $w) {
            $c1 = 24;
            $c2 = 5;
            $c3 = 9;
            $c4 = 10;
            $wrapFn = function (string $text, int $width): array {
                if (mb_strlen($text) <= $width) return [$text];
                $result = [];
                while (mb_strlen($text) > $width) {
                    $pos = mb_strrpos(mb_substr($text, 0, $width), ' ');
                    if ($pos === false || $pos === 0) $pos = $width;
                    $result[] = mb_substr($text, 0, $pos);
                    $text = ltrim(mb_substr($text, $pos));
                }
                if ($text !== '') $result[] = $text;
                return $result ?: [''];
            };
            $wrapped = $wrapFn($desc, $c1);
            $first   = array_shift($wrapped);
            $row = str_pad($first, $c1)
                . str_pad($qty,   $c2, ' ', STR_PAD_LEFT)
                . str_pad($pvu,   $c3, ' ', STR_PAD_LEFT)
                . str_pad($total, $c4, ' ', STR_PAD_LEFT);
            $lines[] = ['text' => $row, 'bold' => false, 'align' => 'left', 'pre' => true];
            foreach ($wrapped as $wrap) {
                $lines[] = ['text' => '  ' . $wrap, 'bold' => false, 'align' => 'left', 'pre' => true];
            }
        };

        // ── Logo ──────────────────────────────────────────────
        $logoHtml = '';
        if ($logoPath !== '' && file_exists($logoPath)) {
            $mime = mime_content_type($logoPath);
            $allowed = ['image/png', 'image/jpeg', 'image/gif'];
            if (in_array($mime, $allowed, true)) {
                $data = base64_encode(file_get_contents($logoPath));
                $logoHtml = '<div style="text-align:center;margin-bottom:16px">'
                    . '<img src="data:' . htmlspecialchars($mime) . ';base64,' . $data
                    . '" style="max-width:200px;filter:grayscale(100%)" alt="logo"></div>';
            }
        }

        // ── Cabecera empresa ──────────────────────────────────
        $align = 'center';
        $bold  = true;
        if (!empty($empresa->nombrecorto)) {
            $lines[] = ['text' => strtoupper((string)$empresa->nombrecorto), 'bold' => true, 'align' => 'center', 'large' => true];
        }
        $bold = false;
        if (!empty($empresa->direccion)) {
            $pushLine((string)$empresa->direccion);
        }
        if (!empty($empresa->telefono1)) {
            $pushLine('Tel: ' . $empresa->telefono1);
        }
        if (!empty($empresa->cifnif)) {
            $pushLine('NIF/CIF: ' . $empresa->cifnif);
        }
        $pushLine();

        // ── Tipo de documento ─────────────────────────────────
        $align = 'center';
        $bold  = true;
        $esFactura = ($doc instanceof FacturaCliente);
        $pushLine($esFactura ? 'Factura Simplificada' : 'ALBARÁN');
        $bold  = false;
        $align = 'left';
        $pushLine();
        $fecha = date('d-m-Y', strtotime((string)$doc->fecha));
        $hora  = !empty($doc->hora) ? date('H:i:s', strtotime((string)$doc->hora)) : '';
        $pushLine('Fecha: ' . $fecha . ($hora ? ' ' . $hora : ''));
        $pushLine('Documento: ' . (string)$doc->codigo);
        $pushLine();

        // ── Cabecera columnas ─────────────────────────────────
        $lines[] = ['type' => 'row', 'left' => 'Descripcion', 'right' => 'IMP', 'bold' => 'both'];

        // ── Líneas de producto ────────────────────────────────
        $ivaGroups = [];
        foreach ($doc->getLines() as $item) {
            $totalStr = number_format($item->pvptotal, 2, '.', '') . ' €';
            $desc = (string)($item->descripcion ?? '');
            $wrapFn2 = function (string $text, int $width): array {
                if (mb_strlen($text) <= $width) return [$text];
                $result = [];
                while (mb_strlen($text) > $width) {
                    $pos = mb_strrpos(mb_substr($text, 0, $width), ' ');
                    if ($pos === false || $pos === 0) $pos = $width;
                    $result[] = mb_substr($text, 0, $pos);
                    $text = ltrim(mb_substr($text, $pos));
                }
                if ($text !== '') $result[] = $text;
                return $result ?: [''];
            };
            $wrapped = $wrapFn2($desc, self::PRODUCT_DESC_WIDTH);
            $first   = array_shift($wrapped);
            $lines[] = ['type' => 'row', 'left' => $first, 'right' => $totalStr, 'bold' => false];
            foreach ($wrapped as $wrap) {
                $lines[] = ['type' => 'text', 'text' => '  ' . $wrap, 'bold' => false, 'align' => 'left'];
            }
            $rate = (float)$item->iva;
            $rateKey = (string)$rate;
            if (!isset($ivaGroups[$rateKey])) {
                $ivaGroups[$rateKey] = ['neto' => 0.0, 'iva' => 0.0];
            }
            $baseItem = $item->pvptotal / (1 + $item->iva / 100);
            $ivaItem = $item->pvptotal - $baseItem;
            $ivaGroups[$rateKey]['neto'] += $baseItem;
            $ivaGroups[$rateKey]['iva'] += $ivaItem;
        }
        $pushLine();

        // ── Totales ───────────────────────────────────────────
        $lines[] = ['type' => 'row', 'left' => 'TOTAL:', 'right' => number_format($doc->total, 2, '.', '') . ' €', 'bold' => 'both'];
        $pushLine();
        
        // ── Tabla de desglose IVA ─────────────────────────────
        if (!empty($ivaGroups)) {
            $fourColHeader('IVA', 'NETO', 'TOTAL IVA', 'BRUTO');
            ksort($ivaGroups);
            foreach ($ivaGroups as $rate => $data) {
                $neto = $data['neto'];
                $iva = $data['iva'];
                $bruto = $neto + $iva;
                $col1 = number_format($rate, 2, ',', '') . '%';
                $col2 = number_format($neto, 2, ',', '') . ' €';
                $col3 = number_format($iva, 2, ',', '') . ' €';
                $col4 = number_format($bruto, 2, ',', '') . ' €';
                
                $fourColData($col1, $col2, $col3, $col4);
            }
            $pushLine();
        }
        $label = !empty($formaPago->descripcion) ? strtoupper((string)$formaPago->descripcion) . ':' : 'PAGO:';
        if ($efectivo > 0.0) {
            $twoCol($label, number_format($efectivo, 2, '.', '') . ' €', true);
            $twoCol('CAMBIO:', number_format($cambio, 2, '.', '') . ' €', true);
        } else {
            $twoCol($label, '0.00 €', true);
            $twoCol('CAMBIO:', '0.00 €', true);
        }
        $pushLine();

        // ── Pie ───────────────────────────────────────────────
        $formatoPrev = new FormatoDocumento();
        $whereFormatoPrev = [
            new DataBaseWhere('codserie', 'S'),
            new DataBaseWhere('idempresa', $doc->idempresa)
        ];
        $flagFooterPrev = $formatoPrev->loadFromCode('', $whereFormatoPrev);

        $align = 'center';
        $bold  = true;
        $pushLine('Gracias por su compra.');
        $bold = false;
        if ($flagFooterPrev && !empty($formatoPrev->texto)) {
            $lines[] = ['text' => (string)$formatoPrev->texto, 'bold' => false, 'align' => 'center', 'small' => true];
        }
        $pushLine();

        // ── Renderizar HTML ───────────────────────────────────
        $inner = '';
        foreach ($lines as $l) {
            $ltype = $l['type'] ?? 'text';
            if ($ltype === 'row') {
                $boldBoth = ($l['bold'] ?? false) === 'both';
                $bLeft  = (!empty($l['bold']))  ? ' style="font-weight:bold;"' : '';
                $bRight = $boldBoth              ? ' style="font-weight:bold;"' : '';
                $inner .= '<div style="display:flex;justify-content:space-between;">';
                $inner .= '<span' . $bLeft  . '>' . htmlspecialchars($l['left']  ?? '') . '</span>';
                $inner .= '<span' . $bRight . '>' . htmlspecialchars($l['right'] ?? '') . '</span>';
                $inner .= '</div>';
                continue;
            }
            if ($ltype === 'four-header') {
                $w1 = self::IVA_W1;
                $w2 = self::IVA_W2;
                $w3 = self::IVA_W3;
                $w4 = self::IVA_W4;
                $c1 = $l['c1'] ?? '';
                $c2 = $l['c2'] ?? '';
                $c3 = $l['c3'] ?? '';
                $c4 = $l['c4'] ?? '';
                $row = str_pad($c1, $w1)
                     . str_repeat(' ', max(0, $w2 - mb_strlen($c2))) . $c2
                     . str_repeat(' ', max(0, $w3 - mb_strlen($c3))) . $c3
                     . str_repeat(' ', max(0, $w4 - mb_strlen($c4))) . $c4;
                $inner .= '<div style="white-space:pre;font-weight:bold;">' . htmlspecialchars($row) . '</div>';
                continue;
            }
            if ($ltype === 'four-data') {
                $w1 = self::IVA_W1;
                $w2 = self::IVA_W2;
                $w3 = self::IVA_W3;
                $w4 = self::IVA_W4;
                $c1 = $l['c1'] ?? '';
                $c2 = $l['c2'] ?? '';
                $c3 = $l['c3'] ?? '';
                $c4 = $l['c4'] ?? '';
                $row = str_pad($c1, $w1)
                     . str_repeat(' ', max(0, $w2 - mb_strlen($c2))) . $c2
                     . str_repeat(' ', max(0, $w3 - mb_strlen($c3))) . $c3
                     . str_repeat(' ', max(0, $w4 - mb_strlen($c4))) . $c4;
                $inner .= '<div style="white-space:pre;">' . htmlspecialchars($row) . '</div>';
                continue;
            }
            $tag  = 'div';
            $text = htmlspecialchars($l['text'] ?? '');
            $css  = '';
            if (($l['align'] ?? 'left') === 'center') {
                $css .= 'text-align:center;';
            } else {
                $css .= 'text-align:left;';
            }
            if (!empty($l['bold'])) {
                $css .= 'font-weight:bold;';
            }
            if (!empty($l['large'])) {
                $css .= 'font-size:1.4em;margin-bottom:2px;';
            }
            if (!empty($l['small'])) {
                $css .= 'font-size:0.85em;';
            }
            $styleAttr = $css ? ' style="' . $css . '"' : '';
            if ($text === '') {
                $inner .= '<div>&nbsp;</div>';
            } else {
                $inner .= "<{$tag}{$styleAttr}>{$text}</{$tag}>";
            }
        }

        return '<div style="'
            . 'font-family:\'Courier New\',Courier,monospace;'
            . 'font-size:13px;'
            . 'line-height:1.3;'
            . 'background:#fff;'
            . 'color:#000;'
            . 'max-width:420px;'
            . 'margin:0 auto;'
            . 'padding:16px 12px;'
            . 'border:1px solid #ccc;'
            . 'box-shadow:2px 2px 8px rgba(0,0,0,.15);'
            . '">'
            . $logoHtml
            . $inner
            . '</div>';
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers privados
    // ─────────────────────────────────────────────────────────────

    private function encode(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'CP858//TRANSLIT', $text);
            return ($converted !== false) ? $converted : $text;
        }
        return $text;
    }

    private function formatIvaRow(string $c1, string $c2, string $c3, string $c4): string
    {
        return str_pad($c1, self::IVA_W1)
            . str_repeat(' ', max(0, self::IVA_W2 - mb_strlen($c2))) . $c2
            . str_repeat(' ', max(0, self::IVA_W3 - mb_strlen($c3))) . $c3
            . str_repeat(' ', max(0, self::IVA_W4 - mb_strlen($c4))) . $c4;
    }

    private function wrapText(string $text, int $width): array
    {
        if (mb_strlen($text) <= $width) {
            return [$text];
        }
        $result = [];
        while (mb_strlen($text) > $width) {
            $pos = mb_strrpos(mb_substr($text, 0, $width), ' ');
            if ($pos === false || $pos === 0) {
                $pos = $width;
            }
            $result[] = mb_substr($text, 0, $pos);
            $text     = ltrim(mb_substr($text, $pos));
        }
        if ($text !== '') {
            $result[] = $text;
        }
        return $result ?: [''];
    }
}
