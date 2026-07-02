<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF; 

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\BusinessDocumentLine;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\PurchaseDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\PlantillasPDF\Helper\BusinessDocLinesHelper;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FormatoDocumento;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Retencion;
use FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Helper\QRcodeTrait;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Description of BaseTemplate
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
abstract class BaseTemplate
{
    use ExtensionsTrait;
    use QRcodeTrait;

    const DEFAULT_LOGO = 'Dinamic/Assets/Images/logo-100.png';
    const MAX_IMAGE_FILE_SIZE = 2048000;
    const MEGACITY20_LOGO = 'Plugins/MC20Instance/Assets/Images/logo-100.png';

    /** @var string */
    public $body = '';

    /** @var string */
    protected $config = [];

    /** @var Empresa */
    protected $empresa;

    /** @var array */
    protected $fixedBlocks = [];

    /** @var FormatoDocumento */
    public $format;

    /** @var BusinessDocument */
    public $headerModel;

    /** @var string */
    protected $imagetextPath;

    /** @var string */
    protected $imagefooterPath;

    /** @var bool */
    public $initHtml = false;

    /** @var bool */
    public $isBusinessDoc = false;

    /** @var string */
    protected $logoPath;

    /** @var MPDF */
    public $mpdf = null;

    /** @var bool */
    protected $showHeaderTitle = true;

    abstract public function addInvoiceFooter($model);

    abstract public function addInvoiceHeader($model);

    abstract public function addInvoiceLines($model);

    public function __construct()
    {
        $this->setLogoDefault();
        $this->setEmpresa(Tools::settings('default', 'idempresa'));
        $this->setImage('imagetextPath', $this->get('idimagetext'));
        $this->setImage('imagefooterPath', $this->get('idimagefooter'));
    }

    public function addDualColumnTable(array $data): void
    {
        $html = '';
        $num = 0;
        foreach ($data as $row) {
            if ($num === 0) {
                $html .= '<tr>';
            } elseif ($num % 2 == 0) {
                $html .= '</tr><tr>';
            }

            $html .= '<td width="50%"><b>' . $row['title'] . '</b>: ' . $row['value'] . '</td>';
            $num++;
        }

        $html .= '</tr>';
        $this->writeHTML('<table class="table-big table-dual">' . $html . '</table><br/>');
    }

    public function addTable(array $rows, array $titles, array $alignments, array $css = []): void
    {
        $titlesCount = count($titles);

        $html = '<thead><tr>';
        foreach ($titles as $key => $title) {
            $html .= isset($alignments[$key]) ?
                '<th class="' . ($css[$key] ?? '') . '" align="' . $alignments[$key] . '">' . $title . '</th>' :
                '<th class="' . ($css[$key] ?? '') . '">' . $title . '</th>';
        }
        $html .= '</tr></thead>';

        foreach ($rows as $row) {
            $html .= '<tr>';

            if ($titlesCount != count($row)) {
                $row = $this->regenerateRowTable($row, $titles);
            }

            foreach ($row as $key => $cell) {
                // los espacios al principio de $cell los cambiamos por &nbsp;
                $cellWoSpaces = ltrim($cell);
                $spaces = str_repeat('&nbsp;', strlen($cell) - strlen($cellWoSpaces));
                $cell = $spaces . $cellWoSpaces;

                // añadimos la alineación
                $html .= isset($alignments[$key]) ?
                    '<td class="' . ($css[$key] ?? '') . '" align="' . $alignments[$key] . '">' . $cell . '</td>' :
                    '<td class="' . ($css[$key] ?? '') . '">' . $cell . '</td>';
            }
            $html .= '</tr>';
        }

        $this->writeHTML('<table class="table-big table-list">' . $html . '</table><br/>');
    }

    public function initHtml(): void
    {
        if ($this->initHtml === false) {
            $html = '<html>'
                . '<head>'
                . '<title>' . $this->get('title') . '</title>'
                . '<style>' . $this->css() . '</style>'
                . '</head>'
                . '<body>' . $this->body;
            $this->writeHTML($html);
            $this->initHtml = true;
        }
    }

