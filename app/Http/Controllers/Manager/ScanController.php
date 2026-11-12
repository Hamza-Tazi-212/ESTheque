<?php

namespace App\Http\Controllers\Manager;

use App\Exceptions\CirculationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CirculationRequest;
use App\Http\Requests\IdentifyQrRequest;
use App\Http\Requests\RecordScanRequest;
use App\Services\CirculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(): View
    {
        return view('manager.scan');
    }

    public function identify(IdentifyQrRequest $request, CirculationService $service): JsonResponse
    {
        try {
            return response()->json($service->identify($request->validated('qr_code')));
        } catch (CirculationException $error) {
            return response()->json($error->body(), $error->status);
        }
    }

    public function store(RecordScanRequest $request, CirculationService $service): JsonResponse
    {
        $result = $service->scan($request->user()->id, $request->validated('request_id'), $request->validated('qr_codes'));

        return response()->json($result['body'], $result['status']);
    }

    public function cancel(CirculationRequest $request, int $loan, CirculationService $service): JsonResponse
    {
        $result = $service->cancel($request->user()->id, $request->validated('request_id'), $loan);

        return response()->json($result['body'], $result['status']);
    }
}
