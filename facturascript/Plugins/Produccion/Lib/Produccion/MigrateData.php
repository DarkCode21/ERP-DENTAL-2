<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Lib\Produccion;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Receta;
use FacturaScripts\Dinamic\Model\LineaReceta;

/**
 * Manages the transfer of data from version FS2017 to the new version.
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class MigrateData
{
    /**
     * Link to active database
     *
     * @var DataBase
     */
    private DataBase $database;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->database = new DataBase();
    }

    /**
     * Remove old version tables
     */
    public function deleteOldTables(): void
    {
        $this->database->exec('DROP TABLE lineasrecetas;');
        $this->database->exec('DROP TABLE recetas;');
    }

    /**
     * Performs the transfer of data between the old tables
     * and those of the new version.
     *
     * @return bool
     */
    public function run(): bool
    {
        if (false === $this->needTransferData()) {
            return false;
        }

        $this->database->beginTransaction();
        try {
            $sql = 'SELECT * FROM recetas'
                . ' WHERE referencia IS NOT NULL'
                . ' AND referencia IN (SELECT referencia FROM variantes)';
            foreach ($this->database->select($sql) as $row) {
                if (false === $this->processRecipe($row)) {
                    return false;
                }
            }

            $this->database->commit();
            return true;
        } catch (Exception $exc) {
            $this->database->rollback();
            Tools::log()->error($exc->getMessage());
        }
        return false;
    }

    /**
     *
     * @return bool
     */
    private function needTransferData(): bool
    {
        if (false === $this->database->tableExists('recetas')) {
            return false;
        }

        $recipe = new Receta();
        return ($recipe->count() == 0);
    }

    /**
     *
     * @param array $data
     * @return bool
     * @throws Exception
     */
    private function processRecipe(array $data): bool
    {
        $recipe = new Receta($data);
        $recipe->ultimaproduccion = $data['ultima_produccion'];
        if (false === $recipe->save()) {
            Tools::log()->error('recipe-save-error', ['%recipe%' => $data['codreceta']]);
            return false;
        }

        $sql = "SELECT * FROM lineasrecetas WHERE codreceta = '" . $recipe->codreceta
            . "' AND referencia IN (SELECT referencia FROM variantes);";

        foreach ($this->database->select($sql) as $row) {
            $recipeLine = new LineaReceta($row);
            $recipeLine->idreceta = $recipe->idreceta;
            $recipeLine->idlinea = null;
            if (false === $recipeLine->save()) {
                Tools::log()->error(
                    'recipeline-save-error',
                    ['%recipe%' => $data['codreceta'], '%line%' => $data['idlinea']]
                );
                return false;
            }
        }
        return true;
    }
}
