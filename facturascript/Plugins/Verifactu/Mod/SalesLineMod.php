<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Mod;

use FacturaScripts\Core\Base\Contract\SalesLineModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SalesLineMod implements SalesLineModInterface
{
    public function apply(SalesDocument &$model, array &$lines, array $formData): void {}

    public function applyToLine(array $formData, SalesDocumentLine &$line, string $id): void
    {
        $line->vf_send = (bool)($formData['vf_send_' . $id] ?? '0');
    }

    public function assets(): void {}

    public function getFastLine(SalesDocument $model, array $formData): ?SalesDocumentLine
    {
        return null;
    }

    public function map(array $lines, SalesDocument $model): array
    {
        return [];
    }

    public function newFields(): array
    {
        return [];
    }

    public function newModalFields(): array
    {
        return ['vf_send'];
    }

    public function newTitles(): array
    {
        return [];
    }

    public function renderField(Translator $i18n, string $idlinea, SalesDocumentLine $line, SalesDocument $model, string $field): ?string
    {
        if ($field === 'vf_send') {
            return $this->vfSendModal($idlinea, $line, $model);
        }

        return null;
    }

    public function renderTitle(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        return null;
    }

    protected function vfSendModal(string $idlinea, SalesDocumentLine $line, SalesDocument $model): string
    {
        if (!$model->getCompany()->verifactuIsConfigured(false)) {
            return '';
        }

        $attributes = $model->editable ?
            'name="vf_send_' . $idlinea . '"' :
            'disabled=""';

        $options = '<option value="1" ' . ($line->vf_send ? 'selected' : '') . '>' . Tools::lang()->trans('send') . '</option>';
        $options .= '<option value="0" ' . ($line->vf_send === false ? 'selected' : '') . '>' . Tools::lang()->trans('not-send') . '</option>';

        return '<div class="col-6">'
            . '<div class="mb-2">' . Tools::lang()->trans('send-verifactu')
            . '<select ' . $attributes . ' class="form-select">'
            . $options
            . '</select>'
            . '<small class="text-secondary">' . Tools::lang()->trans('send-verifactu-desc') . '</small>'
            . '</div>'
            . '</div>';
    }
}
