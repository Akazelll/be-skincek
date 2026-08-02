<?php

namespace App\Http\Controllers;

use App\Contracts\SkinPredictionServiceContract;
use App\Enums\ScanMode;
use App\Enums\SeverityLevel;
use App\Http\Requests\StoreScanRequest;
use App\Http\Resources\PredictionHistoryResource;
use App\Models\PredictionHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ScanController extends Controller
{
    public function store(StoreScanRequest $request, SkinPredictionServiceContract $service)
    {
        return $this->createPrediction($request, $service, ScanMode::UPLOAD);
    }

    public function storeLivecam(StoreScanRequest $request, SkinPredictionServiceContract $service)
    {
        return $this->createPrediction($request, $service, ScanMode::LIVECAM);
    }

    public function index(Request $request)
    {
        $histories = QueryBuilder::for($request->user()->predictionHistories())
            ->allowedFilters(
                AllowedFilter::exact('scan_mode'),
                AllowedFilter::exact('predicted_class'),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate();

        return PredictionHistoryResource::collection($histories);
    }

    public function show(Request $request, PredictionHistory $predictionHistory)
    {
        abort_unless($predictionHistory->user_id === $request->user()->id, 404);

        return new PredictionHistoryResource($predictionHistory);
    }

    private function createPrediction(Request $request, SkinPredictionServiceContract $service, ScanMode $mode)
    {
        $result = $service->predict($request->file('image')->getRealPath(), $mode === ScanMode::LIVECAM);
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
            $history->addMedia($request->file('image'))->toMediaCollection('image');

            return $history;
        });

        return (new PredictionHistoryResource($history))->response()->setStatusCode(201);
    }
}
