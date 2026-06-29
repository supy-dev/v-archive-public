<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Video\ImportVideoAction;
use App\Services\YouTube\YouTubeApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * YouTube 動画 URL からの単体取り込みコントローラー。
 *
 * 憲法 I: 検証 → Action 呼び出し → Response のみ。
 * 憲法 III: ユーザー識別はサーバー側の Auth::user() から取得。
 */
class ImportVideoController extends Controller
{
    public function create(): View
    {
        return view('videos.import');
    }

    public function store(Request $request, ImportVideoAction $action): RedirectResponse
    {
        $request->validate([
            'video_url' => ['required', 'string', 'max:500'],
        ]);

        /** @var \App\Models\Profile $profile */
        $profile = Auth::user();

        try {
            $watchItem = $action->execute($profile, (string) $request->input('video_url'));
        } catch (ValidationException $e) {
            return redirect()->route('videos.import.create')
                ->withErrors($e->errors())
                ->withInput();
        } catch (YouTubeApiException) {
            return redirect()->route('videos.import.create')
                ->withErrors(['video_url' => 'YouTube API エラーが発生しました。しばらく後に再度お試しください。'])
                ->withInput();
        }

        return redirect()->route('archives.show', $watchItem)
            ->with('success', '動画を取り込みました。');
    }
}
