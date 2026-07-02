<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\EnviarDocumentos\Extension\Model;

use Closure;

/**
 * Description of Cliente
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Cliente
{
    public function testBefore(): Closure
    {
        return function () {
            $this->checkEmail('emailto');
            $this->checkEmail('emailcc');
            $this->checkEmail('emailbcc');
        };
    }

    public function checkEmail(): Closure
    {
        return function ($field) {
            // comprobamos que el campo no este vacío
            if (empty($this->{$field})) {
                return;
            }

            // comprobamos que los emails sean válidos
            $result = [];
            foreach (explode(',', $this->{$field}) as $email) {
                $value = trim($email);
                if (empty($value)) {
                    continue;
                }

                if (filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                    $result[] = trim($value);
                    continue;
                }

                $this->toolbox()->i18nLog()->warning('not-valid-email', ['%email%' => $value]);
            }

            // guardamos el resultado
            if (false === empty($result)) {
                $this->{$field} = implode(',', $result);
            }
        };
    }
}