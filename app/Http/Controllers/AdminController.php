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
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class AdminController extends Controller
{
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
}
