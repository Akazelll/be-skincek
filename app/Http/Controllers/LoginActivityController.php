<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoginActivityResource;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;

class LoginActivityController extends Controller
{
    public function index(Request $request)
    {
        return LoginActivityResource::collection(
            $request->user()->tokens()->latest('last_used_at')->latest()->get()
        );
    }

    public function destroy(Request $request, PersonalAccessToken $personalAccessToken)
    {
        abort_unless($personalAccessToken->tokenable_id === $request->user()->id
            && $personalAccessToken->tokenable_type === $request->user()::class, 404);

        $personalAccessToken->delete();

        return $this->successResponse(null, ['message' => 'Sesi berhasil dicabut']);
    }
}
