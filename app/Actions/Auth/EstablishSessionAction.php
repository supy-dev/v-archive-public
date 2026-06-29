<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Profile;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;

/**
 * 検証済み profiles に対して Laravel セッションを確立し、セッション固定攻撃を
 * 防ぐためにセッションIDを再生成する。
 */
class EstablishSessionAction
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function execute(Profile $profile, Request $request): void
    {
        $this->auth->guard('web')->login($profile);

        $request->session()->regenerate();
    }
}
