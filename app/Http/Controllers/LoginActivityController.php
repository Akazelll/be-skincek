<?php

namespace App\Http\Controllers;

use App\Contracts\IpLocationResolverContract;
use App\Http\Resources\LoginActivityResource;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;

class LoginActivityController extends Controller
{
    public function index(Request $request, IpLocationResolverContract $resolver)
    {
        $tokens = $request->user()->tokens()
            ->select('id', 'uuid', 'user_agent', 'ip_address', 'last_used_at', 'created_at')
            ->latest('last_used_at')
            ->latest()
            ->get();

        return LoginActivityResource::collection(
            $tokens->map(fn ($token) => new LoginActivityResource($token, $resolver->resolve($token->ip_address)))
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
