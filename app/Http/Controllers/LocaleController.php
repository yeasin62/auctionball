<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! Locales::isValid($locale)) {
            return back()->with('error', 'Unsupported locale.');
        }

        // Persist for guest (cookie) + this session
        $request->session()->put('locale', $locale);
        Cookie::queue('locale', $locale, 60 * 24 * 365);   // 1 year

        // Persist on the user row if signed in
        if ($user = $request->user()) {
            $user->update(['locale' => $locale]);
        }

        return back();
    }
}
