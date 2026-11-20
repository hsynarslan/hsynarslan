<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamAdminController extends Controller
{
    public function index()
    {
        return view('admin.teams.index', [
            'teams' => Team::with('coach')->withCount('activePlayers')->orderBy('name')->get(),
            'coaches' => User::where('is_coach', true)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $team = Team::create($this->validated($request) + ['is_active' => true]);
        Audit::log('team.created', $team, $team->only('name', 'sport', 'coach_id'));

        return back()->with('success', __('Takım oluşturuldu.'));
    }

    public function update(Request $request, Team $team)
    {
        $team->fill($this->validated($request));
        $team->is_active = $request->boolean('is_active');
        $changes = $team->getDirty();
        $team->save();
        Audit::log('team.updated', $team, $changes);

        return back()->with('success', __('Takım kaydedildi.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sport' => ['nullable', 'string', 'max:100'],
            'coach_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_coach', true)],
        ]);
    }
}
