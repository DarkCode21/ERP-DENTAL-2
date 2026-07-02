<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Contract;

/**
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
interface AutoTemplateInterface
{
    public function continue(): bool;

    public function getTotalLines(): int;

    public function isValid(string $filePath, string $profile): bool;

    public function run(string $filePath, string $profile, string $mode, int &$offset, int &$saveLines): bool;
}
