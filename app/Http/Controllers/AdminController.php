<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\DoctorVerificationResource;
use App\Http\Resources\UserResource;
use App\Models\DoctorVerification;
use App\Models\PredictionHistory;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class AdminController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user()->load('roles');

        $lastLogin = $user->tokens()->latest('created_at')->first();

        return $this->successResponse([
            'uuid' => $user->uuid,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name ?? 'admin',
            'avatar_url' => $user->avatarUrl(),
            'email_verified' => $user->hasVerifiedEmail(),
            'account_created_at' => $user->created_at?->toISOString(),

            'last_login' => [
                'at' => $lastLogin?->created_at?->toISOString(),
                'ip_address' => $lastLogin?->ip_address,
                'user_agent' => $lastLogin?->user_agent,
            ],
            'active_sessions' => $user->tokens()->count(),

            'summary' => [
                'total_users' => User::role('user')->count(),
                'total_doctors' => User::role('doctor')->where('ai_bot', false)->count(),
                'pending_doctor_verifications' => DoctorVerification::where('verification_status', VerificationStatus::PENDING)->count(),
            ],
        ]);
    }

    public function dashboard()
    {
        $activeSubs = Subscription::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now()));

        $stats = [
            'total_users' => User::role('user')->count(),
            'total_doctors' => User::role('doctor')->where('ai_bot', false)->count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'total_scans' => PredictionHistory::count(),
            'scans_today' => PredictionHistory::whereDate('created_at', today())->count(),
            'active_pro_subscriptions' => (clone $activeSubs)->count(),
            'monthly_revenue' => (int) (clone $activeSubs)->sum('amount'),
        ];

        return $this->successResponse([
            'stats' => $stats,
            'pending_actions' => [
                'doctor_verifications' => DoctorVerification::where('verification_status', VerificationStatus::PENDING)->count(),
            ],
            'charts' => [
                'scans_last_14_days' => $this->dailyCounts(
                    (new PredictionHistory)->getTable(), now()->subDays(13), today()
                ),
                'registrations_last_14_days' => $this->dailyCounts(
                    (new User)->getTable(), now()->subDays(13), today()
                ),
            ],
            'recent_verifications' => DoctorVerificationResource::collection(
                DoctorVerification::with(['doctor.roles', 'doctor.media'])
                    ->latest()
                    ->limit(5)
                    ->get()
            ),
        ]);
    }

    private function dailyCounts(string $table, Carbon $from, Carbon $to): array
    {
        $rows = DB::table($table)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupByRaw('DATE(created_at)')
            ->pluck('count', 'date');

        $result = [];

        foreach (range(0, 13) as $i) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();

            $result[] = [
                'date' => $key,
                'count' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $result;
    }

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
        $users = User::with(['roles', 'media'])
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

    public function toggleActive(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        activity()
            ->useLog('user_management')
            ->performedOn($user)
            ->causedBy(request()->user())
            ->withProperties(['is_active' => $user->is_active])
            ->log($user->is_active ? 'User activated' : 'User suspended');

        return $this->successResponse(new UserResource($user), [
            'message' => $user->is_active ? 'User berhasil diaktifkan' : 'User berhasil disuspend',
        ]);
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

    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'doctor', 'user'])],
            'is_active' => ['sometimes', 'boolean'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:laki_laki,perempuan'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
            'privacy_consent_at' => now(),
            'gender' => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        activity()
            ->useLog('user_management')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['role' => $validated['role']])
            ->log('User created');

        return $this->successResponse(new UserResource($user->load('roles')), [
            'message' => 'User berhasil dibuat',
        ], 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)->whereNull('deleted_at')],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:laki_laki,perempuan'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
        ]);

        $user->fill([
            'full_name' => $validated['full_name'] ?? $user->full_name,
            'email' => $validated['email'] ?? $user->email,
            'is_active' => $validated['is_active'] ?? $user->is_active,
            'gender' => array_key_exists('gender', $validated) ? $validated['gender'] : $user->gender,
            'date_of_birth' => array_key_exists('date_of_birth', $validated) ? $validated['date_of_birth'] : $user->date_of_birth,
        ]);

        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        activity()
            ->useLog('user_management')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['updated_fields' => array_keys($validated)])
            ->log('User updated');

        return $this->successResponse(new UserResource($user->load('roles')), [
            'message' => 'User berhasil diperbarui',
        ]);
    }

    public function destroyUser(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'Tidak dapat menghapus akun sendiri');

        $userName = $user->full_name;
        $userRole = $user->roles->first()?->name;
        $user->tokens()->delete();
        $user->delete();

        activity()
            ->useLog('user_management')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['full_name' => $userName, 'role' => $userRole])
            ->log('User deleted');

        return $this->successResponse(null, [
            'message' => "User {$userName} berhasil dihapus",
        ]);
    }
}
