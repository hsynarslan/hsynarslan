<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\Ldap\Directory;
use App\Services\UserDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request, Directory $directory, UserDirectory $users)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $username = mb_strtolower(trim($data['username']));

        try {
            $entry = $directory->authenticate($username, $data['password']);
        } catch (Throwable $e) {
            Log::error('LDAP hatası', ['error' => $e->getMessage()]);

            return back()->withInput($request->only('username'))
                ->withErrors(['username' => __('Kimlik doğrulama servisine şu anda ulaşılamıyor. Lütfen daha sonra tekrar deneyin.')]);
        }

        if (! $entry) {
            Audit::log('auth.failed', null, ['username' => $username]);

            return back()->withInput($request->only('username'))->withErrors(['username' => __('Kullanıcı adı veya şifre hatalı.')]);
        }

        $user = $users->sync($entry);
        if (! $user->is_active) {
            Audit::log('auth.denied_inactive', $user, [], $user->id);

            return back()->withErrors(['username' => __('Hesabınız pasif durumda.')]);
        }

        Auth::login($user);
        $request->session()->regenerate(); // Oturum sabitleme saldırısına karşı
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('auth.login', $user);

        return redirect()->intended(route('calendar'));
    }

    public function logout(Request $request)
    {
        Audit::log('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function locale(Request $request)
    {
        $locale = $request->validate(['locale' => ['required', 'in:tr,en']])['locale'];
        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
