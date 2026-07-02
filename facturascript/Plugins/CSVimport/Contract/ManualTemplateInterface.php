<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Contract;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
interface ManualTemplateInterface
{
    public function getDataFields(): array;

    public function getFieldsToColumn(): array;

    public static function getProfile(): string;

    public function getRequiredFieldsAnd(): array;

    public function getRequiredFieldsOr(): array;

    public function importItem(array $item): bool;
}
