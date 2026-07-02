<?php
/**
 * DentalCalendar
 * 
 * Helper para renderizar calendario de citas
 */

namespace FacturaScripts\Plugins\Dental\Lib;

use FacturaScripts\Plugins\Dental\Model\Cita;

class DentalCalendar
{
    public static function citasToFullCalendarEvents(array $citas): array
    {
        $events = [];
        foreach ($citas as $cita) {
            $paciente = $cita->getPaciente();
            $especialista = $cita->getEspecialista();
            
            $cliente = $paciente ? $paciente->getCliente() : null;
            $title = $cliente ? $cliente->nombre . ' ' . $cliente->razonsocial : 'Paciente #' . $cita->idpaciente;
            if ($especialista) {
                $title .= ' (' . $especialista->nombre . ')';
            }

            $events[] = [
                'id' => $cita->id,
                'title' => $title,
                'start' => $cita->fecha . 'T' . substr($cita->hora_inicio, 0, 5),
                'end' => $cita->fecha . 'T' . substr($cita->hora_fin, 0, 5),
                'color' => self::getColorByEstado($cita->estado),
                'extendedProps' => [
                    'idpaciente' => $cita->idpaciente,
                    'idespecialista' => $cita->idespecialista,
                    'idgabinete' => $cita->idgabinete,
                    'estado' => $cita->estado,
                    'motivo' => $cita->motivo,
                    'duracion' => $cita->duracion,
                    'confirmada' => $cita->confirmada_paciente,
                ]
            ];
        }
        return $events;
    }

    public static function getColorByEstado(string $estado): string
    {
        $colors = [
            'pendiente' => '#f59e0b',
            'confirmada' => '#3b82f6',
            'en_sala' => '#eab308',
            'en_curso' => '#22c55e',
            'finalizada' => '#6b7280',
            'cancelada' => '#ef4444',
            'no_asistio' => '#fca5a5',
        ];
        return $colors[$estado] ?? '#6b7280';
    }

    public static function getAllEstados(): array
    {
        return [
            'pendiente' => 'Pendiente',
            'confirmada' => 'Confirmada',
            'en_sala' => 'En sala',
            'en_curso' => 'En curso',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            'no_asistio' => 'No asistió',
        ];
    }
}
