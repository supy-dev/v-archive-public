<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Oshi\CreateOshiAction;
use App\Actions\Oshi\DeleteOshiAction;
use App\Actions\Oshi\UpdateOshiAction;
use App\Enums\OshiColor;
use App\Http\Requests\StoreOshiRequest;
use App\Http\Requests\UpdateOshiRequest;
use App\Models\Oshi;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 推しリソースのコントローラー。
 * 憲法 I: Controller は「入力検証 → Action 呼び出し → Response 返却」に限定。
 */
class OshiController extends Controller
{
    /** 自分の推し一覧 */
    public function index(): View
    {
        $oshis = Auth::user()
            ->oshis()
            ->with([
                'userChannels' => fn ($q) => $q->where('is_main', true)->with('youtubeChannel'),
            ])
            ->withCount('userChannels')
            ->latest()
            ->get();

        return view('oshis.index', compact('oshis'));
    }

    /** 推し作成フォーム */
    public function create(): View
    {
        $colors = OshiColor::cases();

        return view('oshis.create', compact('colors'));
    }

    /** 推しを作成する */
    public function store(StoreOshiRequest $request, CreateOshiAction $action): RedirectResponse
    {
        /** @var Profile $user */
        $user = Auth::user();
        $oshi = $action->execute($user, $request);

        return redirect()->route('oshis.show', $oshi);
    }

    /** 推し詳細 */
    public function show(Oshi $oshi): View
    {
        $this->authorize('view', $oshi);

        $oshi->load([
            'userChannels.youtubeChannel' => fn ($query) => $query->withCount([
                'youtubeVideos as unavailable_videos_count' => fn ($videoQuery) => $videoQuery
                    ->where('is_available', false),
            ]),
        ]);

        $mainUserChannel = $oshi->userChannels->firstWhere('is_main', true)
            ?? $oshi->userChannels->first();
        $syncEnabledCount = $oshi->userChannels->where('sync_enabled', true)->count();

        return view('oshis.show', compact('oshi', 'mainUserChannel', 'syncEnabledCount'));
    }

    /** 推し編集フォーム */
    public function edit(Oshi $oshi): View
    {
        $this->authorize('update', $oshi);

        $colors = OshiColor::cases();

        return view('oshis.edit', compact('oshi', 'colors'));
    }

    /** 推しを更新する */
    public function update(UpdateOshiRequest $request, Oshi $oshi, UpdateOshiAction $action): RedirectResponse
    {
        $this->authorize('update', $oshi);
        $action->execute($oshi, $request);

        return redirect()->route('oshis.show', $oshi);
    }

    /** 推しを削除する */
    public function destroy(Oshi $oshi, DeleteOshiAction $action): RedirectResponse
    {
        $this->authorize('delete', $oshi);
        $action->execute($oshi);

        return redirect()->route('oshis.index');
    }
}
