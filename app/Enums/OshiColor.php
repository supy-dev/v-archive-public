<?php

declare(strict_types=1);

namespace App\Enums;

enum OshiColor: string
{
    case Rose    = 'rose';
    case Pink    = 'pink';
    case Fuchsia = 'fuchsia';
    case Purple  = 'purple';
    case Violet  = 'violet';
    case Blue    = 'blue';
    case Cyan    = 'cyan';
    case Teal    = 'teal';
    case Green   = 'green';
    case Lime    = 'lime';
    case Yellow  = 'yellow';
    case Orange  = 'orange';
    case Red     = 'red';
    case Slate   = 'slate';
    case Gray    = 'gray';

    /** 表示名（日本語） */
    public function label(): string
    {
        return match ($this) {
            self::Rose    => 'ローズ',
            self::Pink    => 'ピンク',
            self::Fuchsia => 'フューシャ',
            self::Purple  => 'パープル',
            self::Violet  => 'バイオレット',
            self::Blue    => 'ブルー',
            self::Cyan    => 'シアン',
            self::Teal    => 'ティール',
            self::Green   => 'グリーン',
            self::Lime    => 'ライム',
            self::Yellow  => 'イエロー',
            self::Orange  => 'オレンジ',
            self::Red     => 'レッド',
            self::Slate   => 'スレート',
            self::Gray    => 'グレー',
        };
    }

    /** Tailwind CSS の背景色クラス */
    public function tailwindBg(): string
    {
        return match ($this) {
            self::Rose    => 'bg-rose-400',
            self::Pink    => 'bg-pink-400',
            self::Fuchsia => 'bg-fuchsia-400',
            self::Purple  => 'bg-purple-400',
            self::Violet  => 'bg-violet-400',
            self::Blue    => 'bg-blue-400',
            self::Cyan    => 'bg-cyan-400',
            self::Teal    => 'bg-teal-400',
            self::Green   => 'bg-green-400',
            self::Lime    => 'bg-lime-400',
            self::Yellow  => 'bg-yellow-400',
            self::Orange  => 'bg-orange-400',
            self::Red     => 'bg-red-400',
            self::Slate   => 'bg-slate-400',
            self::Gray    => 'bg-gray-400',
        };
    }

    /** テキスト色クラス（一覧表示用） */
    public function tailwindRing(): string
    {
        return match ($this) {
            self::Rose    => 'ring-rose-400',
            self::Pink    => 'ring-pink-400',
            self::Fuchsia => 'ring-fuchsia-400',
            self::Purple  => 'ring-purple-400',
            self::Violet  => 'ring-violet-400',
            self::Blue    => 'ring-blue-400',
            self::Cyan    => 'ring-cyan-400',
            self::Teal    => 'ring-teal-400',
            self::Green   => 'ring-green-400',
            self::Lime    => 'ring-lime-400',
            self::Yellow  => 'ring-yellow-400',
            self::Orange  => 'ring-orange-400',
            self::Red     => 'ring-red-400',
            self::Slate   => 'ring-slate-400',
            self::Gray    => 'ring-gray-400',
        };
    }

    /** 推し識別色のCSSカスタムプロパティを適用するクラス */
    public function cssClass(): string
    {
        return 'oshi-color-'.$this->value;
    }
}
