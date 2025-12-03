<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['rol', 'sucursal'])->get();
        return response()->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'telefono' => 'nullable|string|max:20',
            'usuario' => 'required|string|max:50|unique:users,usuario',
            'rol_id' => 'required|exists:roles,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'estado' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'telefono' => $request->telefono,
            'usuario' => $request->usuario,
            'rol_id' => $request->rol_id,
            'sucursal_id' => $request->sucursal_id,
            'estado' => $request->estado ?? true,
        ]);

        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        $user->load(['rol', 'sucursal']);
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'telefono' => 'nullable|string|max:20',
            'usuario' => 'required|string|max:50|unique:users,usuario,' . $user->id,
            'rol_id' => 'required|exists:roles,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'estado' => 'boolean',
        ]);

        $data = $request->except('password');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(null, 204);
    }
}
