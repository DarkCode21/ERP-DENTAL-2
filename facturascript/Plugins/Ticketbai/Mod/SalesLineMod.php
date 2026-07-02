<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Mod;

use FacturaScripts\Core\Base\Contract\SalesLineModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Plugins\Ticketbai\Lib\TbaiTools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SalesLineMod implements SalesLineModInterface
{

    public function apply(SalesDocument &$model, array &$lines, array $formData)
    {
    }

    public function applyToLine(array $formData, SalesDocumentLine &$line, string $id)
    {
        $line->tbai_send = (bool)($formData['tbai_send_' . $id] ?? '0');
        $line->tbai_idiae = $formData['tbai_idiae_' . $id] ?? null;
    }

    public function assets(): void
    {
    }

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
        return ['tbai_send', 'tbai_iae'];
    }

    public function newTitles(): array
    {
        return [];
    }

    public function renderField(Translator $i18n, string $idlinea, SalesDocumentLine $line, SalesDocument $model, string $field): ?string
    {
        switch ($field) {
            case 'tbai_iae':
                return $this->tbaiIaeModal($i18n, $idlinea, $line, $model);

            case 'tbai_send':
                return $this->tbaiSendModal($i18n, $idlinea, $line, $model);
        }

        return null;
    }

    public function renderTitle(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        return null;
    }

    protected function tbaiIaeModal(Translator $i18n, string $idlinea, SalesDocumentLine $line, SalesDocument $model): string
    {
        // si el modelo no es FacturaCliente, terminamos
        if ($model->modelClassName() !== 'FacturaCliente') {
            return '';
        }

        // obtenemos la empresa
        $company = $model->getCompany();

        // si la empresa no es una persona física, o no es del país vasco, terminamos
        if (false === $company->personafisica || false === TbaiTools::isBasqueCountryCompany($company)) {
            return '';
        }

        // comprobamos el territorio de la empresa, si no es Bizkaia, terminamos
        if (false === TbaiTools::isCompanyBizkaia($company)) {
            return '';
        }

        $options = '';
        $attributes = $model->editable ?
            'name="tbai_idiae_' . $idlinea . '"' :
            'disabled=""';

        $product = $line->getProducto();

        foreach ($company->getIAEs() as $companyIAE) {
            $iae = $companyIAE->getIae();
            $selected = $line->tbai_idiae === $companyIAE->idiae
            || empty($line->primaryColumnValue()) && $product->exists() && $product->tbai_idiae === $companyIAE->idiae
                ? 'selected' : '';

            $options .= '<option value="' . $companyIAE->idiae . '" ' . $selected . '>'
                . $companyIAE->idiae . ' | ' . $iae->descripcion . '</option>';
        }

        return '<div class="col-6">'
            . '<div class="mb-2">' . $i18n->trans('code-iae')
            . '<select ' . $attributes . ' class="form-control">'
            . $options
            . '</select>'
            . '</div>'
            . '</div>';
    }

    protected function tbaiSendModal(Translator $i18n, string $idlinea, SalesDocumentLine $line, SalesDocument $model): string
    {
        $attributes = $model->editable ?
            'name="tbai_send_' . $idlinea . '"' :
            'disabled=""';

        $options = '<option value="1" ' . ($line->tbai_send ? 'selected' : '') . '>' . $i18n->trans('send') . '</option>';
        $options .= '<option value="0" ' . ($line->tbai_send === false ? 'selected' : '') . '>' . $i18n->trans('not-send') . '</option>';

        return '<div class="col-6">'
            . '<div class="mb-2">' . $i18n->trans('send-tickbai')
            . '<select ' . $attributes . ' class="form-control">'
            . $options
            . '</select>'
            . '<small class="text-secondary">' . $i18n->trans('send-tickbai-desc') . '</small>'
            . '</div>'
            . '</div>';
    }
}
