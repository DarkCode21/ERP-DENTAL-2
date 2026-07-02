<?php
/**
 * DentalLogger
 * 
 * Registro de actividad clinica
 */

namespace FacturaScripts\Plugins\Dental\Lib;

use FacturaScripts\Core\Tools;

class DentalLogger
{
    const ACTION_PATIENT_VIEW = 'patient_view';
    const ACTION_PATIENT_CREATE = 'patient_create';
    const ACTION_PATIENT_UPDATE = 'patient_update';
    const ACTION_HISTORY_CREATE = 'history_create';
    const ACTION_HISTORY_UPDATE = 'history_update';
    const ACTION_FILE_UPLOAD = 'file_upload';
    const ACTION_FILE_DOWNLOAD = 'file_download';
    const ACTION_CITA_CREATE = 'cita_create';
    const ACTION_CITA_UPDATE = 'cita_update';
    const ACTION_CITA_CANCEL = 'cita_cancel';

    public static function log(string $action, $user, ?int $idPaciente = null, ?int $idRegistro = null, ?string $detalles = null): void
    {
        $message = 'Dental: ' . self::getActionLabel($action);
        $context = [
            'action' => $action,
            'user' => $user->nick ?? 'unknown',
            'idpaciente' => $idPaciente,
            'idregistro' => $idRegistro,
            'detalles' => $detalles,
        ];

        Tools::log('dental')->info($message, $context);
    }

    public static function logPatientAccess($user, int $idPaciente): void
    {
        self::log(self::ACTION_PATIENT_VIEW, $user, $idPaciente);
    }

    public static function logPatientCreate($user, int $idPaciente, string $nombre): void
    {
        self::log(self::ACTION_PATIENT_CREATE, $user, $idPaciente, null, 'Nombre: ' . $nombre);
    }

    public static function logHistoryCreate($user, int $idPaciente, int $idHistorial, string $tipo): void
    {
        self::log(self::ACTION_HISTORY_CREATE, $user, $idPaciente, $idHistorial, 'Tipo: ' . $tipo);
    }

    public static function logFileUpload($user, int $idPaciente, int $idArchivo, string $nombreOriginal): void
    {
        self::log(self::ACTION_FILE_UPLOAD, $user, $idPaciente, $idArchivo, 'Archivo: ' . $nombreOriginal);
    }

    public static function logCitaCreate($user, int $idPaciente, int $idCita, string $fecha): void
    {
        self::log(self::ACTION_CITA_CREATE, $user, $idPaciente, $idCita, 'Fecha: ' . $fecha);
    }

    public static function logCitaCancel($user, int $idPaciente, int $idCita, string $motivo): void
    {
        self::log(self::ACTION_CITA_CANCEL, $user, $idPaciente, $idCita, 'Motivo: ' . $motivo);
    }

    private static function getActionLabel(string $action): string
    {
        $labels = [
            self::ACTION_PATIENT_VIEW => 'Visualizo paciente',
            self::ACTION_PATIENT_CREATE => 'Creo paciente',
            self::ACTION_PATIENT_UPDATE => 'Actualizo paciente',
            self::ACTION_HISTORY_CREATE => 'Creo entrada historial',
            self::ACTION_HISTORY_UPDATE => 'Actualizo historial',
            self::ACTION_FILE_UPLOAD => 'Subio archivo',
            self::ACTION_FILE_DOWNLOAD => 'Descargo archivo',
            self::ACTION_CITA_CREATE => 'Creo cita',
            self::ACTION_CITA_UPDATE => 'Actualizo cita',
            self::ACTION_CITA_CANCEL => 'Cancelo cita',
        ];
        return $labels[$action] ?? $action;
    }
}
