<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return $request->user()->load(['perfilCliente', 'perfilPrestador']);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'tipo' => ['required', Rule::in(['cliente', 'prestador'])],
            'bio' => ['nullable', 'string', 'max:2000'],
            'titulo' => ['nullable', 'string', 'max:255'],
            // Só http(s): o link vira um <a href> no perfil público, e `data:` ou outros esquemas não são seguros.
            'link_externo' => ['nullable', 'url:http,https', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'cidade' => $data['cidade'] ?? null,
            'uf' => isset($data['uf']) ? strtoupper($data['uf']) : null,
            'telefone' => $data['telefone'] ?? null,
        ];

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $userData['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($userData);

        if ($data['tipo'] === 'prestador') {
            $user->perfilPrestador()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bio' => $data['bio'] ?? null,
                    'titulo' => $data['titulo'] ?? null,
                    'link_externo' => $data['link_externo'] ?? null,
                ],
            );
        } else {
            $user->perfilCliente()->firstOrCreate(['user_id' => $user->id]);
        }

        return $user->load(['perfilCliente', 'perfilPrestador']);
    }
}