    public function initMpdf(): void
    {
        if (is_null($this->mpdf)) {
            $orientation = strtolower(substr($this->get('orientation'), 0, 1)) === 'l' ? 'L' : 'P';

            $config = [
                'format' => $this->get('size') . '-' . $orientation,
                'margin_top' => $this->get('topmargin'),
                'margin_bottom' => $this->get('bottommargin'),
                'tempDir' => FS_FOLDER . '/MyFiles/Cache'
            ];

            $this->mpdf = new Mpdf($config);
            $this->mpdf->SetCreator('FacturaScripts');

            $password = $this->get('password');
            if (!empty($password)) {
                $this->mpdf->SetProtection(['copy', 'print', 'print-highres'], null, $password, 128);
            }
        }
    }

    public function output(string $fileName = ''): string
    {
        if (null === $this->mpdf) {
            $this->initMpdf();
            $this->initHtml();
        }

        // pintamos el QR
        $qrHtml = '<div style="position: absolute; top: ' . $this->get('qrpositiony') . 'mm; left: '
            . $this->get('qrpositionx') . 'mm; width: auto; height: auto;">' . $this->getQRcode() . '</div>';
        $this->writeHTML($qrHtml);

        foreach ($this->fixedBlocks as $block) {
            $this->mpdf->WriteFixedPosHTML($block['html'], $block['x'], $block['y'], $block['w'], $block['h']);
        }

        $this->writeHTML('</body></html>');
        return $this->mpdf->Output($fileName, Destination::STRING_RETURN);
    }

    public function setEmpresa(int $idempresa): void
    {
        // si no hay una empresa cargada, cargamos la indicada
        if (empty($this->empresa)) {
            $this->empresa = new Empresa();
            $this->empresa->loadFromCode($idempresa);
        }

        // si la empresa indicada es diferente a la cargada, la cargamos
        if ($idempresa != $this->empresa->idempresa) {
            $this->empresa->loadFromCode($idempresa);
        }

        // si ya hay un formato cargado y tiene idlogo, terminamos
        if (isset($this->format) && $this->format->idlogo) {
            return;
        }

        // si la empresa tiene logotipo, lo cargamos
        if ($this->empresa->idlogo) {
            $this->setImage('logoPath', $this->empresa->idlogo);
            return;
        }

        // no hay logotipo, cargamos el logotipo por defecto
        $this->setLogoDefault();
    }

    public function setFormat(FormatoDocumento $format): void
    {
        $this->format = $format;
        if (false === $format->exists()) {
            return;
        }

        $optionalFields = [
            'color1', 'linecolalignments', 'linecols', 'linecoltypes', 'orientation', 'size'
        ];
        foreach ($optionalFields as $field) {
            if ($format->{$field}) {
                $this->config[$field] = $format->{$field};
            }
        }

        $fields = [
            'footertext', 'hideobservations', 'hidepaymentmethods', 'hidereceipts', 'hidetotals', 'linesheight',
            'thankstext', 'thankstitle'
        ];
        foreach ($fields as $field) {
            $result = $this->pipe('setFormatField', $field);
            $this->config[$field] = (null === $result) ? $format->{$field} : $result;
        }

        if ($format->texto) {
            $result = $this->pipe('setFormatField', 'texto');
            $this->config['endtext'] = (null === $result) ? $format->texto : $result;
        }

        if ($format->idlogo) {
            $this->setImage('logoPath', $format->idlogo);
        }

        if ($format->idimagetext) {
            $this->setImage('imagetextPath', $format->idimagetext);
        }

        if ($format->idimagefooter) {
            $this->setImage('imagefooterPath', $format->idimagefooter);
        }
    }

    public function setHeaderTitle(string $title, bool $force = false): void
    {
        if (empty($this->config['headertitle']) || $force) {
            $this->config['headertitle'] = $title;
        }
    }

    public function setImage(string $var, ?int $idfile): void
    {
        $atFile = new AttachedFile();
        if ($idfile && $atFile->loadFromCode($idfile) && $atFile->size <= static::MAX_IMAGE_FILE_SIZE) {
            $this->{$var} = FS_FOLDER . '/' . $atFile->path;
        }
    }

    public function setOrientation(string $value): void
    {
        $this->config['orientation'] = $value;
    }

    public function setTitle(string $title, bool $force = false): void
    {
        if (empty($this->config['title']) || $force) {
            $this->config['title'] = $title;
        }
    }

