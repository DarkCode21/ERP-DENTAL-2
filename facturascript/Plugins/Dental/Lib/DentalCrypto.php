<?php
/**
 * DentalCrypto
 * 
 * Sistema de cifrado AES-256 para datos clinicos sensibles
 */

namespace FacturaScripts\Plugins\Dental\Lib;

class DentalCrypto
{
    private const CIPHER = 'aes-256-cbc';
    private const KEY_PATH = FS_FOLDER . '/MyFiles/keys/dental_module.key';

    public static function generateKey(): string
    {
        $key = openssl_random_pseudo_bytes(32);
        $dir = dirname(self::KEY_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents(self::KEY_PATH, $key, 0600);
        return $key;
    }

    public static function getKey(): string
    {
        if (!file_exists(self::KEY_PATH)) {
            self::generateKey();
        }
        return file_get_contents(self::KEY_PATH);
    }

    public static function encrypt(string $data): string
    {
        $key = self::getKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($data, self::CIPHER, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $encryptedData): string
    {
        $key = self::getKey();
        $data = base64_decode($encryptedData);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        return openssl_decrypt($encrypted, self::CIPHER, $key, 0, $iv);
    }
}
