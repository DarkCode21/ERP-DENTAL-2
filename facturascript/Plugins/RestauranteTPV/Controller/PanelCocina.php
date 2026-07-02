<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestComanda;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestComandaLinea;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestEstacion;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestMesa;

/**
 * Panel de cocina: muestra las líneas pendientes de todas las comandas abiertas,
 * agrupadas por mesa. El cocinero puede marcar líneas como preparadas o servidas.
 */
class PanelCocina extends Controller
{
    /**
     * Array de grupos para mostrar en el panel.
     * Cada elemento:
     *   ['mesa' => RestMesa, 'comanda' => RestComanda, 'lineas' => RestComandaLinea[], 'elapsedMin' => int]
     *
     * @var array
     */
    public $grupos = [];

    /** @var int Segundos entre recargas automáticas (0 = desactivado) */
    public $autoRefresh = 30;

    /** @var RestEstacion|null Estación activa para este panel */
    public $estacion = null;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'kitchen-panel';
        $data['icon']       = 'fa-solid fa-fire-burner';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        // Cargar estación (puede venir como GET /?idestacion=X o como campo POST oculto)
        $idestacion = (int)$this->request->get('idestacion', 0);
        if ($idestacion <= 0) {
            $idestacion = (int)$this->request->request->get('idestacion', 0);
        }
        if ($idestacion > 0) {
            $this->estacion = new RestEstacion();
            if (false === $this->estacion->loadFromCode($idestacion)) {
                $this->estacion = null;
            }
        }

        $action = $this->request->request->get('action', '');
        switch ($action) {
            case 'mark-prepared':
                $this->actionMarkPrepared();
                break;
            case 'mark-all-prepared':
                $this->actionMarkAllPreparedForComanda();
                break;
            case 'mark-served':
                $this->actionMarkServed();
                break;
            case 'mark-all-served':
                $this->actionMarkAllServedForComanda();
                break;
            case 'autoserv':
                $db = new DataBase();
                $db->exec('UPDATE rest_comandas_lineas SET estado = ' . $db->var2str(RestComandaLinea::ESTADO_SERVIDO)
                    . ' WHERE estado = ' . $db->var2str(RestComandaLinea::ESTADO_PREPARADO));
                header('Content-Type: application/json');
                echo '{"ok":true}';
                exit;
        }

        if ($action !== '' && $idestacion > 0) {
            $this->redirect($this->url() . '?idestacion=' . $idestacion);
            return;
        }

