<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
            'bio' => ['nullable', 'string'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($data['tipo'] === 'prestador') {
            $user->perfilPrestador()->updateOrCreate(
                ['user_id' => $user->id],
                ['bio' => $data['bio'] ?? null],
            );
        } else {
            $user->perfilCliente()->firstOrCreate(['user_id' => $user->id]);
        }

        return $user->load(['perfilCliente', 'perfilPrestador']);
    }
}
