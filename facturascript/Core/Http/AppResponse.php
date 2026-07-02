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

use Symfony\Component\HttpFoundation\Response;

/**
 * Extends Symfony Response with all methods from Core\Response.
 * Satisfies all Symfony Response type hints while providing the new API.
 */
class AppResponse extends Response
{
    public function cookie(string $name, ?string $value, int $expire = 0, bool $httpOnly = true, ?bool $secure = null, string $sameSite = 'Lax'): self
    {
        if ($secure === null) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        }

        $cookie = new \Symfony\Component\HttpFoundation\Cookie(
            $name,
            $value ?? '',
            $expire ?: (time() + 3600 * 24 * 365),
            '/',
            null,
            $secure,
            $httpOnly,
            false,
            $sameSite
        );
        $this->headers->setCookie($cookie);
        return $this;
    }

    public function download(string $file_path, string $file_name = ''): void
    {
        $this->file($file_path, $file_name, 'attachment');
    }

    public function file(string $file_path, string $file_name = '', string $disposition = 'inline'): void
    {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            $this->setStatusCode(404);
            return;
        }

        $real_path = realpath($file_path);
        if ($real_path === false || is_dir($real_path)) {
            $this->setStatusCode(403);
            return;
        }

        $mime_type = mime_content_type($real_path) ?: 'application/octet-stream';
        $disp = $disposition;
        if (false === empty($file_name)) {
            $safe = basename($file_name);
            $disp .= '; filename="' . $safe . '"';
        }

        $this->headers->set('Content-Type', $mime_type);
        $this->headers->set('Content-Disposition', $disp);
        $this->headers->set('Content-Length', (string)filesize($real_path));
        $this->setContent((string)file_get_contents($real_path));
    }

    public function getHttpCode(): int
    {
        return $this->getStatusCode();
    }

    public function header(string $name, string $value): self
    {
        $this->headers->set($name, $value);
        return $this;
    }

    public function json(array $data): void
    {
        $this->headers->set('Content-Type', 'application/json');
        $this->setContent(json_encode($data));
        $this->send();
    }

    public function pdf(string $content, string $file_name = ''): void
    {
        $safe = empty($file_name) ? 'document.pdf' : basename($file_name);
        $this->headers->set('Content-Type', 'application/pdf');
        $this->headers->set('Content-Disposition', 'inline; filename="' . $safe . '"');
        $this->headers->set('Content-Length', (string)strlen($content));
        $this->setContent($content);
        $this->send();
    }

    public function redirect(string $url, int $delay = 0): self
    {
        if ($delay > 0) {
            $this->headers->set('Refresh', $delay . '; url=' . $url);
        } else {
            $this->headers->set('Location', $url);
        }
        return $this;
    }

    public function setHttpCode(int $http_code): self
    {
        $this->setStatusCode($http_code);
        return $this;
    }

    public function view(string $view, array $data = []): void
    {
        $this->headers->set('Content-Type', 'text/html');
        $this->setContent(\FacturaScripts\Core\Html::render($view, $data));
        $this->send();
    }

    public function withoutCookie(string $name): self
    {
        $this->headers->clearCookie($name);
        return $this;
    }
}
