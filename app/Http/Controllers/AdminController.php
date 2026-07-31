<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'doctor', 'user'])],
        ]);

        $user->syncRoles($validated['role']);

        return $this->successResponse(new UserResource($user), [
            'message' => "Role berhasil diubah menjadi {$validated['role']}",
        ]);
    }

    public function listUsers(Request $request)
    {
        $users = User::with('roles')
            ->when($request->input('role'), function ($query, $role) {
                $query->role($role);
            })
            ->latest()
            ->paginate(15);

        return UserResource::collection($users);
    }
}
