<?php

namespace FacturaScripts\Plugins\FechaEntregaDocumentos\Mod;

use FacturaScripts\Core\Base\Contract\SalesModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\User;

class SalesHeaderHTMLMod implements SalesModInterface
{

    public function apply(SalesDocument &$model, array $formData, User $user)
    {
    }

	public function applyBefore(SalesDocument &$model, array $formData, User $user)
    {
	   if (isset($formData['fechaentrega'])) {
	   	  $model->fechaentrega = $formData['fechaentrega'] != ""? $formData['fechaentrega']: null;
       }
    }

    public function assets(): void
    {
        // TODO: Implement assets() method.
    }

    public function newFields(): array
    {
        // TODO: Implement newFields() method.
        return ['fechaentrega'];
    }

    public function renderField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        // TODO: Implement renderField() method.
        if ($field == 'fechaentrega') {
            return self::fechaEntrega($i18n, $model);
        }
        return null;
    }

    public function newBtnFields(): array
    {
        // No button fields by default
        return [];
    }

    public function newModalFields(): array
    {
        // No modal fields by default
        return [];
    }

    private static function fechaEntrega($i18n, $model): string
    {
		#return "<div>Hola Mundo...</div>";
        $attributes = $model->editable?'name="fechaentrega" required=""':'disabled=""';

        $fecha = null;
    	if (property_exists($model, 'fechaentrega')) {
        	$fecha = is_null($model->fechaentrega) ? null : date('Y-m-d', strtotime($model->fechaentrega));    
        }
    	return '<div class="col-sm-6 mt-3">'
            . '<div class="form-group">' . $i18n->trans('delivery')
            . '<input type="date" ' . $attributes . ' value="' . $fecha . '" class="form-control"/>'
			. '</div>'
			. '</div>';
    }
}