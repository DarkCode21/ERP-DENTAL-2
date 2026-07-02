<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2024-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Extends Symfony Request with all methods from Core\Request.
 * Satisfies all Symfony Request type hints while providing the new API.
 */
class AppRequest extends Request
{
    /** Get combined POST + GET array */
    public function all(string ...$key): array
    {
        if (empty($key)) {
            return array_merge($this->request->all(), $this->query->all());
        }
        $result = [];
        foreach ($key as $k) {
            $result[$k] = $this->get($k);
        }
        return $result;
    }

    public function browser(): string
    {
        $ua = $this->userAgent();
        if (stripos($ua, 'chrome') !== false) return 'chrome';
        if (stripos($ua, 'edg/') !== false || stripos($ua, 'edge') !== false) return 'edge';
        if (stripos($ua, 'firefox') !== false) return 'firefox';
        if (stripos($ua, 'safari') !== false) return 'safari';
        if (stripos($ua, 'opera') !== false) return 'opera';
        if (stripos($ua, 'msie') !== false) return 'ie';
        return 'unknown';
    }

    public function cookie(string $key, $default = null): ?string
    {
        return $this->cookies->get($key, $default);
    }

    public function fullUrl(): string
    {
        return $this->getUri();
    }

    public function getArray(string $key): array
    {
        if ($this->query->has($key)) {
            $v = $this->query->get($key);
            return is_array($v) ? $v : (array)$v;
        }
        $v = $this->request->get($key);
        return is_array($v) ? $v : (array)($v ?? []);
    }

    public function getAlnum(string $key): string
    {
        $value = $this->get($key, '');
        return preg_replace('/[^a-zA-Z0-9]/', '', $value ?? '');
    }

    public function getBool(string $key, ?bool $default = null): ?bool
    {
        $value = $this->get($key);
        if ($value === null) return $default;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function getDate(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);
        if (empty($value)) return $default;
        $date = date_create($value);
        return $date ? date_format($date, 'd-m-Y') : $default;
    }

    public function getDateTime(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);
        if (empty($value)) return $default;
        $date = date_create($value);
        return $date ? date_format($date, 'd-m-Y H:i:s') : $default;
    }

    public function getEmail(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);
        if (empty($value)) return $default;
        return filter_var($value, FILTER_VALIDATE_EMAIL) ?: $default;
    }

    public function getFloat(string $key, ?float $default = null): ?float
    {
        $value = $this->get($key);
        if ($value === null) return $default;
        return is_numeric($value) ? (float)$value : $default;
    }

    public function getHour(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);
        if (empty($value)) return $default;
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? $value : $default;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key);
        if ($value === null) return $default;
        return is_numeric($value) ? (int)$value : $default;
    }

    public function getOnly(string $key, array $values): ?string
    {
        $value = $this->get($key);
        return in_array($value, $values) ? $value : null;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);
        return $value !== null ? (string)$value : $default;
    }

    public function getUrl(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);
        if (empty($value)) return $default;
        return filter_var($value, FILTER_VALIDATE_URL) ?: $default;
    }

    public function header(string $key, $default = null): ?string
    {
        return $this->headers->get($key, $default);
    }

    public function host(): string
    {
        return $this->getHost();
    }

    /** Get a value from POST data (request body). */
    public function input(string $key, $default = null): ?string
    {
        return $this->request->get($key, $default);
    }

    /** Get a value from POST, falling back to GET query string. */
    public function inputOrQuery(string $key, $default = null): ?string
    {
        if ($this->request->has($key)) {
            return $this->request->get($key);
        }
        return $this->query->get($key, $default);
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $field) {
            if (!empty($_SERVER[$field])) {
                return (string)$_SERVER[$field];
            }
        }
        return '::1';
    }

    public function json(?string $key = null, $default = null)
    {
        $input = $this->getContent();
        $data = json_decode($input, true);
        if ($key === null) return $data;
        return $data[$key] ?? $default;
    }

    public function method(): string
    {
        return $this->getMethod();
    }

    public function os(): string
    {
        $ua = $this->userAgent();
        if (stripos($ua, 'windows') !== false) return 'windows';
        if (stripos($ua, 'macintosh') !== false) return 'mac';
        if (stripos($ua, 'linux') !== false) return 'linux';
        if (stripos($ua, 'unix') !== false) return 'unix';
        if (stripos($ua, 'sunos') !== false) return 'sun';
        if (stripos($ua, 'bsd') !== false) return 'bsd';
        return 'unknown';
    }

    public function protocol(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /** Get a value from GET query string, falling back to POST. */
    public function queryOrInput(string $key, $default = null): ?string
    {
        if ($this->query->has($key)) {
            return $this->query->get($key);
        }
        return $this->request->get($key, $default);
    }

    public function url(?int $position = null): string
    {
        $url = explode('?', $_SERVER['REQUEST_URI'] ?? '')[0];
        if (null === $position) {
            return $url;
        }
        $path = explode('/', $url);
        if ($position < 0) {
            $position = count($path) + $position;
        }
        return $path[$position] ?? '';
    }

    public function urlWithQuery(): string
    {
        return $this->url() . '?' . ($_SERVER['QUERY_STRING'] ?? '');
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
