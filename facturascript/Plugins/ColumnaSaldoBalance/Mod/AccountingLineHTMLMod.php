<?php
namespace FacturaScripts\Plugins\ColumnaSaldoBalance\Mod;
use FacturaScripts\Core\Base\Contract\SalesLineModInterface;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;

class AccountingLineHTMLMod implements SalesLineModInterface 
{
    public function apply(SalesDocument &$model, array &$lines, array $formData) {
	}

    public function applyToLine(array $formData, SalesDocumentLine &$line, string $id) {
	}

    public function assets(): void {
	}

    public function map(array $lines, SalesDocument $model): array {	
		return [];
	}

    public function newModalFields(): array {
		return [];	
	}

    public function newFields(): array {
		return [];
	}

    public function newTitles(): array {
		return ["saldo"];	
	}

    public function renderField(Translator $i18n, string $idlinea, SalesDocumentLine $line, SalesDocument $model, string $field): ?string {
		return null;
	}

    public function renderTitle(Translator $i18n, SalesDocument $model, string $field): ?string {
        if ($field === 'saldo') {
            return $this->saldoTitle($i18n);
        }
        return null;
	}
	
	protected function saldoTitle($i18n): string
    {
        return '<th class="text-right order-3">' . $i18n->trans('balance') . '</th>';
    }

}