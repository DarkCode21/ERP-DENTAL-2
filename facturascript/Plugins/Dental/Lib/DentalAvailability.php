<?php

/**
 * DentalAvailability
 * 
 * Control de disponibilidad y deteccion de solapamientos
 */

namespace FacturaScripts\Plugins\Dental\Lib;

use FacturaScripts\Core\Base\DataBase;

class DentalAvailability
{
    /** @var DataBase */
    private static $dataBase;

    private static function db(): DataBase
    {
        if (null === self::$dataBase) {
            self::$dataBase = new DataBase();
        }
        return self::$dataBase;
    }

    public static function isEspecialistaAvailable(
        int $idespecialista,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $excludeCitaId = null
    ): bool {
        // Verificar si tiene una cita solapada
        $sql = "SELECT 1 FROM dental_citas WHERE idespecialista = " . self::db()->var2str($idespecialista);
        $sql .= " AND fecha = " . self::db()->var2str($fecha);
        $sql .= " AND estado NOT IN ('cancelada', 'no_asistio')";

        if ($excludeCitaId !== null) {
            $sql .= " AND id != " . self::db()->var2str($excludeCitaId);
        }

        $sql .= " AND ((hora_inicio < " . self::db()->var2str($horaFin) . " AND hora_fin > " . self::db()->var2str($horaInicio) . "))";

        $result = self::db()->select($sql);
        if (!empty($result)) {
            return false;
        }

        // Verificar si tiene un bloqueo activo
        $sqlBloqueo = "SELECT 1 FROM dental_bloqueos_agenda WHERE idespecialista = " . self::db()->var2str($idespecialista);
        $sqlBloqueo .= " AND fecha = " . self::db()->var2str($fecha);
        $sqlBloqueo .= " AND ((hora_inicio < " . self::db()->var2str($horaFin) . " AND hora_fin > " . self::db()->var2str($horaInicio) . "))";

        $resultBloqueo = self::db()->select($sqlBloqueo);
        return empty($resultBloqueo);
    }

    public static function isGabineteAvailable(
        int $idgabinete,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $excludeCitaId = null
    ): bool {
        $sql = "SELECT 1 FROM dental_citas WHERE idgabinete = " . self::db()->var2str($idgabinete);
        $sql .= " AND fecha = " . self::db()->var2str($fecha);
        $sql .= " AND estado NOT IN ('cancelada', 'no_asistio')";

        if ($excludeCitaId !== null) {
            $sql .= " AND id != " . self::db()->var2str($excludeCitaId);
        }

        $sql .= " AND ((hora_inicio < " . self::db()->var2str($horaFin) . " AND hora_fin > " . self::db()->var2str($horaInicio) . "))";

        $result = self::db()->select($sql);
        if (!empty($result)) {
            return false;
        }

        // Verificar bloqueos del gabinete
        $sqlBloqueo = "SELECT 1 FROM dental_bloqueos_agenda WHERE idgabinete = " . self::db()->var2str($idgabinete);
        $sqlBloqueo .= " AND fecha = " . self::db()->var2str($fecha);
        $sqlBloqueo .= " AND ((hora_inicio < " . self::db()->var2str($horaFin) . " AND hora_fin > " . self::db()->var2str($horaInicio) . "))";

        $resultBloqueo = self::db()->select($sqlBloqueo);
        return empty($resultBloqueo);
    }

    public static function getAvailableSlots(
        int $idespecialista,
        string $fecha,
        int $duracionMinutos = 30,
        ?int $idgabinete = null
    ): array {
        // Horario de la clinica (09:00 a 14:00 y 16:00 a 20:00)
        $morningSlots = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30'];
        $afternoonSlots = ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30'];
        $allSlots = array_merge($morningSlots, $afternoonSlots);

        // Obtener citas del dia
        $sql = "SELECT hora_inicio, hora_fin FROM dental_citas WHERE idespecialista = " . self::db()->var2str($idespecialista);
        $sql .= " AND fecha = " . self::db()->var2str($fecha);
        $sql .= " AND estado NOT IN ('cancelada', 'no_asistio')";

        $citas = self::db()->select($sql);
        $bookedSlots = [];
        foreach ($citas as $cita) {
            $bookedSlots[] = [
                'inicio' => substr($cita['hora_inicio'], 0, 5),
                'fin' => substr($cita['hora_fin'], 0, 5)
            ];
        }

        // Obtener bloqueos del dia
        $sqlBloqueo = "SELECT hora_inicio, hora_fin FROM dental_bloqueos_agenda WHERE idespecialista = " . self::db()->var2str($idespecialista);
        $sqlBloqueo .= " AND fecha = " . self::db()->var2str($fecha);

        $bloqueos = self::db()->select($sqlBloqueo);
        foreach ($bloqueos as $bloqueo) {
            $bookedSlots[] = [
                'inicio' => substr($bloqueo['hora_inicio'], 0, 5),
                'fin' => substr($bloqueo['hora_fin'], 0, 5)
            ];
        }

        // Calcular huecos libres
        $availableSlots = [];
        foreach ($allSlots as $slotStart) {
            $slotEnd = self::addMinutesToTime($slotStart, $duracionMinutos);

            if ($slotEnd > '14:00' && $slotStart < '16:00') {
                continue;
            }

            $isAvailable = true;
            foreach ($bookedSlots as $booked) {
                if (self::rangesOverlap($slotStart, $slotEnd, $booked['inicio'], $booked['fin'])) {
                    $isAvailable = false;
                    break;
                }
            }

            if ($isAvailable) {
                $availableSlots[] = [
                    'hora_inicio' => $slotStart,
                    'hora_fin' => $slotEnd
                ];
            }
        }

        return $availableSlots;
    }

    public static function rangesOverlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): bool {
        return $start1 < $end2 && $start2 < $end1;
    }

    private static function addMinutesToTime(string $time, int $minutes): string
    {
        $parts = explode(':', $time);
        $hour = (int)$parts[0];
        $minute = (int)$parts[1];
        $totalMinutes = $hour * 60 + $minute + $minutes;
        $newHour = (int)($totalMinutes / 60);
        $newMinute = $totalMinutes % 60;
        return sprintf('%02d:%02d', $newHour, $newMinute);
    }
}
