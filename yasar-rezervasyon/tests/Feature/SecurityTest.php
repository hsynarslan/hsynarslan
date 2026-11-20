<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Services\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_login_with_ldap_and_failure_is_generic_and_audited(): void
    {
        $this->post('/login', ['username' => 'ogrenci', 'password' => 'yanlis'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.failed')->exists());

        $this->post('/login', ['username' => 'ogrenci', 'password' => 'test'])->assertRedirect();
        $this->assertAuthenticated();
        $this->assertTrue(AuditLog::where('action', 'auth.login')->exists());
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/login', ['username' => 'ogrenci', 'password' => 'x'.$i]);
        }
        $this->post('/login', ['username' => 'ogrenci', 'password' => 'test'])->assertStatus(429);
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $u = $this->user('ogrenci');
        $u->is_active = false;
        $u->save();
        $this->actingAs($u)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_security_headers_are_present(): void
    {
        $r = $this->get('/login');
        $r->assertHeader('X-Frame-Options', 'DENY');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("script-src 'self'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('unsafe-inline', $r->headers->get('Content-Security-Policy'));
    }

    public function test_kvkk_consent_is_required(): void
    {
        $this->actingAs($this->user('ogrenci'))->get('/')->assertRedirect(route('kvkk.show'));
        $this->post(route('kvkk.accept'), ['accept' => '1'])->assertRedirect();
        $this->get('/')->assertOk();
    }

    public function test_mass_assignment_cannot_grant_admin(): void
    {
        $u = $this->user('ogrenci');
        $u->fill(['is_sks_admin' => true, 'is_coach' => true]);
        $this->assertFalse($u->is_sks_admin);
        $this->assertFalse($u->is_coach);
    }

    public function test_player_health_data_is_encrypted_access_controlled_and_purged(): void
    {
        $team = Team::first();
        $this->loginAs($this->user('antrenor'))->post(route('teams.players.store', $team), [
            'student_number' => '20231046', 'jersey_no' => '7', 'phone' => '0555 111 22 33',
            'health_report_date' => '2026-01-01', 'health_report_expires_on' => '2026-12-15', 'emergency_contact_name' => 'Anne',
        ])->assertRedirect(route('teams.show', $team));

        $player = TeamPlayer::first();
        $raw = DB::table('team_players')->first();
        $this->assertStringNotContainsString('0555', $raw->phone);
        $this->assertStringNotContainsString('2026-12-15', $raw->health_report_expires_on);
        $this->assertSame('0555 111 22 33', $player->phone);

        // Personel olmayan öğrenci eklenemez
        $this->post(route('teams.players.store', $team), ['student_number' => 'P-2001'])->assertSessionHasErrors('student_number');

        // Görüntüleme loglanır, başka antrenör erişemez
        $this->get(route('teams.players.show', [$team, $player]))->assertOk();
        $this->assertTrue(AuditLog::where('action', 'player.viewed')->exists());
        $other = $this->user('akademik');
        $other->is_coach = true;
        $other->save();
        $this->loginAs($other)->get(route('teams.players.show', [$team, $player]))->assertForbidden();

        // Takımdan çıkınca hassas veri silinir, kayıt kalır
        $this->loginAs($this->user('antrenor'))->delete(route('teams.players.remove', [$team, $player]))->assertRedirect();
        $player->refresh();
        $this->assertNull($player->phone);
        $this->assertNull($player->health_report_expires_on);
        $this->assertNull($player->emergency_contact_name);
        $this->assertSame('7', $player->jersey_no);
        $this->assertNotNull($player->left_on);
    }

    public function test_health_expiry_warning_is_sent_once(): void
    {
        $team = Team::first();
        TeamPlayer::create(['team_id' => $team->id, 'user_id' => $this->user('ogrenci')->id, 'joined_on' => today(), 'health_report_expires_on' => '2026-12-10']);
        $this->artisan('players:health-warnings')->expectsOutput('1 uyarı');
        $this->artisan('players:health-warnings')->expectsOutput('0 uyarı');
    }

    public function test_audit_log_chain_detects_tampering(): void
    {
        Audit::log('test.one');
        Audit::log('test.two', null, ['phone' => '0555', 'x' => 1]);
        $this->assertNull(Audit::verify());
        $this->assertSame('***', AuditLog::where('action', 'test.two')->first()->data['phone']);

        // Model üzerinden değişiklik engellenir
        $this->expectException(\LogicException::class);
        try {
            AuditLog::first()->update(['action' => 'hack']);
        } finally {
            // Doğrudan SQL ile değişiklik zincir doğrulamasında yakalanır
            $id = AuditLog::where('action', 'test.one')->value('id');
            DB::table('audit_logs')->where('id', $id)->update(['action' => 'hack']);
            $this->assertSame($id, Audit::verify());
        }
    }

    public function test_retention_anonymizes_old_reservations(): void
    {
        $r = $this->book($this->user('ogrenci'));
        Carbon::setTestNow(now()->addYears(5)->addDays(3));
        $this->artisan('retention:purge')->assertSuccessful();
        $r->refresh();
        $this->assertNull($r->user_id);
        $this->assertNotNull($r->anonymized_at);
    }
}
