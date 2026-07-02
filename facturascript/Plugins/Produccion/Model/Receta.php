<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Model;

use Exception;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Join\LineaReceta;
use FacturaScripts\Dinamic\Model\RecetaProducto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Class that manages the data model of the product that is manufactured
 * based on a raw material.
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class Receta extends ModelClass
{
    use ModelTrait;

    /**
     * Quantity of the manufactured article that is produced
     * with the raw material.
     *
     * @var float
     * @deprecated since 1.52 (removed in 1.53)
     */
    public $cantidad;

    /**
     * Link with the warehouse where the raw material is taken.
     *
     * @var string
     */
    public $codalmacen;

    /**
     * Link with the warehouse where the manufactured product
     * will be stored.
     *
     * @var string
     */
    public $codalmacen2;

    /**
     * Added for compatibility. (deprecated)
     *
     * @var string
     */
    public $codreceta;

    /**
     * Cost of the manufactured product.
     *
     * @var float
     */
    public $coste;

    /**
     * Human description of the recipe.
     *
     * @var string
     */
    public $descripcion;

    /**
     * Primary Key.
     *
     * @var int
     */
    public $idreceta;

    /**
     * Remarks on the recipe.
     *
     * @var string
     */
    public $observaciones;

    /**
     * @var string
     * @deprecated since 1.52 (removed in 1.53)
     */
    public $referencia;

    /**
     * Date and Time of the last manufacture.
     *
     * @var string
     */
    public $ultimaproduccion;

    /**
     * Reset the values of all model properties.
     */
    public function clear(): void
    {
        parent::clear();
        $this->codalmacen = Tools::settings('default', 'codalmacen');
        $this->codalmacen2 = Tools::settings('default', 'codalmacen');
        $this->coste = 0.00;
    }

    /**
     * Obtains all the lines that make up the recipe
     * with all the complementary data: Variant, Product and Stock.
     *
     * @return LineaReceta[]
     */
    public function getLines(): array
    {
        return (new LineaReceta())->all(
            [Where::eq('lineasrecetas.idreceta', $this->idreceta)],
            ['lineasrecetas.idlinea' => 'ASC']
        );
    }

    /**
     * Get all additional products that are produced when crafting the recipe.
     *
     * @return RecetaProducto[]
     */
    public function getProducts(): array
    {
        return RecetaProducto::all(
            [Where::eq('idreceta', $this->idreceta)],
            ['id' => 'ASC']
        );
    }

    /**
     * Gets the products that share the cost of the recipe.
     *
     * @return RecetaProducto[]
     */
    public function getProductShareCost(): array
    {
        $where = [
            Where::eq('idreceta', $this->idreceta),
            Where::eq('repartircoste', RecetaProducto::SHARE_COST_YES),
        ];
        return RecetaProducto::all($where);
    }

    /**
     * Returns the variant of the reference passed as a parameter.
     * This method is only for compatibility with previous versions.
     *
     * @param string $reference
     * @return Variante
     */
    public function getVariant(string $reference): Variante
    {
        $variant = new Variante();
        $variant->loadWhereEq('referencia', $reference);
        return $variant;
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'idreceta';
    }

    /**
     * Stores the model data in the database.
     *
     * @return bool
     */
    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }
        return true;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'produccion_recetas';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     * @throws Exception
     */
    public function test(): bool
    {
        $this->descripcion = Tools::noHtml($this->descripcion);
        $this->observaciones = Tools::noHtml($this->observaciones);
        $this->coste = $this->getTotalCost();
        if ($this->checkForDuplicateCode()) {
            Tools::log()->warning('duplicate-recipe-code', ['%code%' => $this->codreceta]);
            return false;
        }
        return parent::test();
    }

    /**
     * Checks if there is another recipe with the same code.
     *
     * @return bool
     */
    private function checkForDuplicateCode(): bool
    {
        $canDuplicate = (int)Tools::settings('production', 'duplicatecode', 0) ?? 0;
        if ($canDuplicate === 0) {
            return false;
        }

        $where = [
            Where::notEq('idreceta', $this->idreceta),
            Where::eq('codreceta', $this->codreceta),
        ];
        $recipe = new Receta();
        return $recipe->loadWhere($where);
    }

    /**
     * Returns the total cost of the recipe.
     *
     * @return float
     */
    private function getTotalCost(): float
    {
        $result = 0.00;
        foreach ($this->getLines() as $line) {
            $result += $line->getCost();
        }
        return $result;
    }
}
