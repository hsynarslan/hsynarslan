<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Services\Audit;
use App\Services\UserDirectory;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Takım ve oyuncu yönetimi. Hassas verilere yalnızca takımın antrenörü ve SKS Admin erişir (ISO 27001 A.8.3). */
class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $teams = Team::with('coach')->withCount('activePlayers')
            ->when(! $user->is_sks_admin, fn ($q) => $q->where('coach_id', $user->id))
            ->orderBy('name')->get();

        return view('teams.index', compact('teams'));
    }

    public function show(Request $request, Team $team)
    {
        $this->authorizeTeam($request, $team);
        $team->load(['players' => fn ($q) => $q->with('user')->orderBy('left_on')->orderBy('jersey_no')]);

        return view('teams.show', ['team' => $team, 'warningDays' => Setting::get('health_warning_days')]);
    }

    public function createPlayer(Request $request, Team $team)
    {
        $this->authorizeTeam($request, $team);

        return view('teams.player', ['team' => $team, 'player' => new TeamPlayer]);
    }

    public function storePlayer(Request $request, Team $team, UserDirectory $directory)
    {
        $this->authorizeTeam($request, $team);
        $data = $this->validated($request, true);
        $student = $directory->findOrImport($data['student_number']);
        if (! $student || $student->base_role !== Roles::STUDENT) {
            throw ValidationException::withMessages(['student_number' => __('Bu numarayla kayıtlı bir öğrenci bulunamadı.')]);
        }
        if ($team->activePlayers()->where('user_id', $student->id)->exists()) {
            throw ValidationException::withMessages(['student_number' => __('Bu öğrenci zaten takımda.')]);
        }
        $player = new TeamPlayer(collect($data)->except(['student_number', 'photo'])->all());
        $player->team_id = $team->id;
        $player->user_id = $student->id;
        $player->joined_on ??= today();
        $this->storePhoto($request, $player);
        $player->save();
        Audit::log('player.added', $player, ['team' => $team->id, 'user' => $student->id]);

        return redirect()->route('teams.show', $team)->with('success', __(':name takıma eklendi.', ['name' => $student->name]));
    }

    public function showPlayer(Request $request, Team $team, TeamPlayer $player)
    {
        $this->authorizePlayer($request, $team, $player);
        Audit::log('player.viewed', $player);

        return view('teams.player', compact('team', 'player'));
    }

    public function updatePlayer(Request $request, Team $team, TeamPlayer $player)
    {
        $this->authorizePlayer($request, $team, $player);
        abort_if($player->left_on, 403);
        $data = $this->validated($request, false);
        $oldExpiry = $player->health_report_expires_on;
        $player->fill(collect($data)->except('photo')->all());
        if ($oldExpiry !== $player->health_report_expires_on) {
            $player->health_warned_at = null;
        }
        $this->storePhoto($request, $player);
        $changed = array_keys($player->getDirty());
        $player->save();
        Audit::log('player.updated', $player, ['fields' => $changed]);

        return redirect()->route('teams.show', $team)->with('success', __('Oyuncu bilgileri kaydedildi.'));
    }

    public function removePlayer(Request $request, Team $team, TeamPlayer $player)
    {
        $this->authorizePlayer($request, $team, $player);
        $player->removeFromTeam();
        Audit::log('player.removed_and_purged', $player, ['team' => $team->id]);

        return redirect()->route('teams.show', $team)->with('success', __('Oyuncu takımdan çıkarıldı ve hassas bilgileri silindi.'));
    }

    public function photo(Request $request, Team $team, TeamPlayer $player)
    {
        $this->authorizePlayer($request, $team, $player);
        abort_unless($player->photo_path && Storage::disk('local')->exists($player->photo_path), 404);

        return response()->file(Storage::disk('local')->path($player->photo_path), ['Cache-Control' => 'no-store, private']);
    }

    private function authorizeTeam(Request $request, Team $team): void
    {
        abort_unless($team->canManage($request->user()), 403);
    }

    private function authorizePlayer(Request $request, Team $team, TeamPlayer $player): void
    {
        $this->authorizeTeam($request, $team);
        abort_unless($player->team_id === $team->id, 404);
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'student_number' => [$creating ? 'required' : 'prohibited', 'string', 'max:50'],
            'jersey_no' => ['nullable', 'string', 'max:5'],
            'position' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()-]+$/'],
            'license_no' => ['nullable', 'string', 'max:50'],
            'health_report_date' => ['nullable', 'date'],
            'health_report_expires_on' => ['nullable', 'date', 'after_or_equal:health_report_date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()-]+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'joined_on' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
    }

    private function storePhoto(Request $request, TeamPlayer $player): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }
        if ($player->photo_path) {
            Storage::disk('local')->delete($player->photo_path);
        }
        // Özel diskte, tahmin edilemez adla saklanır; yalnızca yetki kontrollü rota üzerinden sunulur.
        $player->photo_path = $request->file('photo')->store('player-photos', 'local');
    }
}
