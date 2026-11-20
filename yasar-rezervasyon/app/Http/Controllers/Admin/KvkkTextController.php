<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KvkkText;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;

class KvkkTextController extends Controller
{
    public function index()
    {
        return view('admin.kvkk', [
            'texts' => KvkkText::withCount('consents')->orderByDesc('published_at')->get(),
            'current' => KvkkText::current(),
            'userCount' => User::where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:20', 'unique:kvkk_texts,version'],
            'body_tr' => ['required', 'string', 'max:50000'],
            'body_en' => ['required', 'string', 'max:50000'],
        ]);
        $text = KvkkText::create($data + ['published_at' => now(), 'published_by' => $request->user()->id]);
        Audit::log('kvkk.published', $text, ['version' => $text->version]);

        return back()->with('success', __('Yeni KVKK metni yayımlandı. Kullanıcılardan bir sonraki işlemlerinde onay istenecek.'));
    }
}
