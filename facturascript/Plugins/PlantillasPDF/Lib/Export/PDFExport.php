<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\Export;

use FacturaScripts\Core\Lib\Export\ExportBase;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FormatoDocumento;
use FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Template1;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of PDFExport
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PDFExport extends ExportBase
{
    const LIST_LIMIT = 500;

    /** @var FormatoDocumento */
    protected $format;

    /** @var bool */
    protected $newPage = false;

    /** @var Template1 */
    protected $template;

    public function __construct()
    {
        $this->format = new FormatoDocumento();
    }

    /**
     * @param BusinessDocument $model
     *
     * @return bool
     */
    public function addBusinessDocPage($model): bool
    {
        $this->template->setEmpresa($model->idempresa);

        if (false === $this->format->exists()) {
            $format = $this->getDocumentFormat($model);
            $this->template->setFormat($format);
        }

        // RELOAD model from database to prevent caching issues
        $modelClass = get_class($model);
        $freshModel = new $modelClass();
        $freshModel->loadFromCode($model->primaryColumnValue());
        
        $code = $this->template->format->primarynumero2 && isset($freshModel->numero2) ? $freshModel->numero2 : $freshModel->primaryDescription();
        $modelTitle = Tools::lang()->trans($freshModel->modelClassName() . '-min') . ' ' . $code;
        $title = empty($this->template->format->titulo) ? $modelTitle : $this->template->format->titulo . ' ' . $code;
        
        // ALWAYS update title and headerModel BEFORE any rendering
        $this->template->setTitle($title, true);
        $this->template->setHeaderTitle($title, true);
        $this->template->headerModel = $freshModel;
        $this->template->isBusinessDoc = true;

        $this->template->initMpdf();
        $this->template->initHtml();

        if ($this->newPage) {
            // Force header update BEFORE AddPage by writing empty HTML
            $this->template->writeHTML('');
			$this->template->refreshHeader();
            $this->template->mpdf->AddPage('', '', 1);
            $this->newPage = false;
        }

        $this->template->addInvoiceHeader($freshModel);
        $this->template->addInvoiceLines($freshModel);
        $this->template->addInvoiceFooter($freshModel);
        $this->newPage = true;

        // do not continue with export
        return false;
    }

    /**
     * @param ModelClass $model
     * @param array $where
     * @param array $order
     * @param int $offset
     * @param array $columns
     * @param string $title
     *
     * @return bool
     * @throws MpdfException
     */
    public function addListModelPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
        $this->setFileName($title);
        $this->template->setHeaderTitle($title);

        $css = $this->getColumnCss($columns);
        $alignments = $this->getColumnAlignments($columns);
        $titles = $this->getColumnTitles($columns);

        if (count($titles) > 5) {
            $this->setOrientation('landscape');
        }

        $this->template->initMpdf();
        $this->template->initHtml();

        $cursor = $model->all($where, $order, $offset, self::LIST_LIMIT);
        if (empty($cursor)) {
            $this->template->addTable([], $titles, $alignments, $css);
        }
        while (!empty($cursor)) {
            $rows = $this->getCursorData($cursor, $columns);
            $this->template->addTable($rows, $titles, $alignments, $css);

            // Advance within the results
            $offset += self::LIST_LIMIT;
            $cursor = $model->all($where, $order, $offset, self::LIST_LIMIT);
        }

        return true;
    }

    /**
     * @param ModelClass $model
     * @param array $columns
     * @param string $title
     *
     * @return bool
     * @throws MpdfException
     */
    public function addModelPage($model, $columns, $title = ''): bool
    {
        $this->setFileName($title);
        if (isset($model->idempresa)) {
            $this->template->setEmpresa($model->idempresa);
        }
        $this->template->setHeaderTitle($title);

        $this->template->initMpdf();
        $this->template->initHtml();

        $data = $this->getModelColumnsData($model, $columns);
        $this->template->addDualColumnTable($data);
        return true;
    }

    /**
     * @param array $headers
     * @param array $rows
     * @param array $options
     * @param string $title
     *
     * @return bool
     * @throws MpdfException
     */
    public function addTablePage($headers, $rows, $options = [], $title = ''): bool
    {
        $this->template->initMpdf();
        $this->template->initHtml();

        $css = [];
        $alignments = [];
        foreach (array_keys($headers) as $key) {
            if (array_key_exists($key, $options)) {
                $css[$key] = $options[$key]['css'] ?? '';
                $alignments[$key] = $options[$key]['display'] ?? 'left';
                continue;
            }
            $css[$key] = in_array($key, ['debe', 'haber', 'saldo', 'saldoprev']) ? 'nowrap' : '';
            $alignments[$key] = in_array($key, ['debe', 'haber', 'saldo', 'saldoprev']) ? 'right' : 'left';
        }

        if (false === empty($title)) {
            $this->template->writeHTML('<h3 class="mb-0">' . $title . '</h3>');
        }

        // metemos cada 500 líneas en una tabla
        foreach (array_chunk($rows, self::LIST_LIMIT) as $lines) {
            $this->template->addTable($lines, $headers, $alignments, $css);
        }

        return true;
    }

    public function getOrientation()
    {
        return $this->template->mpdf->DefOrientation ?? 'portrait';
    }

    /**
     * @return string
     * @throws MpdfException
     */
    public function getDoc()
    {
        return $this->template->output($this->getFileName() . '.pdf');
    }

    public function newDoc(string $title, int $idformat, string $langcode)
    {
        if (!empty($langcode)) {
            Tools::lang()->setDefaultLang($langcode);
        }

        $this->setFileName($title);
        $this->setTemplate();
        $this->template->setTitle($title);
        $this->template->setHeaderTitle($title);
        
        if ($this->format->loadFromCode($idformat)) {
            $this->template->setFormat($this->format);
        }
    }

    public function newPage(string $orientation = '', bool $forceNewPage = false)
    {
        if (empty($orientation)) {
            $orientation = $this->getOrientation();
        }

        $this->setOrientation($orientation);

        if ($this->template->mpdf === null) {
            $this->template->initMpdf();
            $this->template->initHtml();
        } elseif ($forceNewPage || $this->template->mpdf->y < 200) {
            $this->template->mpdf->AddPage($orientation);
        } else {
            $this->template->mpdf->WriteHTML("\n");
        }
    }

    public function setOrientation(string $orientation)
    {
        $this->template->setOrientation($orientation);
    }

    /**
     * @param Response $response
     * @throws MpdfException
     */
    public function show(Response &$response)
    {
        $response->headers->set('Content-type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline;filename=' . $this->getFileName() . '.pdf');
        $response->setContent($this->getDoc());
    }

    protected function getColumnCss(array $columns): array
    {
        $css = [];
        foreach ($columns as $col) {
            if (is_numeric($col) || is_float($col)) {
                $css[$col] = 'nowrap';
                continue;
            }

            if (isset($col->widget) && $col->widget->getType() === 'number') {
                $css[$col->widget->fieldname] = 'nowrap';
            }
        }
        return $css;
    }

    protected function setTemplate()
    {
        $name = Tools::settings('plantillaspdf', 'template', 'template1');
        $className = '\\FacturaScripts\\Dinamic\\Lib\\PlantillasPDF\\' . $name;
        if (false === class_exists($className)) {
            $className = '\\FacturaScripts\\Dinamic\\Lib\\PlantillasPDF\\Template1';
        }
        $this->template = new $className();
    }
}
