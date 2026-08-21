<?php

namespace App\Http\Controllers\Locale;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * @var list<string>
     */
    private const SUPPORTED = ['en', 'mm', 'zh'];

    public function __invoke(Request $request, string $lang): RedirectResponse
    {
        if (! in_array($lang, self::SUPPORTED, true)) {
            abort(404);
        }

        $request->session()->put('locale', $lang);

        return back();
    }
}
