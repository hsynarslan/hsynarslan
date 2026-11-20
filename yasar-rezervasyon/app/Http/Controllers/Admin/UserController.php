<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sanction;
use App\Models\User;
use App\Services\Audit;
use App\Services\Notifier;
use App\Services\SanctionService;
use App\Services\UserDirectory;
use Illuminate\Http\Request;

/** Kullanıcı ve yetki yönetimi (ISO 27001 A.5.18 Erişim hakları, A.8.2 Ayrıcalıklı erişim). */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('filter');
        $users = User::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('number', 'like', "%$q%")->orWhere('username', 'like', "%$q%")))
            ->when($filter === 'privileged', fn ($w) => $w->where(fn ($x) => $x->where('is_coach', true)->orWhere('is_sks_admin', true)->orWhere('is_attendant', true)))
            ->when($filter === 'sanctioned', fn ($w) => $w->whereHas('sanctions', fn ($s) => $s->active()))
            ->with(['sanctions' => fn ($s) => $s->active()])
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.users.index', compact('users', 'q', 'filter'));
    }

    public function import(Request $request, UserDirectory $directory)
    {
        $data = $request->validate(['number' => ['required', 'string', 'max:100']]);
        $user = $directory->findOrImport($data['number']);
        if (! $user) {
            return back()->withErrors(['number' => __('LDAP\'ta bulunamadı.')]);
        }
        Audit::log('user.imported', $user);

        return redirect()->route('admin.users.index', ['q' => $user->number ?? $user->username])->with('success', __(':name sisteme eklendi.', ['name' => $user->name]));
    }

    public function update(Request $request, User $user)
    {
        if ($user->is($request->user()) && ! $request->boolean('is_sks_admin')) {
            return back()->withErrors(['user' => __('Kendi SKS Admin yetkinizi kaldıramazsınız.')]);
        }
        $before = $user->only('is_coach', 'is_sks_admin', 'is_attendant', 'is_active');
        foreach (['is_coach', 'is_sks_admin', 'is_attendant', 'is_active'] as $flag) {
            $user->{$flag} = $request->boolean($flag);
        }
        $user->save();
        Audit::log('user.permissions_changed', $user, ['before' => $before, 'after' => $user->only(array_keys($before))]);

        return back()->with('success', __(':name için yetkiler kaydedildi.', ['name' => $user->name]));
    }

    public function sanction(Request $request, User $user)
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365'], 'reason' => ['required', 'string', 'max:500']]);
        $s = Sanction::create(['user_id' => $user->id, 'reason' => $data['reason'], 'starts_at' => now(),
            'ends_at' => now()->addDays($data['days']), 'is_automatic' => false, 'created_by' => $request->user()->id]);
        Audit::log('sanction.manual', $s, $data + ['user' => $user->id]);
        app(Notifier::class)->sanctioned($s->load('user'));

        return back()->with('success', __('Yaptırım uygulandı.'));
    }

    public function liftSanction(Request $request, Sanction $sanction, SanctionService $service)
    {
        $service->lift($sanction, $request->user());

        return back()->with('success', __('Yaptırım kaldırıldı.'));
    }
}
