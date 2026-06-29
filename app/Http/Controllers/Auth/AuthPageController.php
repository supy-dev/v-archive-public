<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AuthPageController extends Controller
{
    public function login(): View|RedirectResponse
    {
        return $this->redirectIfAuthenticated() ?? view('auth.login');
    }

    public function register(): View|RedirectResponse
    {
        return $this->redirectIfAuthenticated() ?? view('auth.register');
    }

    public function forgotPassword(): View|RedirectResponse
    {
        return $this->redirectIfAuthenticated() ?? view('auth.forgot-password');
    }

    public function resetPassword(): View|RedirectResponse
    {
        return $this->redirectIfAuthenticated() ?? view('auth.reset-password');
    }

    private function redirectIfAuthenticated(): ?RedirectResponse
    {
        return Auth::guard('web')->check() ? redirect()->route('home') : null;
    }
}
