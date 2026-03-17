<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasPagination;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Exports\UsuariosExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    use HasPagination;

    public function index(Request $request)
    {
        $query = User::with(['rol', 'sucursal']);

        $searchableFields = [
            'id',
            'name',
            'email',
            'usuario',
            'telefono',
            'rol.nombre',
            'sucursal.nombre'
        ];

        $query = $this->applySearch($query, $request, $searchableFields);
        $query = $this->applySorting($query, $request, ['id', 'name', 'email', 'created_at'], 'id', 'desc');

        return $this->paginateResponse($query, $request, 15, 100);
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

    /**
     * Actualizar perfil del usuario autenticado (nombre, email, teléfono, avatar).
     * Solo permite actualizar los campos propios del perfil.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'telefono' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->telefono = $request->input('telefono');

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if ($file->isValid()) {
                if ($user->avatar) {
                    Storage::disk('public')->delete('avatars/' . $user->avatar);
                }
                $filename = 'user_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('avatars', $filename, 'public');
                $user->avatar = $filename;
            }
        }

        $user->save();
        $user->load(['rol', 'sucursal']);

        return response()->json($user);
    }

    /**
     * Sirve la imagen de avatar de un usuario (ruta pública).
     */
    public function serveAvatar(string $filename)
    {
        $filename = basename($filename);
        if (empty($filename)) {
            return response()->json(['error' => 'Nombre de archivo no válido'], 400);
        }

        $path = 'avatars/' . $filename;
        if (Storage::disk('public')->exists($path)) {
            $fullPath = Storage::disk('public')->path($path);
            $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
            return response()->file($fullPath, ['Content-Type' => $mimeType]);
        }

        return response()->json(['error' => 'Imagen no encontrada'], 404);
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

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if ($file->isValid()) {
                if ($user->avatar) {
                    Storage::disk('public')->delete('avatars/' . $user->avatar);
                }
                $filename = 'user_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('avatars', $filename, 'public');
                $data['avatar'] = $filename;
            }
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(null, 204);
    }

    /**
     * Toggle user status (active/inactive)
     */
    public function toggleStatus(User $user)
    {
        $user->estado = !$user->estado;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->estado ? 'Usuario activado' : 'Usuario desactivado',
            'data' => $user->load(['rol', 'sucursal'])
        ]);
    }
    /**
     * Exporta usuarios a Excel
     */
    public function exportExcel()
    {
        return Excel::download(new UsuariosExport, 'usuarios.xlsx');
    }

    /**
     * Exporta usuarios a PDF
     */
    public function exportPDF()
    {
        $users = User::with('rol')->get();
        $pdf = Pdf::loadView('pdf.usuarios', compact('users'));
        return $pdf->download('usuarios.pdf');
    }
}
