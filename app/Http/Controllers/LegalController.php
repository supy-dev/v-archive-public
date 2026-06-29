<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * 法的ページ（プライバシーポリシー・利用規約）のコントローラー。
 *
 * 認証不要。View 返却のみ（憲法 I）。
 */
class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacy');
    }

    public function terms(): View
    {
        return view('legal.terms');
    }
}