        $this->loadGrupos();
    }

    /**
     * Cocina marca una línea como preparada (lista para servir al cliente).
     */
    protected function actionMarkPrepared(): void
    {
        $idlinea = (int)$this->request->request->get('idlinea', 0);
        $linea = new RestComandaLinea();
        if ($linea->loadFromCode($idlinea) && $linea->estado === RestComandaLinea::ESTADO_PENDIENTE) {
            $linea->estado = RestComandaLinea::ESTADO_PREPARADO;
            $linea->save();
            // Cascada: sub-líneas (modificadores) también
            $db = new DataBase();
            $db->exec('UPDATE rest_comandas_lineas SET estado = ' . $db->var2str(RestComandaLinea::ESTADO_PREPARADO)
                . ' WHERE idlinea_padre = ' . $db->var2str($idlinea)
                . ' AND estado = ' . $db->var2str(RestComandaLinea::ESTADO_PENDIENTE));
        }
    }

    /**
     * Cocina marca todas las líneas de una comanda como preparadas (listas para servir).
     */
    protected function actionMarkAllPreparedForComanda(): void
    {
        $idcomanda = (int)$this->request->request->get('idcomanda', 0);
        if ($idcomanda <= 0) {
            return;
        }

        $db = new DataBase();
        $sql = 'UPDATE rest_comandas_lineas'
            . ' SET estado = ' . $db->var2str(RestComandaLinea::ESTADO_PREPARADO)
            . ' WHERE idcomanda = ' . $db->var2str($idcomanda)
            . ' AND estado = ' . $db->var2str(RestComandaLinea::ESTADO_PENDIENTE);
        $db->exec($sql);
    }

    /**
     * Marca una línea preparada como servida (entregada a la mesa) — desaparece del panel.
     */
    protected function actionMarkServed(): void
    {
        $idlinea = (int)$this->request->request->get('idlinea', 0);
        $linea = new RestComandaLinea();
        if ($linea->loadFromCode($idlinea) && $linea->estado === RestComandaLinea::ESTADO_PREPARADO) {
            $linea->estado = RestComandaLinea::ESTADO_SERVIDO;
            $linea->save();
            $db = new DataBase();
            $db->exec('UPDATE rest_comandas_lineas SET estado = ' . $db->var2str(RestComandaLinea::ESTADO_SERVIDO)
                . ' WHERE idlinea_padre = ' . $db->var2str($idlinea)
                . ' AND estado = ' . $db->var2str(RestComandaLinea::ESTADO_PREPARADO));
        }
    }

    /**
     * Marca todas las líneas preparadas de una comanda como servidas (desaparecen del panel).
     */
    protected function actionMarkAllServedForComanda(): void
    {
        $idcomanda = (int)$this->request->request->get('idcomanda', 0);
        if ($idcomanda <= 0) {
            return;
        }
        $db = new DataBase();
        $db->exec('UPDATE rest_comandas_lineas'
            . ' SET estado = ' . $db->var2str(RestComandaLinea::ESTADO_SERVIDO)
            . ' WHERE idcomanda = ' . $db->var2str($idcomanda)
            . ' AND estado = ' . $db->var2str(RestComandaLinea::ESTADO_PREPARADO));
    }

    /**
     * Carga los grupos de comandas con líneas pendientes de preparar.
     * La cocina ve líneas en estado 'pendiente'. Cuando las marca listas pasan a 'preparado'.
     * Ordenadas de más antigua a más nueva.
     */
    protected function loadGrupos(): void
    {
        $this->grupos = [];
        $db = new DataBase();

        // Obtener familias de la estación (si hay una activa)
        $familias = [];
        $catFilter = '';
        if ($this->estacion) {
            $familias = $this->estacion->getFamilias();
            // Estación configurada pero sin familias → no mostrar nada
            if (empty($familias)) {
                return;
            }
        }

        // Filtro de categorías por JOIN con variantes/productos
        $joinFamilias = ' LEFT JOIN variantes v ON v.referencia = l.referencia'
            . ' LEFT JOIN productos p ON p.idproducto = v.idproducto';

        if (!empty($familias)) {
            $catList = implode(', ', array_map([$db, 'var2str'], $familias));
            $catFilter = ' AND (p.codfamilia IN (' . $catList . '))';
        }

        // Comandas con líneas PENDIENTES o PREPARADAS (por servir)
        $sql = 'SELECT DISTINCT c.idcomanda'
            . ' FROM rest_comandas c'
            . ' INNER JOIN rest_comandas_lineas l ON l.idcomanda = c.idcomanda'
            . $joinFamilias
            . ' WHERE c.estado IN (' . $db->var2str(RestComanda::ESTADO_ABIERTA) . ', ' . $db->var2str(RestComanda::ESTADO_EN_PROCESO) . ')'
            . ' AND l.estado IN (' . $db->var2str(RestComandaLinea::ESTADO_PENDIENTE) . ', ' . $db->var2str(RestComandaLinea::ESTADO_PREPARADO) . ')'
            . ' AND l.idlinea_padre IS NULL'
            . ' AND l.enviado = 1'
            . $catFilter
            . ' ORDER BY c.fecha ASC, c.hora ASC';

        $rows = $db->select($sql);
        if (empty($rows)) {
            return;
        }

        $now = time();

        foreach ($rows as $row) {
            $comanda = new RestComanda();
            if (false === $comanda->loadFromCode($row['idcomanda'])) {
                continue;
            }

            // Minutos transcurridos desde que se creó la comanda
            $ts = strtotime($comanda->fecha . ' ' . $comanda->hora);
            $elapsedMin = ($ts > 0) ? max(0, (int)floor(($now - $ts) / 60)) : 0;

            $mesa = null;
            if ($comanda->idmesa) {
                $mesa = new RestMesa();
                if (false === $mesa->loadFromCode($comanda->idmesa)) {
                    $mesa = null;
                }
            }

            // Cargar líneas PENDIENTES y PREPARADAS
            if (!empty($familias)) {
                $catList = implode(', ', array_map([$db, 'var2str'], $familias));
                $sqlLineas = 'SELECT l.* FROM rest_comandas_lineas l'
                    . ' LEFT JOIN variantes v ON v.referencia = l.referencia'
                    . ' LEFT JOIN productos p ON p.idproducto = v.idproducto'
                    . ' WHERE l.idcomanda = ' . $db->var2str($comanda->idcomanda)
                    . ' AND l.estado IN (' . $db->var2str(RestComandaLinea::ESTADO_PENDIENTE) . ', ' . $db->var2str(RestComandaLinea::ESTADO_PREPARADO) . ')'
                    . ' AND (l.idlinea_padre IS NOT NULL OR p.codfamilia IN (' . $catList . '))';
                $lineasRaw = $db->select($sqlLineas);
            } else {
                $sqlLineas = 'SELECT * FROM rest_comandas_lineas'
                    . ' WHERE idcomanda = ' . $db->var2str($comanda->idcomanda)
                    . ' AND estado IN (' . $db->var2str(RestComandaLinea::ESTADO_PENDIENTE) . ', ' . $db->var2str(RestComandaLinea::ESTADO_PREPARADO) . ')';
                $lineasRaw = $db->select($sqlLineas);
            }

            if (empty($lineasRaw)) {
                continue;
            }

            // Convertir a objetos RestComandaLinea
            $lineas = [];
            foreach ($lineasRaw as $lr) {
                $l = new RestComandaLinea();
                $l->loadFromData($lr);
                $lineas[] = $l;
            }

            // Ordenar: cada línea padre seguida de sus modificadores (hijas)
            $subsByParent = [];
            $parentLines  = [];
            foreach ($lineas as $l) {
                if ($l->idlinea_padre) {
                    $subsByParent[$l->idlinea_padre][] = $l;
                } else {
                    $parentLines[] = $l;
                }
            }
            $ordered = [];
            foreach ($parentLines as $l) {
                $ordered[] = $l;
                if (isset($subsByParent[$l->idlinea])) {
                    foreach ($subsByParent[$l->idlinea] as $s) {
                        $ordered[] = $s;
                    }
                }
            }

            if (empty($parentLines)) {
                continue;
            }

            $this->grupos[] = [
                'mesa'       => $mesa,
                'comanda'    => $comanda,
                'lineas'     => $ordered,
                'elapsedMin' => $elapsedMin,
            ];
        }
    }
}