    public function writeFixedPosHTML(string $html, float $x, float $y, float $w, float $h): void
    {
        $this->fixedBlocks[] = [
            'html' => $html,
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h
        ];
    }

    public function writeHTML(string $html): void
    {
        $this->mpdf->SetHTMLHeader($this->header(), 'O', true);
        $this->mpdf->SetHTMLFooter($this->footer(), 'O');

        $this->body .= $html;
        $this->mpdf->WriteHTML($html);
    }
	
	public function refreshHeader(): void
    {
        if (!is_null($this->mpdf)) {
            $this->mpdf->SetHTMLHeader($this->header(), 'O', false);
        }
    }

    protected function addPageBreak(float $currentY, ModelClass $model): void
    {
        $linesHeight = (float)$this->get('linesheight');
        if (empty($linesHeight)) {
            return;
        }

        $mm = (($linesHeight * 25.4) / 96) + $currentY;
        if ($this->mpdf->y <= $mm) {
            return;
        }

        if ('' !== $this->getObservations($model)) {
            $this->mpdf->AddPage();
            return;
        }

        if (method_exists($model, 'getReceipts') && empty($model->getReceipts())) {
            return;
        }

        if ($this->format->id && false === $this->format->hidetotals) {
            if (false === $this->format->hidepaymentmethods && false === $this->format->hidereceipts) {
                $this->mpdf->AddPage();
                return;
            }

            if (true === $this->format->hidepaymentmethods && false === $this->format->hidereceipts) {
                $this->mpdf->AddPage();
                return;
            }

            if (false === $this->format->hidepaymentmethods && true === $this->format->hidereceipts) {
                $this->mpdf->AddPage();
            }
            return;
        }

        // si no hay formato
        if (false === $this->get('hidepaymentmethods') && false === $this->get('hidereceipts')) {
            $this->mpdf->AddPage();
            return;
        }

        if (true === $this->get('hidepaymentmethods') && false === $this->get('hidereceipts')) {
            $this->mpdf->AddPage();
            return;
        }

        if (false === $this->get('hidepaymentmethods') && true === $this->get('hidereceipts')) {
            $this->mpdf->AddPage();
        }
    }

    protected function autoHideLineColumns(array $lines): void
    {
        // reiniciamos las columnas
        foreach (['linecols', 'linecolalignments', 'linecoltypes'] as $item) {
            if ($this->format && isset($this->format->{$item}) && false === empty($this->format->{$item})) {
                $this->set($item, $this->format->{$item});
            } else {
                $this->set($item, Tools::settings('plantillaspdf', $item));
            }
        }

        $alignments = [];
        $cols = [];
        $types = [];
        foreach ($this->getInvoiceLineFields() as $key => $field) {
            $show = false;
            foreach ($lines as $line) {
                if (isset($line->{$key}) && $line->{$key}
                    || $key === 'totaliva'
                    || $key === 'precioiva'
                    || $key === 'numlinea'
                    || $key === 'image'
                    || $key === 'pvpdto'
                    || $key === 'codbarras') {
                    $show = true;
                    break;
                }

                if ($key === 'refproveedor') {
                    $show = $line->getDocument() instanceof PurchaseDocument;
                    break;
                }
            }

            if ($show) {
                $cols[] = $key;
                $alignments[] = $field['align'];
                $types[] = $field['type'];
            }
        }

        $this->config['linecols'] = implode(',', $cols);
        $this->config['linecolalignments'] = implode(',', $alignments);
        $this->config['linecoltypes'] = implode(',', $types);
    }

    /**
     * @param BusinessDocument|Contacto $model
     */
    protected function combineAddress($model, bool $shipping = false): string
    {
        if (!isset($model->direccion)) {
            return '';
        }

        $completeAddress = '';
        if ($shipping && $model->nombre) {
            $completeAddress .= Tools::fixHtml($model->nombre) . ' ' . Tools::fixHtml($model->apellidos) . '<br>';
        }
        $completeAddress .= Tools::fixHtml($model->direccion);
        $completeAddress .= empty($model->apartado) ? '' : ', ' . Tools::lang()->trans('box') . ' ' . $model->apartado;
        $completeAddress .= empty($model->codpostal) ? '' : '<br/>' . $model->codpostal;
        $completeAddress .= empty($model->ciudad) ? '' : ', ' . Tools::fixHtml($model->ciudad);
        $completeAddress .= empty($model->provincia) ? '' : ' (' . Tools::fixHtml($model->provincia) . ')';
        $completeAddress .= empty($model->codpais) ? '' : ', ' . $this->getCountryName($model->codpais);

        // ¿Añadimos los teléfonos?
        $strPhones = property_exists($model, 'telefono1') ? $this->getPhones($model->telefono1, $model->telefono2) : '';
        if ($shipping && $this->get('showcustomerphones') && false === empty($strPhones)) {
            $completeAddress .= '<br/>' . $strPhones;
        }

        return $completeAddress;
    }

