<?php

namespace App\Http\Responses;

use App\Support\AdminHome;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $home = AdminHome::path($request->user());

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false, 'redirect' => $home], 201)
            : redirect()->intended($home);
    }
}
