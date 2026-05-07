<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\Tenancy\AcceptTenantInvitation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantInvitationController extends Controller
{
    public function accept(Request $request, string $token, AcceptTenantInvitation $acceptTenantInvitation): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect('/vendor/login')
                ->with('status', 'Sign in with the invited email address to accept this invitation.');
        }

        try {
            $acceptTenantInvitation->handle($token, $user);
        } catch (ValidationException $exception) {
            return redirect('/vendor')
                ->withErrors($exception->errors());
        }

        return redirect('/vendor')
            ->with('status', 'Invitation accepted.');
    }
}
