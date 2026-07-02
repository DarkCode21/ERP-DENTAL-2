<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Lib;

use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FormaPago;

/**
 * Generador de tickets ESC/POS para impresoras térmicas.
 * Envía los comandos directamente por socket TCP (red/Ethernet).
 * Compatible con impresoras 80 mm tipo 10POS RP-12N y similares.
 */
class TicketESCPos
{
    // Comandos ESC/POS
    private const ESC = "\x1B";
    private const GS  = "\x1D";
    private const LF  = "\n";

    // Ancho en caracteres conservador para evitar saltos en impresoras estrechas
    private const WIDTH = 42;

    // Columnas compactas de líneas de producto (suma = WIDTH)
    private const PROD_W1 = 18; // Descripcion
    private const PROD_W2 = 4;  // Ud
    private const PROD_W3 = 9;  // P/u
    private const PROD_W4 = 11; // Total

    private string $buffer = '';
    private int    $lastErrno  = 0;
    private string $lastErrstr = '';

    public function getLastError(): string
    {
        return $this->lastErrno . ' - ' . $this->lastErrstr;
    }

    // ─────────────────────────────────────────────────────────────
    //  Comandos primitivos
    // ─────────────────────────────────────────────────────────────

    public function init(): self
    {
        $this->buffer  = self::ESC . '@';       // Inicializar impresora
        $this->buffer .= self::ESC . 't' . "\x13"; // Código de página CP858 (español + €)
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

    /** Avanzar n líneas */
    public function feed(int $lines = 3): self
    {
        $this->buffer .= self::ESC . 'd' . chr(min($lines, 255));
        return $this;
    }

    /** Corte de papel */
    public function cut(): self
    {
        $this->buffer .= self::GS . 'V' . "\x41" . "\x00"; // Full cut
        return $this;
    }

    /** Imprime una línea de texto seguida de LF */
    public function line(string $text = ''): self
    {
        $this->buffer .= $this->encode($text) . self::LF;
        return $this;
    }

    /**
     * Imprime un logo desde un archivo de imagen (PNG/JPG/GIF).
     * Usa GS v 0 (raster bitmap ESC/POS). Max 384px ancho.
     */
    public function logo(string $filePath): self
    {
        if (!function_exists('imagecreatefrompng') || !file_exists($filePath)) {
            return $this;
        }
        $mime = mime_content_type($filePath);
        $img  = null;
        if ($mime === 'image/png')  $img = @imagecreatefrompng($filePath);
        elseif ($mime === 'image/jpeg') $img = @imagecreatefromjpeg($filePath);
        elseif ($mime === 'image/gif')  $img = @imagecreatefromgif($filePath);
        if (!$img) return $this;

        // Redimensionar a max 384px ancho manteniendo ratio
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

        // Convertir a 1 bit (blanco/negro)
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

        // Comando GS v 0: imprime raster bitmap
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

    /**
     * Imprime un código de barras CODE128 centrado.
     */
    public function barcode(string $data): self
    {
        if ($data === '') {
            return $this;
        }
        $this->alignCenter();
        // Altura del código de barras (puntos)
        $this->buffer .= self::GS . 'h' . chr(60);
        // Ancho de cada barra (1-6)
        $this->buffer .= self::GS . 'w' . chr(2);
        // Texto HRI debajo
        $this->buffer .= self::GS . 'H' . chr(2);
        // Fuente HRI pequeña
        $this->buffer .= self::GS . 'f' . chr(1);
        // Imprimir CODE128: GS k 0x49 n data
        $this->buffer .= self::GS . 'k' . chr(0x49) . chr(strlen($data)) . $data;
        $this->buffer .= self::LF;
        $this->alignLeft();
        return $this;
    }

    /** Línea separadora de guiones */
    public function separator(string $char = '-'): self
    {
        $this->buffer .= str_repeat($char, self::WIDTH) . self::LF;
        return $this;
    }

    /** Dos columnas: texto izquierda y texto derecha alineado al borde */
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

    /**
     * Línea de producto con 4 columnas compactas.
     */
    public function productLine(string $desc, string $qty, string $pvu, string $total): self
    {
        $c1 = self::PROD_W1;
        $c2 = self::PROD_W2;
        $c3 = self::PROD_W3;
        $c4 = self::PROD_W4;
        $lines  = $this->wrapText($desc, $c1);
        $first  = array_shift($lines);
        // Codificar antes de str_pad para que € ocupe 1 byte (CP858)
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

    // ─────────────────────────────────────────────────────────────
    //  Envío por red
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía el buffer a la impresora por TCP.
     * Devuelve true si se envió correctamente, false en caso de error.
     */
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

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    // ─────────────────────────────────────────────────────────────
    //  Constructor de ticket de factura
    // ─────────────────────────────────────────────────────────────

    /**
     * Construye un ticket completo a partir de una FacturaCliente.
     */
    public static function fromFactura(
        FacturaCliente $doc,
        Empresa        $empresa,
        FormaPago      $formaPago,
        string         $camarero   = '',
        string         $mesaNombre = '',
        string         $idComanda  = '',
        float          $efectivo   = 0.0,
        float          $cambio     = 0.0,
        string         $logoPath   = ''
    ): self {
        $t = new self();
        $t->init();

        // ── Logo ──────────────────────────────────────────────
        if ($logoPath !== '') {
            $t->logo($logoPath);
        }

        // ── Cabecera: camarero + empresa (igual que el HTML) ──
        $t->alignCenter();
        $t->bold(true);
        $t->line(strtoupper($camarero ?: 'TPV'));
        $t->bold(false);
        if (!empty($empresa->nombrecorto)) {
            $t->line((string)$empresa->nombrecorto);
        }
        if (!empty($empresa->direccion)) {
            $t->line((string)$empresa->direccion);
        }
        if (!empty($empresa->telefono1)) {
            $t->line('Tel: ' . $empresa->telefono1);
        }
        if (!empty($empresa->cifnif)) {
            $t->line('NIF/CIF: ' . $empresa->cifnif);
        }
        $t->separator();

        // ── Tipo de documento ─────────────────────────────────
        $t->bold(true);
        $t->line('FACTURA SIMPLIFICADA');
        $t->bold(false);
        $t->line('Nº ' . (string)$doc->codigo);
        $t->alignLeft();
        $t->line();
        $fecha = date('d-m-Y', strtotime((string)$doc->fecha));
        $hora  = $doc->hora ? date('H:i:s', strtotime((string)$doc->hora)) : '';
        $t->line('Fecha: ' . $fecha . ($hora ? ' ' . $hora : ''));
        $t->line('Comanda: #' . $idComanda);
        if ($mesaNombre !== '') {
            $t->line('Mesa: ' . $mesaNombre);
        }
        if (!empty($doc->nombrecliente)) {
            $t->line('Cliente: ' . (string)$doc->nombrecliente);
        }
        $t->separator();

        // ── Cabecera de columnas ──────────────────────────────
        $header = str_pad('Descripcion', self::PROD_W1)
            . str_pad('Ud',    self::PROD_W2, ' ', STR_PAD_LEFT)
            . str_pad('P/u',   self::PROD_W3, ' ', STR_PAD_LEFT)
            . str_pad('Total', self::PROD_W4, ' ', STR_PAD_LEFT);
        $t->bold(true);
        $t->line($header);
        $t->bold(false);
        $t->separator();

        // ── Líneas de producto ────────────────────────────────
        $ivaGroups = [];
        foreach ($doc->getLines() as $item) {
            $qty   = $item->cantidad;
            $pvu   = $item->pvpunitario;
            $total = round($qty * $pvu, 2);
            $qtyStr   = ($qty == (int)$qty) ? (string)(int)$qty : number_format($qty, 2, '.', '');
            $pvuStr   = number_format($pvu,   2, '.', '') . ' €';
            $totalStr = number_format($total, 2, '.', '') . ' €';
            $t->productLine((string)($item->descripcion ?? ''), $qtyStr, $pvuStr, $totalStr);
            $rate = (float)$item->iva;
            $rateKey = (string)$rate;
            $ivaGroups[$rateKey] = ($ivaGroups[$rateKey] ?? 0.0)
                + round($item->pvptotal * $item->iva / 100, 2);
        }
        $t->separator();

        // ── Totales ───────────────────────────────────────────
        $t->twoColumns('NETO:', number_format($doc->neto, 2, '.', '') . ' €');
        ksort($ivaGroups);
        foreach ($ivaGroups as $rate => $amount) {
            if ($amount <= 0.0) {
                continue;
            }
            $t->twoColumns('IVA (' . $rate . '%):', number_format($amount, 2, '.', '') . ' €');
        }
        $t->twoColumns('TOTAL:', number_format($doc->total, 2, '.', '') . ' €', true);
        $t->line();

        if (!empty($formaPago->descripcion)) {
            $t->twoColumns('', strtoupper((string)$formaPago->descripcion));
        }
        if ($efectivo > 0.0) {
            $t->twoColumns('ENTREGADO:', number_format($efectivo, 2, '.', '') . ' €');
            $t->twoColumns('CAMBIO:',    number_format($cambio,   2, '.', '') . ' €');
        }
        $t->separator();

        // ── Pie ───────────────────────────────────────────────
        $t->alignCenter();
        $t->bold(true);
        $t->line('Gracias por su visita.');
        $t->bold(false);
        $t->feed(1);
        $t->barcode((string)$doc->codigo);
        $t->feed(3);
        $t->cut();

        return $t;
    }

    // ─────────────────────────────────────────────────────────────
    //  Constructor de KOT (comanda de cocina/barra)
    // ─────────────────────────────────────────────────────────────

    /**
     * Construye un ticket KOT para una estación de preparación.
     *
     * @param string  $estacionNombre  Nombre de la estación (Cocina, Bar…)
     * @param array   $lineas          Cada elemento: ['descripcion'=>..., 'cantidad'=>..., 'observaciones'=>...]
     * @param string  $mesaNombre      Nombre de la mesa o tipo de servicio
     * @param int     $idComanda       Número de comanda
     */
    public static function fromKOT(
        string $estacionNombre,
        array  $lineas,
        string $mesaNombre  = '',
        int    $idComanda   = 0,
        string $docCodigo   = '',
        string $fecha       = ''
    ): self {
        $t = new self();
        $t->init();

        $ahora = date('d-m-Y H:i');
        if ($fecha === '') {
            $fecha = $ahora;
        }

        // ── Cabecera ──────────────────────────────────────────
        $t->alignCenter();
        $t->bold(true);
        $t->buffer .= self::GS . '!' . "\x11"; // doble ancho + doble alto
        $t->line(strtoupper($estacionNombre));
        $t->buffer .= self::GS . '!' . "\x00"; // tamaño normal
        $t->bold(false);
        $t->line();

        $t->alignCenter();
        if ($docCodigo !== '') {
            $t->line('Nº ' . $docCodigo);
            $t->line();
        }
        $t->alignLeft();
        $t->line('Fecha: ' . $fecha);
        if ($idComanda > 0) {
            $t->bold(true);
            $t->line('Comanda: #' . $idComanda);
        }
        if ($mesaNombre !== '') {
            $t->line('Mesa: ' . $mesaNombre);
        }
        $t->separator();

        // Cabeceras columnas
        $t->bold(true);
        $hDesc = 'Descripcion';
        $hQty  = 'Cant.';
        $pad = self::WIDTH - mb_strlen($hDesc) - mb_strlen($hQty);
        $t->line($hDesc . str_repeat(' ', max(1, $pad)) . $hQty);
        $t->bold(false);
        $t->separator();

        // ── Líneas ────────────────────────────────────────────
        foreach ($lineas as $linea) {
            $desc  = (string)($linea['descripcion'] ?? '');
            $qty   = $linea['cantidad'] ?? 1;
            $qtyStr = ($qty == (int)$qty) ? (string)(int)$qty : number_format((float)$qty, 2, '.', '');
            $obs   = (string)($linea['observaciones'] ?? '');

            $rLen = mb_strlen($qtyStr);
            $lMax = self::WIDTH - $rLen - 1;
            $descShort = mb_strlen($desc) > $lMax ? mb_substr($desc, 0, $lMax) : $desc;
            $pad = self::WIDTH - mb_strlen($descShort) - $rLen;
            $row = $descShort . str_repeat(' ', max(1, $pad)) . $qtyStr;
            $t->line($row);

            if ($obs !== '') {
                $t->line('  >> ' . $obs);
            }

            // Modificadores / adicionales
            $mods = $linea['mods'] ?? [];
            foreach ($mods as $mod) {
                $modDesc = (string)($mod['desc'] ?? '');
                $modQty  = $mod['qty'] ?? 1;
                $modQtyStr = ($modQty == (int)$modQty) ? (string)(int)$modQty : number_format((float)$modQty, 2, '.', '');
                $t->line($modDesc . ($modQtyStr !== '1' ? ' x' . $modQtyStr : ''));
            }
        }

        $t->separator();
        $t->feed(3);
        $t->cut();

        return $t;
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers privados
    // ─────────────────────────────────────────────────────────────

    /** Convierte texto UTF-8 a CP858 (compatible impresoras térmicas + español + €) */
    private function encode(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'CP858//TRANSLIT', $text);
            return ($converted !== false) ? $converted : $text;
        }
        return $text;
    }

    /** Rompe texto largo en varias líneas de ancho máximo dado */
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