    protected function css(): string
    {
        return 'body {color: ' . $this->get('fontcolor') . '; font-family: ' . $this->get('font') . '; font-size: ' . $this->get('fontsize') . 'px;}'
            . '.font-big {font-size: ' . (2 + $this->get('fontsize')) . 'px;}'
            . '.m2 {margin: 2px;}'
            . '.m3 {margin: 3px;}'
            . '.m4 {margin: 4px;}'
            . '.m5 {margin: 5px;}'
            . '.m10 {margin: 10px;}'
            . '.mt-5 {margin-top: 5px;}'
            . '.mt-20 {margin-top: 20px;}'
            . '.mb-0 {margin-bottom: 0px;}'
            . '.p2 {padding: 2px;}'
            . '.p3 {padding: 3px;}'
            . '.p4 {padding: 4px;}'
            . '.p5 {padding: 5px;}'
            . '.p10 {padding: 10px;}'
            . '.spacer {font-size: 8px;}'
            . '.text-center {text-align: center;}'
            . '.text-left {text-align: left;}'
            . '.text-right {text-align: right;}'
            . '.border1 {border: 1px solid ' . $this->get('color1') . ';}'
            . '.no-border {border: 0px;}'
            . '.primary-box {background-color: ' . $this->get('color1') . '; color: ' . $this->get('color2') . '; padding: 10px; '
            . 'text-transform: uppercase; font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold;}'
            . '.seccondary-box {background-color: ' . $this->get('color3') . '; padding: 10px; '
            . 'text-transform: uppercase; font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold;}'
            . '.title {color: ' . $this->get('color1') . '; font-size: ' . $this->get('titlefontsize') . 'px;}'
            . '.table-big {width: 100%;}'
            . '.table-lines {height: ' . $this->get('linesheight') . 'px;}'
            . '.end-text {font-size: ' . $this->get('endfontsize') . 'px; text-align: ' . $this->get('endalign') . ';}'
            . '.footer-text {font-size: ' . $this->get('footerfontsize') . 'px; text-align: ' . $this->get('footeralign') . ';}'
            . '.color-red {color: red;}'
            . '.rotate-90 {rotate: -90;}'
            . '.font-bold {font-weight: bold;}'
            . '.nowrap {white-space: nowrap;}'
            . '.qrcode {color: red; background-color: blue; margin: 0; padding: 0;}';
    }

    protected function getImageText(): string
    {
        return empty($this->imagetextPath) ? '' :
            '<div class="imagetext"><img src="' . $this->imagetextPath . '" height="' . $this->get('imagetextsize') . '"/></div>';
    }

    protected function getImageFooter(): string
    {
        return empty($this->imagefooterPath) ? '' :
            '<div class="imagefooter"><img src="' . $this->imagefooterPath . '" height="' . $this->get('imagefootersize') . '"/></div>';
    }

    protected function footer(): string
    {
        $html = $this->getImageFooter();
        $html .= empty($this->get('footertext')) ? '' : '<p class="footer-text">' . nl2br($this->get('footertext')) . '</p>';
        return $html;
    }

    /**
     * @return mixed
     */
    protected function get(string $key)
    {
        $this->pipe('get', $key);

        if (!isset($this->config[$key])) {
            $this->config[$key] = Tools::settings('plantillaspdf', $key);
        }

        return $this->config[$key];
    }

    protected function getCountryName(?string $code): string
    {
        if (empty($code)) {
            return '';
        }

        $country = new Pais();
        return $country->loadFromCode($code) ? Tools::fixHtml($country->nombre) : '';
    }

