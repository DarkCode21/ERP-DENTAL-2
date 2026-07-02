<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos    private function     private function exportSummary(): void
    {
        $idempresa = (int)filter_input(INPUT_GET, 'idempresa', FILTER_VALIDATE_INT) ?: 1;
        $days = (int)filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT) ?: 7;rtJson(): void
    {
        $idempresa = (int)filter_input(INPUT_GET, 'idempresa', FILTER_VALIDATE_INT) ?: 1;
        $days = (int)filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT) ?: 7;turascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use Symfony\Component\HttpFoundation\Response;
use FacturaScripts\Core\Model\User;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuErrorLog;

/**
 * Controlador para generar reportes de errores de Verifactu con tabs
 *
 * @author Generated automatically
 */
class ReportVerifactuErrors extends Controller
{
    /** @var array */
    public $errors = [];
    
    /** @var array */
    public $errorStats = [];
    
    /** @var array */
    public $companies = [];
    
    /** @var array */
    public $errorList = [];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'verifactu-error-report';
        $data['icon'] = 'fas fa-exclamation-triangle';
        return $data;
    }

    public function privateCore(&$response, $user, $controllerName)
    {
        parent::privateCore($response, $user, $controllerName);
        
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?: '';
        switch ($action) {
            case 'export-json':
                return $this->exportJson();
                
            case 'export-summary':
                return $this->exportSummary();
        }
        
        // Cargar datos para el dashboard
        $this->loadErrorData();
        $this->loadCompanies();
        $this->loadErrorList();
    }

    protected function exportJson(): bool
    {
        $idempresa = $this->request->get('idempresa', 1);
        $days = $this->request->get('days', 7);
        
        $json = VerifactuErrorLog::exportToJson($idempresa, $days);
        $filename = 'verifactu_errors_' . date('Y-m-d_H-i-s') . '.json';
        
        // Set headers for JSON download
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $json;
        exit;
    }

    protected function exportSummary(): bool
    {
        $idempresa = $this->request->get('idempresa', 1);
        $days = $this->request->get('days', 7);
        
        $errors = VerifactuErrorLog::getRecentErrors($idempresa, $days);
        
        // Crear un resumen de errores
        $summary = [
            'generated_at' => date('Y-m-d H:i:s'),
            'empresa_id' => $idempresa,
            'period_days' => $days,
            'total_errors' => count($errors),
            'errors_by_type' => [],
            'errors_by_job' => [],
            'unresolved_count' => 0,
            'recent_errors' => []
        ];
        
        foreach ($errors as $error) {
            // Count by type
            if (!isset($summary['errors_by_type'][$error->error_type])) {
                $summary['errors_by_type'][$error->error_type] = 0;
            }
            $summary['errors_by_type'][$error->error_type]++;
            
            // Count by job
            if (!isset($summary['errors_by_job'][$error->job_name])) {
                $summary['errors_by_job'][$error->job_name] = 0;
            }
            $summary['errors_by_job'][$error->job_name]++;
            
            // Count unresolved
            if (!$error->resolved) {
                $summary['unresolved_count']++;
            }
            
            // Add to recent errors (last 10)
            if (count($summary['recent_errors']) < 10) {
                $summary['recent_errors'][] = [
                    'timestamp' => $error->fecha . ' ' . $error->hora,
                    'type' => $error->error_type,
                    'message' => $error->error_message,
                    'resolved' => $error->resolved
                ];
            }
        }
        
        $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'verifactu_summary_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo $json;
        exit;
    }

    protected function loadErrorData(): void
    {
        $idempresa = (int)filter_input(INPUT_GET, 'idempresa', FILTER_VALIDATE_INT) ?: 1;
        $days = (int)filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT) ?: 7;
        
        // Obtener errores recientes
        $this->errors = VerifactuErrorLog::getRecentErrors($idempresa, $days);
        
        // Calcular estadísticas
        $this->errorStats = $this->calculateErrorStats($this->errors);
    }
    
    protected function loadCompanies(): void
    {
        $where = [
            new DataBaseWhere('vf_certificate', null, 'IS NOT'),
            new DataBaseWhere('vf_password', null, 'IS NOT'),
        ];
        $this->companies = Empresa::all($where);
    }
    
    protected function loadErrorList(): void
    {
        // Usar parámetros de GET para filtros en lugar de request->get
        $idempresa = (int)filter_input(INPUT_GET, 'idempresa', FILTER_VALIDATE_INT) ?: 1;
        $errorType = filter_input(INPUT_GET, 'error_type', FILTER_SANITIZE_STRING) ?: '';
        $resolved = filter_input(INPUT_GET, 'resolved', FILTER_SANITIZE_STRING) ?: '';
        
        $where = [new DataBaseWhere('idempresa', $idempresa)];
        
        if (!empty($errorType)) {
            $where[] = new DataBaseWhere('error_type', $errorType);
        }
        
        if ($resolved !== '') {
            $where[] = new DataBaseWhere('resolved', $resolved === '1');
        }
        
        $errorLogModel = new VerifactuErrorLog();
        $this->errorList = $errorLogModel->all($where, ['fecha' => 'DESC'], 0, 50);
    }
    
    protected function calculateErrorStats(array $errors): array
    {
        $stats = [
            'total_errors' => count($errors),
            'unresolved_count' => 0,
            'by_type' => [],
            'certificate_issues' => 0
        ];
        
        foreach ($errors as $error) {
            if (!$error->resolved) {
                $stats['unresolved_count']++;
            }
            
            if (!isset($stats['by_type'][$error->error_type])) {
                $stats['by_type'][$error->error_type] = 0;
            }
            $stats['by_type'][$error->error_type]++;
            
            if ($error->error_type === 'CERTIFICATE_ERROR') {
                $stats['certificate_issues']++;
            }
        }
        
        return $stats;
    }

    public function getCompanies(): array
    {
        $where = [
            new DataBaseWhere('vf_certificate', null, 'IS NOT'),
            new DataBaseWhere('vf_password', null, 'IS NOT'),
        ];
        return Empresa::all($where);
    }
}