<?php

namespace App\Http\Controllers;

use App\Enums\DevicePlatform;
use App\Http\Resources\DeviceTokenResource;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
        ]);

        $token = $request->user()->deviceTokens()->updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            ['platform' => $validated['platform']],
        );

        return (new DeviceTokenResource($token))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, DeviceToken $deviceToken)
    {
        abort_unless($deviceToken->user_id === $request->user()->id, 404);

        $deviceToken->delete();

        return $this->successResponse(null, ['message' => 'Perangkat berhasil dihapus']);
    }
}
