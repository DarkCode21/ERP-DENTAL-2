<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Anviz;

/**
 * Handler functions library.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AnvizHandler {

    /**
     * Result operation code: successful.
     */
    private const ACK_SUCCESS = 0x00;

    /**
     * For Anviz devices zero second means 2000/Jan/02 00:00:00 GMT+0
     */
    private const ANVIZ_EPOCH = 946771200;

    /**
     *
     * @var \DateTimeZone
     */
    public static $dateTimeZone;

    /**
     *
     * @return function
     */
    public static function attendanceHandler()
    {
        return function ($response, &$result) {
            if (self::ACK_SUCCESS !== $response['ret']) {
                return;
            }

            if (false === isset($result['attendances'])) {
                $result['attendances'] = [];
            }

            $attendances = [];
            $data = $response['data'];
            $length = (0xC0 === $response['ack']) ? hexdec($data[0]) : 1;
            $lenByteOffset = (0xC0 === $response['ack']) ? 1 : 0;

            for ($i = 0; $i < $length; $i++) {
                $itemOffset = $i * 14 + $lenByteOffset;
                $record = static::getRecordAttendance($data, $itemOffset);
                $attendances[md5(json_encode($record))] = $record;
            }
            $result['attendances'] += $attendances;
        };
    }

    /**
     *
     * @return function
     */
    public static function clearedHandler()
    {
        return function ($response, &$result) {
            if (self::ACK_SUCCESS !== $response['ret']) {
                return;
            }
            $result['cleared'] = hexdec(implode($response['data']));
        };
    }

    /**
     *
     * @return function
     */
    public static function informationHandler()
    {
        return function ($response, &$result) {
            if (self::ACK_SUCCESS !== $response['ret']) {
                return;
            }
            $data = $response['data'];
            $result['information'] = static::getRecordInformation($data);
        };
    }

    /**
     *
     * @param array $data
     * @param int $offset
     * @return array
     */
    private static function getRecordAttendance(array &$data, int $offset): array
    {
        $dateTimeZoneOffset = static::ANVIZ_EPOCH;
        if (isset(static::$dateTimeZone)) {
            $dateTimeZoneOffset -= static::$dateTimeZone->getOffset(
                new \DateTime('now', new \DateTimeZone('UTC'))
            );
        }
        return [
            'user_code' => hexdec(implode(array_slice($data, $offset, 5))),
            'timestamp' => hexdec(implode(array_slice($data, $offset + 5, 4))) + $dateTimeZoneOffset,
            'backup_code' => hexdec($data[$offset + 9]),
            'record_type' => hexdec($data[$offset + 10] & 0xF),
            'work_type' => hexdec(implode(array_slice($data, $offset + 11, 2))),
        ];
    }

    /**
     *
     * @param array $data
     * @return array
     */
    private static function getRecordInformation(array &$data): array
    {
        return [
            'user_amount' => hexdec(implode(array_slice($data, 0, 3))),
            'fp_amount' => hexdec(implode(array_slice($data, 3, 3))),
            'password_amount' => hexdec(implode(array_slice($data, 6, 3))),
            'card_amount' => hexdec(implode(array_slice($data, 9, 3))),
            'all_record_amount' => hexdec(implode(array_slice($data, 12, 3))),
            'new_record_amount' => hexdec(implode(array_slice($data, 15, 3))),
        ];
    }
}
