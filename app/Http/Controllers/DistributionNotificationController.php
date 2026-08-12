<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotifyFarmersRequest;
use App\Models\DistributionEvent;
use App\Models\User;
use App\Services\DistributionNotificationService;
use Illuminate\Http\JsonResponse;

class DistributionNotificationController extends Controller
{
    protected $notificationService;

    // Inject the service via the constructor
    public function __construct(DistributionNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send multi-channel notifications via the dedicated service layer.
     */
    public function __invoke(NotifyFarmersRequest $request, DistributionEvent $event): JsonResponse
    {
        $validated = $request->validated();
        
        $farmers = User::whereIn('id', $validated['farmer_ids'])->get();

        // Delegate execution to the notification service
        $summary = $this->notificationService->notifyFarmers(
            $event, 
            $farmers, 
            $validated['channels']
        );

        return response()->json([
            'message' => 'Notification dispatch completed successfully.',
            'summary' => $summary
        ], 200);
    }
}