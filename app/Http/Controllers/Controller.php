<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Objects from another tenant answer 404 (not 403) so their ids are not confirmed to exist.
     */
    protected function ensureTenant(Model $model): void
    {
        abort_unless($model->business_id === app('currentBusiness')->id, 404);
    }
}
