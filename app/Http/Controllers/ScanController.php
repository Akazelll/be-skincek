<?php

namespace App\Http\Controllers;

use App\Contracts\SkinPredictionServiceContract;
use App\Enums\ScanMode;
use App\Enums\SeverityLevel;
use App\Http\Requests\StoreScanRequest;
use App\Http\Resources\PredictionHistoryResource;
use App\Mail\ScanCompleteMail;
use App\Models\PredictionFeedback;
use App\Models\PredictionHistory;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\ImageExifStripper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ScanController extends Controller
{
    public function store(StoreScanRequest $request, SkinPredictionServiceContract $service)
    {
        $this->assertEmailVerified($request->user());
        $this->assertProfileCompleteForPrediction($request->user());
        $this->assertWithinFreeScanQuota($request->user());

        return $this->createPrediction($request, $service, ScanMode::UPLOAD);
    }

    public function storeLivecam(StoreScanRequest $request, SkinPredictionServiceContract $service)
    {
        $this->assertEmailVerified($request->user());
        $this->assertProfileCompleteForPrediction($request->user());
        $this->assertWithinFreeScanQuota($request->user());

        return $this->createPrediction($request, $service, ScanMode::LIVECAM);
    }

    public function index(Request $request)
    {
        $histories = QueryBuilder::for($request->user()->predictionHistories()->with('media'))
            ->allowedFilters(
                AllowedFilter::exact('scan_mode'),
                AllowedFilter::exact('predicted_class'),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate($this->perPage($request));

        return PredictionHistoryResource::collection($histories);
    }

    public function show(Request $request, PredictionHistory $predictionHistory)
    {
        abort_unless($predictionHistory->user_id === $request->user()->id, 404);

        return new PredictionHistoryResource($predictionHistory);
    }

    private function assertEmailVerified(User $user): void
    {
        abort_unless($user->hasVerifiedEmail(), 403, 'Verifikasi email terlebih dahulu sebelum menggunakan fitur ini.');
    }

    private function assertWithinFreeScanQuota(User $user): void
    {
        if ($user->hasActiveSubscription()) {
            return;
        }

        $limit = config('services.ml.free_scan_limit', 3);
        $todayCount = $user->predictionHistories()
            ->whereDate('created_at', today())
            ->count();

        abort_unless($todayCount < $limit, 429, "Kamu sudah mencapai {$limit} scan gratis hari ini. Upgrade ke SkinCek Pro untuk scan tanpa batas.");
    }

    private function assertProfileCompleteForPrediction(User $user): void
    {
        if ($user->predictionHistories()->exists()) {
            return;
        }

        abort_unless($user->hasCompletedProfile(), 422, 'Lengkapi profil kamu terlebih dahulu (tanggal lahir dan jenis kelamin) sebelum melakukan prediksi pertama.');
    }

    private function createPrediction(Request $request, SkinPredictionServiceContract $service, ScanMode $mode)
    {
        try {
            $result = $service->predict(
                $request->file('image')->getRealPath(),
                $mode === ScanMode::LIVECAM,
                $request->file('image')->getClientOriginalName(),
            );
        } catch (ConnectionException) {
            return $this->errorResponse('Layanan prediksi sedang sibuk, coba lagi nanti', 502);
        } catch (RequestException $e) {
            $detail = data_get($e->response?->json(), 'detail');

            return $this->errorResponse(
                is_string($detail) && $detail !== '' ? $detail : 'Prediksi gagal, coba lagi nanti',
                $e->response ? $e->response->status() : 502,
            );
        }

        validator($result, [
            'predicted_class' => ['required', 'string'],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'probabilities' => ['required', 'array'],
            'severity_score' => ['required', 'numeric', 'between:0,1'],
            'severity_level' => ['required', Rule::enum(SeverityLevel::class)],
            'model_used' => ['required', 'string'],
        ])->validate();

        $history = DB::transaction(function () use ($request, $result, $mode) {
            $history = $request->user()->predictionHistories()->create([
                'scan_mode' => $mode,
                'predicted_class' => $result['predicted_class'],
                'confidence' => $result['confidence'],
                'probabilities' => $result['probabilities'],
                'severity_score' => (int) round($result['severity_score'] * 100),
                'severity_level' => $result['severity_level'],
                'model_used' => $result['model_used'],
                'raw_response' => $result,
            ]);

            $collection = $mode === ScanMode::LIVECAM ? 'scan-photo-cropped' : 'scan-photo';
            $history->addMedia(ImageExifStripper::strip($request->file('image')))->toMediaCollection($collection);

            return $history;
        });

        $this->notifyScanComplete($request->user(), $history);

        return (new PredictionHistoryResource($history))->response()->setStatusCode(201);
    }

    private function notifyScanComplete(User $user, PredictionHistory $history): void
    {
        app(NotificationService::class)->scanComplete(
            $user,
            $history->predicted_class,
            round($history->confidence * 100),
            $history->uuid,
        );

        $key = 'scan-email-sent:'.$user->id.':'.now()->toDateString();

        if (Cache::has($key)) {
            return;
        }

        Cache::put($key, true, now()->endOfDay());

        Mail::to($user)->send(new ScanCompleteMail($history));
    }

    public function feedback(Request $request, PredictionHistory $predictionHistory)
    {
        abort_unless($predictionHistory->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'is_accurate' => ['required', 'boolean'],
        ]);

        $feedback = PredictionFeedback::updateOrCreate(
            ['prediction_history_id' => $predictionHistory->id, 'user_id' => $request->user()->id],
            ['is_accurate' => $validated['is_accurate']]
        );

        return $this->successResponse([
            'is_accurate' => (bool) $feedback->is_accurate,
        ], ['message' => 'Terima kasih atas masukan Anda']);
    }
}
