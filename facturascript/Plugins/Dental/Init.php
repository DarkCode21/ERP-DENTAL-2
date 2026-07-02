<?php

namespace FacturaScripts\Plugins\Dental;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Kernel;

class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init(): void
    {
        Kernel::addRoute('/CalendarDental', Controller\CalendarDental::class, -1, 'dental-calendar');
    }

    public function update(): void
    {
        new Model\Especialidad();
        new Model\Gabinete();
        new Model\Especialista();
        new Model\EspecialistaEspecialidad();
        new Model\Paciente();
        new Model\Cita();
        new Model\Historial();
        new Model\Archivo();
        new Model\TratamientoPaciente();
        new Model\BloqueoAgenda();

        $this->updateSalonColumns();
    }

    public function uninstall(): void
    {
    }

    private function updateSalonColumns(): void
    {
        $dataBase = new DataBase();
        $columns = [
            'dental_citas' => [
                'salon_booking_id' => 'INTEGER',
                'salon_customer_id' => 'INTEGER',
                'salon_service_id' => 'INTEGER',
                'salon_sync_status' => 'VARCHAR(20)',
                'salon_sync_error' => 'TEXT',
                'salon_synced_at' => 'TIMESTAMP NULL DEFAULT NULL',
            ],
            'dental_pacientes' => [
                'salon_customer_id' => 'INTEGER',
            ],
            'dental_especialistas' => [
                'salon_assistant_id' => 'INTEGER',
            ],
            'dental_tratamientos_paciente' => [
                'salon_service_id' => 'INTEGER',
            ],
        ];

        foreach ($columns as $tableName => $tableColumns) {
            $existing = $dataBase->getColumns($tableName);
            if (empty($existing)) {
                continue;
            }

            foreach ($tableColumns as $columnName => $type) {
                if (isset($existing[$columnName])) {
                    continue;
                }

                $dataBase->exec('ALTER TABLE ' . $dataBase->escapeColumn($tableName)
                    . ' ADD COLUMN ' . $dataBase->escapeColumn($columnName) . ' ' . $type);
            }
        }
    }
}