    protected function getInvoiceLineFieldAlignment(int $num): string
    {
        $valid = ['left', 'right', 'center', 'justify'];
        foreach (explode(',', str_replace(' ', '', $this->get('linecolalignments'))) as $num2 => $value) {
            if ($num == $num2 && in_array($value, $valid)) {
                return $value;
            }
        }

        return 'left';
    }

    protected function getInvoiceLineFieldCss(int $num): string
    {
        $valid = [
            'number', 'number0', 'number1', 'number2', 'number3', 'number4', 'number5',
            'money', 'money0', 'money1', 'money2', 'money3', 'money4', 'money5',
            'percentage', 'percentage0', 'percentage1', 'percentage2', 'percentage3', 'percentage4', 'percentage5',
        ];
        foreach (explode(',', str_replace(' ', '', $this->get('linecolalignments'))) as $num2 => $value) {
            if ($num == $num2 && in_array($value, $valid)) {
                return 'nowrap';
            }
        }

        return '';
    }

    protected function getInvoiceLineFieldTitle(string $txt): string
    {
        if (strtolower($txt) === 'irpf') {
            return Tools::lang()->trans('retention-abb');
        }

        $codes = [
            'cantidad' => 'quantity-abb',
            'descripcion' => 'description',
            'dtopor' => 'dto',
            'dtopor2' => 'dto-2',
            'iva' => 'tax-abb',
            'numlinea' => 'line',
            'precioiva' => 'price-tax-abb',
            'pvpdto' => 'price-dto-abb',
            'pvpunitario' => 'price',
            'pvptotal' => 'net',
            'recargo' => 're',
            'referencia' => 'reference',
            'totaliva' => 'total'
        ];

        return isset($codes[$txt]) ? Tools::lang()->trans($codes[$txt]) : Tools::lang()->trans($txt);
    }

    protected function getInvoiceLineFieldType(int $num): string
    {
        $valid = [
            'money', 'money0', 'money1', 'money2', 'money3', 'money4', 'money5',
            'number', 'number0', 'number1', 'number2', 'number3', 'number4', 'number5',
            'percentage', 'percentage0', 'percentage1', 'percentage2', 'percentage3', 'percentage4', 'percentage5',
            'text'
        ];
        foreach (explode(',', str_replace(' ', '', $this->get('linecoltypes'))) as $num2 => $value) {
            if ($num == $num2 && in_array($value, $valid)) {
                return $value;
            }
        }

        return 'text';
    }

    protected function getInvoiceLineFields(): array
    {
        $fields = [];
        foreach (explode(',', str_replace(' ', '', $this->get('linecols'))) as $num => $key) {
            $fields[$key] = [
                'align' => $this->getInvoiceLineFieldAlignment($num),
                'css' => $this->getInvoiceLineFieldCss($num),
                'key' => $key,
                'title' => $this->getInvoiceLineFieldTitle($key),
                'type' => $this->getInvoiceLineFieldType($num)
            ];
        }

        return $fields;
    }

    protected function getInvoiceLineValue(BusinessDocument $model, BusinessDocumentLine $line, array $field): string
    {
        return BusinessDocLinesHelper::get($model, $line, $field);
    }

