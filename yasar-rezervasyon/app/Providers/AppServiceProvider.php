<?php

namespace App\Providers;

use App\Services\Ldap\Directory;
use App\Services\Ldap\FakeDirectory;
use App\Services\Ldap\NativeDirectory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Directory::class, function ($app) {
            $config = config('ldap');
            if ($config['driver'] === 'fake') {
                if ($app->isProduction()) {
                    throw new RuntimeException('LDAP_DRIVER=fake üretim ortamında kullanılamaz.');
                }

                return new FakeDirectory($config['fake_users']);
            }

            return new NativeDirectory($config);
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.simple');
        Paginator::defaultSimpleView('vendor.pagination.simple');

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Kaba kuvvet denemelerine karşı (ISO 27001 A.8.5).
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $r->input('username')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('app', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
