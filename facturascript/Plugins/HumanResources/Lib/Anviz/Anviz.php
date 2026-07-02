<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Anviz;

use FacturaScripts\Plugins\HumanResources\Lib\Anviz\AnvizDevice;
use FacturaScripts\Plugins\HumanResources\Lib\Anviz\AnvizHandler;
use FacturaScripts\Plugins\HumanResources\Lib\Anviz\Socket;
use FacturaScripts\Plugins\HumanResources\Lib\Anviz\Tools;

/**
 * Description of Anviz
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Anviz {

    /**
     * Command codes list.
     */
    private const COMMAND_NEW_ATTENDANCES = 0x3C;
    private const COMMAND_CLEAR = 0x4E;

    /**
     * Restart; retrieve new records.
     * The first data packet must send this data when retrieving the new records.
     */
    private const DOWNLOAD_NEW = 0x02;

    /**
     * Device to connect.
     *
     * @var AnvizDevice
     */
    private $device;

    /**
     * Class constructor.
     *
     * @param AnvizDevice $device
     */
    public function __construct(AnvizDevice $device) {
        $this->device = $device;
    }

    /**
     *
     * @return array
     */
    public function clearAttendances(): array
    {
        $handlers = $this->handlers();
        $result = $this->runCommand(
            self::COMMAND_CLEAR,
            sprintf("%02x%06x", 0x01, 0x00),
            $handlers
        )['attendances'] ?? [];
        return $result;
    }

    /**
     * Request new attendances to the device.
     *
     * @return array
     */
    public function downloadNewAttendances(): array
    {
        $handlers = $this->handlers();
        $records = $this->runCommand(
            self::COMMAND_NEW_ATTENDANCES,
            '',
            $handlers
        );

        $result = $records['attendances'] ?? [];
        $newRecordsAmount = $records['information']['new_record_amount'] ?? 0;
        if ($newRecordsAmount == 0) {
            return $result;
        }

        $num = min(25, $newRecordsAmount);
        $commands = [
            [0x40, sprintf("%02x%02x", self::DOWNLOAD_NEW, $num)],
        ];
        $remainRecordsAmount = $newRecordsAmount - $num;
        while ($remainRecordsAmount > 0) {
            $num = min(25, $remainRecordsAmount);
            $commands[] = [0x40, sprintf("%02x%02x", 0, $num)];
            $remainRecordsAmount -= $num;
        }

        foreach ($commands as list($command, $data)) {
            $result += $this->runCommand(
                $command,
                $data,
                $handlers
            )['attendances'] ?? [];
        }
        return $result;
    }

    /**
     * List of handlers by type of response.
     *
     * @return array
     */
    private function handlers(): array
    {
        AnvizHandler::$dateTimeZone = $this->device->dateTimeZone;
        return [
            0xBC => AnvizHandler::informationHandler(),
            0xDF => AnvizHandler::attendanceHandler(),
            0xC0 => AnvizHandler::attendanceHandler(),
            0xCE => AnvizHandler::clearedHandler(),
        ];
    }

    /**
     * Exec request to device.
     *
     * @param int $command
     * @param string|null $data
     * @return array
     */
    private function request(int $command, string $data = ''): array
    {
        $requestData = Tools::createRequestString($this->device->deviceId, $command, $data);
        $socket = new Socket($this->device->host, $this->device->port);
        $response = $socket->request($requestData);
        return Tools::parse($response);
    }

    /**
     * Send a command to the device and return the response.
     *
     * @param int $command
     * @param string|null $data
     * @param array $handlers
     * @return array
     */
    private function runCommand(int $command, string $data, array $handlers): array
    {
        $responses = $this->request($command, $data);
        $result = [];
        foreach ($responses as $item) {
            if (false === isset($item['ack'])) {
                continue;
            }
            $handlerFunction = $handlers[$item['ack']] ?? null;
            if (isset($handlerFunction)) {
                $handlerFunction($item, $result);
            }
        }

        return $result;
    }
}
