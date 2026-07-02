<?php
/**
 * This file is part of InformeSII plugin for FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\InformeSII\Lib;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Dinamic\Model\Empresa;
use SimpleXMLElement;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SuministroSII
{
    const NS_ENVELOPE = 'env';

    const NS_1 = 'sii';

    const NS_2 = 'siiR';

    /** @var string */
    protected $codpago;

    /** @var string */
    protected $codpais;

    /** @var string */
    protected $codserie;

    /** @var string */
    protected $endDate;

    /** @var int */
    protected $idempresa;

    /** @var string */
    protected $provincia;

    /** @var string */
    protected $startDate;

    /** @var string */
    protected $xml = '';

    public function getXml(): string
    {
        return $this->xml;
    }

    protected function escape(string $text): string
    {
        // reemplazamos los & por &amp;
        return str_replace('&', '&amp;', Utils::fixHtml($text));
    }

    protected function getCompany(): Empresa
    {
        return Empresas::get($this->idempresa);
    }

    /**
     * Recupera la URL del servicio por el nombre de puerto, escaneando la lista de
     * puertos disponible en el fichero de definición
     * https://akrabat.com/selecting-port-for-phps-soapclient/
     */
    protected function getLocationForPort(string $wsdl, string $portName): string
    {
        $file = file_get_contents($wsdl);

        $xml = new SimpleXmlElement($file);

        $query = "wsdl:service/wsdl:port[@name='$portName']/soap:address";
        $address = $xml->xpath($query);

        return isset($address[0]['location']) && false === empty($address[0]['location'])
            ? (string) $address[0]['location']
            : '';
    }

    protected function prepararCertificadoLocal($fichero_certificado, $clave_certificado): string
    {
        $fichero_certificado = FS_FOLDER . DIRECTORY_SEPARATOR . $fichero_certificado;
        if (false === file_exists($fichero_certificado)) {
            ToolBox::i18nLog()->warning('error-cert-not-found');
            return '';
        }

        // Recuperar nombre del certificado sin extension
        $cert_name = pathinfo($fichero_certificado, PATHINFO_FILENAME);

        // Fichero donde guardaremos el certificado nuevo convertido
        $dir_certificados = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles';
        $bundle_file = $dir_certificados . DIRECTORY_SEPARATOR . $cert_name . '_bundle.pem';

        // Si ya existe es que ya fue convertido, lo devolvemos
        if (file_exists($bundle_file)) {
            return $bundle_file;
        }

        // extraemos la información del certificado
        if (false === openssl_pkcs12_read(file_get_contents($fichero_certificado), $certs, $clave_certificado)) {
            ToolBox::i18nLog()->warning('error-read-cert');
            return '';
        }

        // creamos el archivo bundle.pem con el certificado y la clave privada
        if (false === file_put_contents($bundle_file, $certs['cert'], FILE_APPEND)
            || false === file_put_contents($bundle_file, $certs['pkey'], FILE_APPEND)) {
            ToolBox::i18nLog()->warning('error-write-cert');
            return '';
        }

        return $bundle_file;
    }
}