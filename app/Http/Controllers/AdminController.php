<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class AdminController extends Controller
{
    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'doctor', 'user'])],
        ]);

        $user->syncRoles($validated['role']);

        activity()
            ->useLog('role_management')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['role' => $validated['role']])
            ->log("Role changed to {$validated['role']}");

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
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    public function showUser(User $user)
    {
        return new UserResource($user->load(['roles', 'doctorVerification']));
    }

    public function activityLog(Request $request)
    {
        $activities = Activity::query()
            ->with('causer')
            ->when($request->input('log_name'), function ($query, $logName) {
                $query->where('log_name', $logName);
            })
            ->when($request->input('causer_id'), function ($query, $causerId) {
                $query->where('causer_id', $causerId);
            })
            ->latest()
            ->paginate($this->perPage($request));

        return ActivityLogResource::collection($activities);
    }
}
