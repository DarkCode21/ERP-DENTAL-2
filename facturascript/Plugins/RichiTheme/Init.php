<?php

namespace FacturaScripts\Plugins\RichiTheme;

use FacturaScripts\Core\Html;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use Twig\TwigFunction;

final class Init extends InitClass
{

    public function init(): void
    {
        $this->setupSettings();

        Html::addFunction(new TwigFunction('pluginEnabled', function (string $pluginName): bool {
            return Plugins::isEnabled($pluginName);
        }));
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        $this->setupSettings();
    }

    private function setupSettings(): void
    {
        $defaults = [
            'lbgcolor' => '#f7f7f7',
            'fbgcolor' => '#ffffff',
            'lbtncolor' => '#2770ca',
            'lfoodis' => 0,
            'lrpdis' => 0,
            'sbgcolor' => '#f7f7f7',
            'subbgcolor' => '#ffffff',
            'itembgcolor' => '#edf5fc',
            'accentcolor' => '#000000',
            'lsfile' => 0,
            'tbgcolor' => '#ffffffb3',
            'tcndis' => 0,
            'stxtcolor' => '#444b52',
            'ttxtcolor' => '#1a1f36',
            'itxtcolor' => '#6c757d',
            'uigradient' => '#2770ca',
        ];

        foreach ($defaults as $key => $value) {
            $currentValue = Tools::settings('richitheme', $key);
            if ($currentValue === null || $currentValue === '') {
                Tools::settingsSet('richitheme', $key, $value);
            }
        }

        Tools::settingsSave();
    }
}