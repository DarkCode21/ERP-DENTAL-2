<?php
/**
 * This file is part of Backup plugin for FacturaScripts
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Backup;

use Coderatio\SimpleBackup\SimpleBackup;
use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Core\Tools;
use ZipArchive;

class Cron extends CronClass
{
    const JOB_NAME = 'daily-backup';

    public function run(): void
    {
        $this->job(self::JOB_NAME)
            ->every('1 day')
            ->run(function () {
                $this->createBackup();
            });
    }

    protected function createBackup(): void
    {
        $sql_file_name = $this->createSqlFile();
        if (false === $sql_file_name) {
            Tools::log(self::JOB_NAME)->error('sql-file-error');
            return;
        }

        if (false === $this->createZipFile($sql_file_name)) {
            Tools::log(self::JOB_NAME)->error('zip-file-error');
            return;
        }

        Tools::log(self::JOB_NAME)->info('backup-created');

        $this->purgeOldBackups(10);
    }

    protected function purgeOldBackups(int $max): void
    {
        $folder = Tools::folder('MyFiles', 'Backups');
        $files = [];
        foreach (Tools::folderScan($folder) as $file) {
            $ext = substr($file, -4);
            if ($ext === '.sql' || $ext === '.zip') {
                $files[] = $file;
            }
        }

        // agrupamos por prefijo de fecha (YYYY-MM-DD)
        $groups = [];
        foreach ($files as $file) {
            $key = substr($file, 0, 10); // YYYY-MM-DD
            $groups[$key][] = $file;
        }

        // ordenamos descendente (más recientes primero)
        krsort($groups);

        $count = 0;
        foreach ($groups as $key => $groupFiles) {
            $count++;
            if ($count > $max) {
                foreach ($groupFiles as $f) {
                    $path = Tools::folder('MyFiles', 'Backups', $f);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }
        }
    }

    protected function createSqlFile(): string|false
    {
        // si el puerto no es el puerto por defecto, mostramos un aviso
        if (FS_DB_PORT != 3306) {
            Tools::log(self::JOB_NAME)->warning('backup-port-warning', [
                '%port%' => FS_DB_PORT
            ]);
            return false;
        }

        if (false === extension_loaded('pdo_mysql')) {
            Tools::log(self::JOB_NAME)->error('pdo-mysql-support-only');
            return false;
        }

        $folder = Tools::folder('MyFiles', 'Backups');
        if (false === Tools::folderCheckOrCreate($folder)) {
            Tools::log(self::JOB_NAME)->error('folder-create-error');
            return false;
        }

        $file_name = date('Y-m-d_H-i-s') . '.sql';
        SimpleBackup::setDatabase([FS_DB_NAME, FS_DB_USER, FS_DB_PASS, FS_DB_HOST])
            ->storeAfterExportTo($folder, $file_name);

        $file_path = Tools::folder('MyFiles', 'Backups', $file_name);
        if (false === file_exists($file_path)) {
            Tools::log(self::JOB_NAME)->error('record-save-error');
            return false;
        }

        return $file_name;
    }

    protected function createZipFile(string $sql_file_name): bool
    {
        $sql_file_path = Tools::folder('MyFiles', 'Backups', $sql_file_name);
        $base_name = substr($sql_file_name, 0, -4);
        $zip_file_path = Tools::folder('MyFiles', 'Backups', $base_name . '.zip');

        $zip = new ZipArchive();
        if (false === $zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }

        $zip->addFile($sql_file_path, $sql_file_name);
        $zip->close();
        unlink($sql_file_path);

        return file_exists($zip_file_path);
    }
}