    protected function getInvoiceTaxes(BusinessDocument $model, array $lines, string $class = 'table-big'): string
    {
        if ($this->format->hide_vat_breakdown) {
            return '';
        }

        $taxes = $this->getTaxesRows($model, $lines);
        if (empty($taxes['iva']) && empty($model->totalirpf)) {
            return '';
        }

        $i18n = Tools::lang();
    
    	$impuesto = '';
    	$tax_base = '';
    	$total_iva = '';

        $trs = '';
        foreach ($taxes['iva'] as $row) {
            $trs .= '<tr>'
                . '<td class="nowrap" align="left">' . Impuestos::get($row['codimpuesto'])->descripcion . '</td>'
                . '<td class="nowrap" align="center">' . Tools::money($row['neto'], $model->coddivisa) . '</td>'
                . '<td class="nowrap" align="center">' . Tools::number($row['iva']) . '%</td>'
                . '<td class="nowrap" align="right">' . Tools::money($row['totaliva'], $model->coddivisa) . '</td>';

            if (empty($model->totalrecargo)) {
                $trs .= '</tr>';
                continue;
            }

            $trs .= '<td class="nowrap" align="center">' . (empty($row['recargo']) ? '-' : Tools::number($row['recargo']) . '%') . '</td>'
                . '<td class="nowrap" align="right">' . (empty($row['totalrecargo']) ? '-' : Tools::money($row['totalrecargo'])) . '</td>'
                . '</tr>';
        }

        if (empty($model->totalrecargo)) {
            return '<table class="' . $class . '">'
                . '<thead>'
                . '<tr>'
                . '<th align="left">' . $i18n->trans('tax') . '</th>'
                . '<th align="center">' . $i18n->trans('tax-base') . '</th>'
                . '<th align="center">' . $i18n->trans('percentage') . '</th>'
                . '<th align="right">Cuota de IVA</th>'
                . '</tr>'
                . '</thead>'
                . $trs
                . '</table>';
        }

        return '<table class="' . $class . '">'
            . '<tr>'
            . '<th align="left">' . $i18n->trans('tax') . '</th>'
            . '<th align="center">' . $i18n->trans('tax-base') . '</th>'
            . '<th align="center">' . $i18n->trans('tax') . '</th>'
            . '<th align="center">' . $i18n->trans('amount') . '</th>'
            . '<th align="center">' . $i18n->trans('re') . '</th>'
            . '<th align="right">' . $i18n->trans('amount') . '</th>'
            . '</tr>'
            . $trs
            . '</table>';
    }

    protected function getIrpfs($model, array $lines): array
    {
        $irpfs = [];

        foreach ($lines as $line) {
            $pvpTotal = $line->pvptotal * (100 - $model->dtopor1) / 100 * (100 - $model->dtopor2) / 100;
            if (empty($pvpTotal) || empty($line->irpf) || $line->suplido) {
                continue;
            }

            if (false === isset($irpfs[$line->irpf])) {
                $irpfs[$line->irpf]['name'] = $this->getIrpfName($line->irpf);
                $irpfs[$line->irpf]['total'] = 0;
            }

            $irpfs[$line->irpf]['total'] += $pvpTotal * $line->irpf / 100;
        }

        return $irpfs;
    }

    protected function getIrpfName(float $percentage): string
    {
        $irpfModel = new Retencion();
        foreach ($irpfModel->all([], ['porcentaje' => 'ASC'], 0, 0) as $irpf) {
            if ($irpf->porcentaje == $percentage) {
                return $irpf->descripcion;
            }
        }
        return Tools::lang()->trans('retention') . ' ' . $percentage . '%';
    }

    protected function getObservations(BusinessDocument $model): string
    {
        $return = $this->pipe('getObservations', $model);
        if (null !== $return) {
            $model->observaciones = $return;
        }

        return $this->get('hideobservations') ? '' : nl2br($model->observaciones ?? '');
    }

    protected function getPhones(?string $phone1 = '', ?string $phone2 = ''): string
    {
        $phone1 = str_replace(' ', '', $phone1);
        $phone2 = str_replace(' ', '', $phone2);

        if (empty($phone1) && empty($phone2)) {
            return '';
        } elseif (false === empty($phone1) && empty($phone2)) {
            return Tools::lang()->trans('phone') . ': ' . $phone1;
        } elseif (false === empty($phone2) && empty($phone1)) {
            return Tools::lang()->trans('phone') . ': ' . $phone2;
        }

        return Tools::lang()->trans('phones') . ': ' . $phone1 . ' - ' . $phone2;
    }

    protected function getRectifyingInvoice(): ?BusinessDocument
    {
        if (false === isset($this->headerModel->idfacturarect)
            || empty($this->headerModel->idfacturarect)) {
            return null;
        }

        $rectifyingInvoice = $this->headerModel->parentDocuments();
        if (empty($rectifyingInvoice)) {
            return null;
        }

        if ($this->headerModel->idfacturarect !== $rectifyingInvoice[0]->idfactura) {
            return null;
        }

        return $rectifyingInvoice[0];
    }

    protected function getSubjectIdFiscalStr(BusinessDocument $model): string
    {
        return empty($model->cifnif) ? '' : $model->getSubject()->tipoidfiscal . ': ' . $model->cifnif;
    }

    protected function getSubjectName(BusinessDocument $model): string
    {
        return $model->nombrecliente ?? $model->nombre ?? '';
    }

