<?php

namespace App\Policies;

class DisciplinePolicy extends SnipePermissionsPolicy
{
    protected function columnName()
    {
        return 'disciplines';
    }
}
