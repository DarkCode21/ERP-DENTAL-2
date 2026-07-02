<?php

/**
 * DentalFileManager
 * 
 * Gestion segura de archivos clinicos cifrados
 */

namespace FacturaScripts\Plugins\Dental\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Dental\Model\Archivo;
use FacturaScripts\Plugins\Dental\Model\Paciente;

class DentalFileManager
{
    private const UPLOAD_DIR = 'MyFiles/DentalFiles/';
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'dicom'];

    public static function upload($file, Paciente $paciente, string $categoria, ?string $descripcion = null): ?Archivo
    {
        // Validar extension
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            Tools::log()->warning('Extension not allowed: ' . $extension);
            return null;
        }

        // Validar tamaño
        if ($file->getSize() > self::MAX_SIZE) {
            Tools::log()->warning('File too large: ' . $file->getSize());
            return null;
        }

        // Validar MIME type real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file->getPathname());
        finfo_close($finfo);

        // Generar nombre seguro
        $safeName = self::generateSafeName($extension);

        // Crear directorio si no existe
        $uploadPath = FS_FOLDER . '/' . self::UPLOAD_DIR . $paciente->id . '/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Leer contenido y cifrar
        $content = file_get_contents($file->getPathname());
        $encryptedContent = DentalCrypto::encrypt($content);

        // Guardar archivo cifrado
        $fullPath = $uploadPath . $safeName;
        file_put_contents($fullPath, $encryptedContent);

        // Crear registro en base de datos
        $archivo = new Archivo();
        $archivo->idpaciente = $paciente->id;
        $archivo->categoria = $categoria;
        $archivo->nombre_original = $file->getClientOriginalName();
        $archivo->nombre_archivo = $safeName;
        $archivo->extension = $extension;
        $archivo->mime_type = $realMime;
        $archivo->tamano = $file->getSize();
        $archivo->ruta = self::UPLOAD_DIR . $paciente->id . '/' . $safeName;
        $archivo->hash_archivo = hash_file('sha256', $fullPath);
        $archivo->descripcion = $descripcion ?? '';

        if ($archivo->save()) {
            return $archivo;
        }

        return null;
    }

    public static function download(Archivo $archivo): ?string
    {
        $fullPath = FS_FOLDER . '/' . $archivo->ruta;

        if (!file_exists($fullPath)) {
            Tools::log()->warning('File not found: ' . $fullPath);
            return null;
        }

        // Verificar hash
        if ($archivo->hash_archivo !== hash_file('sha256', $fullPath)) {
            Tools::log()->warning('File hash mismatch: ' . $archivo->ruta);
            return null;
        }

        // Leer y descifrar
        $encryptedContent = file_get_contents($fullPath);
        return DentalCrypto::decrypt($encryptedContent);
    }

    public static function delete(Archivo $archivo): bool
    {
        $fullPath = FS_FOLDER . '/' . $archivo->ruta;

        // Eliminar archivo fisico si existe
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Marcar como eliminado en BD
        $archivo->estado = 'eliminado';
        return $archivo->save();
    }

    private static function generateSafeName(string $extension): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
    }
}
