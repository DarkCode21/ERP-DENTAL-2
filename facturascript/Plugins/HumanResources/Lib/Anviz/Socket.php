<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Anviz;

use RuntimeException;

/**
 * Manage connection and communication with a device.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Socket
{

    /**
     * Contains the error code when establishing the connection.
     *
     * @var int
     */
    private $errorCode;

    /**
     * Contains the error message when establishing the connection.
     *
     * @var string
     */
    private $errorMessage;

    /**
     * Device ip address.
     *
     * @var string
     */
    private $host;

    /**
     * Device port.
     *
     * @var int
     */
    private $port;

    /**
     * Connection for communication with the device.
     *
     * @var resource|false
     */
    private $socket;

    /**
     * Class constructor.
     *
     * @param string $host
     * @param int $port
     */
    function __construct(string $host, int $port) {
        $this->host = $host;
        $this->errorCode = 0;
        $this->errotMessage = '';
        $this->port = $port;
    }

    /**
     * Make a data request to the device.
     * Sends the indicated frame and returns the response.
     *
     * @param string $data
     * @return string
     * @throws RuntimeException
     */
    public function request(string $data): string
    {
        if (false === $this->connect()) {
            throw new RuntimeException(
                sprintf('Can not connect to %s:%d', $this->host, $this->port),
                $this->errorCode,
                $this->errorMessage
            );
        }

        $response = '';
        try {
            $this->send($data);
            $this->read($response);
        } finally {
            fclose($this->socket);
        }
        return $response;
    }

    /**
     * Create a socket connection.
     * Return false if cant.
     *
     * @return bool
     */
    private function connect(): bool
    {
        $this->socket = fsockopen(
            $this->host,
            $this->port,
            $this->errorCode,
            $this->errorMessage
        );

        return false !== $this->socket;
    }

    /**
     * Read response from device through the established connection.
     *
     * @param string $response
     */
    private function read(&$response)
    {
        $info = stream_get_meta_data($this->socket);
        while (false === feof($this->socket) && !($info['timed_out'] ?? true)) {
            $response .= fgets($this->socket);
            $info = stream_get_meta_data($this->socket);
        }
    }

    /**
     * Send data to the device through the established connection.
     *
     * @param string $data
     */
    private function send($data)
    {
        fwrite($this->socket, $data);
        stream_set_timeout($this->socket, 3);
    }
}
