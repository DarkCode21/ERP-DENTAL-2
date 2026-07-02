<?php

namespace FacturaScripts\Plugins\Dental\Lib;

use FacturaScripts\Core\Model\User;

class DentalPermissions
{
    private static $dentalPages = [
        'PanelDental',
        'ListEspecialidad', 'EditEspecialidad',
        'ListGabinete', 'EditGabinete',
        'ListEspecialista', 'EditEspecialista',
        'Paciente',
        'CalendarDental',
        'ListTratamientoPaciente', 'EditTratamientoPaciente', 'EditTratamientoPacienteFromPaciente',
        'Odontograma',
    ];

    private static $clinicalPages = [
        'EditTratamientoPaciente', 'EditTratamientoPacienteFromPaciente',
        'Odontograma',
    ];

    private static $adminPages = [
        'ListEspecialidad', 'EditEspecialidad',
        'ListGabinete', 'EditGabinete',
        'ListEspecialista', 'EditEspecialista',
    ];

    public static function canAccessDentalModule(User $user): bool
    {
        if ($user->admin) {
            return true;
        }

        foreach (self::$dentalPages as $page) {
            if ($user->can($page)) {
                return true;
            }
        }

        return false;
    }

    public static function canAccessPage(User $user, string $pageName): bool
    {
        if ($user->admin) {
            return true;
        }

        return $user->can($pageName);
    }

    public static function isDentalPage(string $pageName): bool
    {
        return in_array($pageName, self::$dentalPages);
    }

    public static function isClinicalPage(string $pageName): bool
    {
        return in_array($pageName, self::$clinicalPages);
    }

    public static function isAdminPage(string $pageName): bool
    {
        return in_array($pageName, self::$adminPages);
    }

    public static function hasClinicalAccess(User $user): bool
    {
        if ($user->admin) {
            return true;
        }

        foreach (self::$clinicalPages as $page) {
            if ($user->can($page)) {
                return true;
            }
        }

        return false;
    }

    public static function hasAdminAccess(User $user): bool
    {
        if ($user->admin) {
            return true;
        }

        foreach (self::$adminPages as $page) {
            if ($user->can($page)) {
                return true;
            }
        }

        return false;
    }
}