    protected function getSubjectTitle(BusinessDocument $model): string
    {
        return isset($model->nombrecliente) ?
            Tools::lang()->trans('customer') :
            Tools::lang()->trans('supplier');
    }

    protected function getTaxesRows(BusinessDocument $model, array $lines): array
    {
        return Calculator::getSubtotals($model, $lines);
    }

    protected function getTotalsModel(BusinessDocument &$model, array $lines): void
    {
        Calculator::calculate($model, $lines, false);
    }

    protected function header(): string
    {
        switch ($this->get('logoalign')) {
            case 'center':
                return $this->headerCenter();

            case 'full-size':
                return $this->headerFull();

            case 'right':
                return $this->headerRight();
        }

        // logo align left
        return $this->headerLeft();
    }

    protected function headerCenter(): string
    {
        $contactData = [];
        foreach (['web', 'email', 'telefono1', 'telefono2'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $title = $this->showHeaderTitle ? '<h1 class="mb-0 title text-center no-border">' . $this->get('headertitle') . '</h1>' : '';
        if ($this->isSketchInvoice()) {
            $title .= '<div class="color-red font-big font-bold text-center">' . Tools::lang()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big">'
            . '<tr>'
            . '<td valign="top" width="35%">'
            . '<p><b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . '</p>'
            . '</td>'
            . '<td align="center" valign="top">'
            . '<img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
            . '</td>'
            . '<td align="right" valign="top" width="35%">'
            . '<p>' . implode('<br/>', $contactData) . '</p>'
            . '</td>'
            . '</tr>'
            . '</table>' . $title;
    }

    protected function headerFull(): string
    {
        $html = '<div class="text-center">'
            . '<img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>';

        if ($this->isSketchInvoice()) {
            $html .= '<div class="mt-5 color-red font-big font-bold">' . Tools::lang()->trans('invoice-is-sketch') . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    protected function headerLeft(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $title = $this->showHeaderTitle ? '<h1 class="title">' . $this->get('headertitle') . '</h1>' . $this->spacer() : '';
        if ($this->isSketchInvoice()) {
            $title .= '<div class="color-red font-big font-bold">' . Tools::lang()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big">'
            . '<tr>'
            . '<td valign="top"><img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>' . '</td>'
            . '<td align="right" valign="top">' . $title
            . '<p><b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . '</p>' . $this->spacer()
            . '<p>' . implode(' · ', $contactData) . '</p>'
            . '</td>'
            . '</tr>'
            . '</table>';
    }

    protected function headerRight(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $title = $this->showHeaderTitle ? '<h1 class="title">' . $this->get('headertitle') . '</h1>' . $this->spacer() : '';
        if ($this->isSketchInvoice()) {
            $title .= '<div class="color-red font-big font-bold">' . Tools::lang()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big">'
            . '<tr>'
            . '<td>' . $title
            . '<p><b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . '</p>' . $this->spacer()
            . '<p>' . implode(' · ', $contactData) . '</p>'
            . '</td>'
            . '<td align="right"><img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/></td>'
            . '</tr>'
            . '</table>';
    }

    protected function isSketchInvoice(): bool
    {
        if ($this->get('showinvoicesketch') && $this->headerModel && $this->headerModel->editable &&
            in_array($this->headerModel->modelClassName(), ['FacturaCliente', 'FacturaProveedor'])) {
            return true;
        }

        return false;
    }

    protected function regenerateRowTable(array $row, array $titles): array
    {
        $data = [];
        foreach ($titles as $key => $value) {
            $data[$key] = $row[$key] ?? '';
        }
        return $data;
    }

    protected function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    protected function setLogoDefault(): void
    {
        $this->logoPath = file_exists(self::MEGACITY20_LOGO) ? self::MEGACITY20_LOGO : self::DEFAULT_LOGO;
        $this->setImage('logoPath', $this->get('idlogo'));
    }

    protected function spacer(int $num = 1): string
    {
        $html = '';
        while ($num > 0) {
            $html .= '<div class="spacer">&nbsp;</div>';
            $num--;
        }

        return $html;
    }

    /**
     * @return ToolBox
     * @deprecated since 2024
     */
    protected function toolBox(): ToolBox
    {
        return new ToolBox();
    }
}
