<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

/**
 * Modelo para almacenar logs de errores de Verifactu
 *
 * @author Generated automatically
 */
class VerifactuErrorLog extends ModelClass
{
    use ModelTrait;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $idempresa;

    /**
     * @var string
     */
    public $job_name;

    /**
     * @var string
     */
    public $error_type;

    /**
     * @var string
     */
    public $error_message;

    /**
     * @var string
     */
    public $error_details;

    /**
     * @var string
     */
    public $factura_id;

    /**
     * @var string
     */
    public $json_file;

    /**
     * @var string
     */
    public $fecha;

    /**
     * @var string
     */
    public $hora;

    /**
     * @var bool
     */
    public $resolved;

    public function clear()
    {
        parent::clear();
        $this->fecha = date('Y-m-d');
        $this->hora = date('H:i:s');
        $this->resolved = false;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'verifactu_error_logs';
    }

    /**
     * Método estático para registrar un error
     *
     * @param int $idempresa
     * @param string $jobName
     * @param string $errorType
     * @param string $errorMessage
     * @param array $details
     * @return bool
     */
    public static function logError(int $idempresa, string $jobName, string $errorType, string $errorMessage, array $details = []): bool
    {
        $errorLog = new self();
        $errorLog->idempresa = $idempresa;
        $errorLog->job_name = $jobName;
        $errorLog->error_type = $errorType;
        $errorLog->error_message = Tools::textBreak($errorMessage, 500);
        $errorLog->error_details = json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Extraer información adicional de los detalles
        if (isset($details['factura_id'])) {
            $errorLog->factura_id = $details['factura_id'];
        }
        if (isset($details['json_file'])) {
            $errorLog->json_file = $details['json_file'];
        }

        return $errorLog->save();
    }

    /**
     * Obtener errores recientes para una empresa
     *
     * @param int $idempresa
     * @param int $days
     * @return array
     */
    public static function getRecentErrors(int $idempresa, int $days = 7): array
    {
        $errorLog = new self();
        $where = [
            new DataBaseWhere('idempresa', $idempresa),
            new DataBaseWhere('fecha', date('Y-m-d', strtotime("-{$days} days")), '>=')
        ];
        
        return $errorLog->all($where, ['fecha' => 'DESC', 'hora' => 'DESC']);
    }

    /**
     * Marcar error como resuelto
     *
     * @return bool
     */
    public function markAsResolved(): bool
    {
        $this->resolved = true;
        return $this->save();
    }

    /**
     * Exportar errores a JSON
     *
     * @param int $idempresa
     * @param int $days
     * @return string
     */
    public static function exportToJson(int $idempresa, int $days = 7): string
    {
        $errors = self::getRecentErrors($idempresa, $days);
        $export = [
            'generated_at' => date('Y-m-d H:i:s'),
            'empresa_id' => $idempresa,
            'period_days' => $days,
            'total_errors' => count($errors),
            'errors' => []
        ];

        foreach ($errors as $error) {
            $export['errors'][] = [
                'id' => $error->id,
                'timestamp' => $error->fecha . ' ' . $error->hora,
                'job_name' => $error->job_name,
                'error_type' => $error->error_type,
                'error_message' => $error->error_message,
                'factura_id' => $error->factura_id,
                'json_file' => $error->json_file,
                'resolved' => $error->resolved,
                'details' => json_decode($error->error_details, true)
            ];
        }

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}