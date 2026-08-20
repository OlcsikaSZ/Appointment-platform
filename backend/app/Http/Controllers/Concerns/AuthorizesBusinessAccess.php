<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Business;
use Illuminate\Http\Request;

trait AuthorizesBusinessAccess
{
    protected function authorizeBusiness(Request $request, Business $business): void
    {
        $this->authorizeBusinessId($request, (int) $business->id);
    }

    protected function authorizeBusinessId(Request $request, ?int $businessId): void
    {
        abort_unless(
            $businessId !== null
            && (int) $request->user()?->business_id === $businessId,
            403,
            'Ehhez a vállalkozáshoz nincs jogosultságod.'
        );
    }
}
