<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Attendance;
use FacturaScripts\Dinamic\Model\BiometricDevice;
use FacturaScripts\Plugins\HumanResources\Lib\Anviz\AnvizDevice;
use FacturaScripts\Plugins\HumanResources\Lib\Anviz\Anviz;

/**
 * Description of BiometricAnviz
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class BiometricAnviz {

    /**
     *
     * @param BiometricDevice $device
     * @return int
     */
    public function import($device): int
    {
        $anvizDevice = new AnvizDevice(
            $device->timezone,
            $device->host,
            $device->port,
            $device->device
        );

        $anviz = new Anviz($anvizDevice);
        $attendanceList = $anviz->downloadNewAttendances();
        if (empty($attendanceList)) {
            return 0;
        }

        $result = 0;
        $database = new DataBase();
        $database->beginTransaction();
        try {
            foreach ($attendanceList as $item) {
                if (false === $this->insertAttendance($item)) {
                    Tools::log()->error('attendance-import-error', [
                        'code' => $item['user_code'],
                        'date' => date('d-m-Y', $item['timestamp']),
                        'time' => date('H:i:s', $item['timestamp'])
                    ]);
                    continue;
                }
                $result++;
            }
            $database->commit();
            $anviz->clearAttendances();
        } catch (Exception $ex) {
            $database->rollback();
            $result = 0;
            Tools::log()->error($ex->getMessage());
        }
        return $result;
    }

    /**
     *
     * @param array $data
     * @return bool
     */
    private function insertAttendance(array $data): bool
    {
        $where = [
            new DataBaseWhere('credentialid', $data['user_code']),
            new DataBaseWhere('checkdate', date('d-m-Y', $data['timestamp'])),
            new DataBaseWhere('checktime', date('H:i:s', $data['timestamp'])),
        ];

        $attendance = new Attendance();
        if ($attendance->loadFromCode('', $where)) {
            return true;
        }

        $attendance->origin = Attendance::ORIGIN_EXTERNAL;
        $attendance->credentialid = $data['user_code'];
        $attendance->checkdate = date(Attendance::DATE_STYLE, $data['timestamp']);
        $attendance->checktime = date('H:i:s', $data['timestamp']);
        $attendance->kind = $data['record_type'] == 1 ? Attendance::KIND_OUTPUT : Attendance::KIND_INPUT;
        return $attendance->save();
    }
}
