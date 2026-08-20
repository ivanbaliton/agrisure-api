<?php

namespace App\Http\Controllers;

use App\Models\DistributionEvent;
use App\Models\DistributionList;
use App\Models\DistributionItem;
use App\Models\DistributionFarmer;
use App\Models\DistributionAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DistributionEventController extends Controller
{
    // Centralized eager loading array to avoid repeating yourself
    protected function getEventRelations(): array
    {
        return [
            'lists.barangay',
            'lists.items.supply',
            'lists.farmers.farmer',
            'lists.allocations.supply',
            'lists.allocations.farmer',
        ];
    }

    public function index()
    {
        return DistributionEvent::with($this->getEventRelations())
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'distribution_date' => 'required|date',
            'distribution_time' => 'nullable',
            'venue' => 'required|string|max:255',
            'description' => 'nullable|string',
            'barangay_lists' => 'required|array|min:1',
            'barangay_lists.*.barangay_id' => 'required|exists:barangays,id',
            'barangay_lists.*.items' => 'required|array|min:1',
            'barangay_lists.*.items.*.supply_id' => 'required|exists:inventory_supplies,id',
            'barangay_lists.*.farmer_ids' => 'required|array|min:1',
            'barangay_lists.*.farmer_ids.*' => 'exists:users,id',
            'barangay_lists.*.allocations' => 'required|array|min:1',
            'barangay_lists.*.allocations.*.farmer_id' => 'required|exists:users,id',
            'barangay_lists.*.allocations.*.supply_id' => 'required|exists:inventory_supplies,id',
            'barangay_lists.*.allocations.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            // Replaced insecure rand() with a safer unique string sequence
            $event = DistributionEvent::create([
                'reference_no' => 'DE-' . now()->format('Y') . '-' . strtoupper(Str::random(6)),
                'title' => $request->title,
                'distribution_date' => $request->distribution_date,
                'distribution_time' => $request->distribution_time,
                'venue' => $request->venue,
                'description' => $request->description,
                'status' => 'draft',
            ]);

            foreach ($request->barangay_lists as $barangayList) {
                $list = DistributionList::create([
                    'distribution_event_id' => $event->id,
                    'barangay_id' => $barangayList['barangay_id'],
                    'status' => 'draft',
                ]);

                // Create a collection of allocations to safely calculate sums locally in memory
                $allocationsCollection = collect($barangayList['allocations']);

                foreach ($barangayList['items'] as $item) {
                    // Calculate total right here in-memory instead of hitting the DB again
                    $totalQuantity = $allocationsCollection
                        ->where('supply_id', $item['supply_id'])
                        ->sum('quantity');

                    DistributionItem::create([
                        'distribution_list_id' => $list->id,
                        'supply_id' => $item['supply_id'],
                        'quantity' => $totalQuantity,
                        
                    ]);
                }

                foreach ($barangayList['farmer_ids'] as $farmerId) {
                    DistributionFarmer::create([
                        'distribution_list_id' => $list->id,
                        'farmer_id' => $farmerId,
                        'received' => false,
                        'received_at' => null,
                    ]);
                }

                foreach ($barangayList['allocations'] as $allocation) {
                    DistributionAllocation::create([
                        'distribution_list_id' => $list->id,
                        'farmer_id' => $allocation['farmer_id'],
                        'supply_id' => $allocation['supply_id'],
                        'quantity' => $allocation['quantity'],
                    ]);
                }
            }

            return $event->load($this->getEventRelations());
        });
    }

    public function show($id)
    {
        return DistributionEvent::with($this->getEventRelations())->findOrFail($id);
    }

    public function publish($id)
{
    try {
        $event = DB::transaction(function () use ($id) {

            $event = DistributionEvent::with([
                'lists.allocations'
            ])->lockForUpdate()->findOrFail($id);

            // Prevent publishing an event more than once
            if ($event->status !== 'draft') {
                throw new \Exception(
                    'Only draft events can be published.'
                );
            }

            if ($event->lists->isEmpty()) {
                throw new \Exception(
                    'This event has no barangay lists.'
                );
            }

            /*
             * ==========================================
             * 1. Calculate total allocation per supply
             * ==========================================
             */

            $supplyTotals = [];

            foreach ($event->lists as $list) {

                foreach ($list->allocations as $allocation) {

                    $supplyId = $allocation->supply_id;
                    $quantity = (int) $allocation->quantity;

                    if ($quantity <= 0) {
                        continue;
                    }

                    if (!isset($supplyTotals[$supplyId])) {
                        $supplyTotals[$supplyId] = 0;
                    }

                    $supplyTotals[$supplyId] += $quantity;
                }
            }

            if (empty($supplyTotals)) {
                throw new \Exception(
                    'No valid supply allocations were found.'
                );
            }

            /*
             * ==========================================
             * 2. Check inventory availability
             * ==========================================
             */

            foreach ($supplyTotals as $supplyId => $totalQuantity) {

                $supply = \App\Models\InventorySupply::lockForUpdate()
                    ->findOrFail($supplyId);

                if ($supply->qty_available < $totalQuantity) {

                    throw new \Exception(
                        "Insufficient stock for {$supply->name}. " .
                        "Available: {$supply->qty_available}, " .
                        "Required: {$totalQuantity}."
                    );
                }
            }

            /*
             * ==========================================
             * 3. Deduct inventory
             * ==========================================
             */

            foreach ($supplyTotals as $supplyId => $totalQuantity) {

                $supply = \App\Models\InventorySupply::lockForUpdate()
                    ->findOrFail($supplyId);

                // Deduct available stock
                $supply->qty_available -= $totalQuantity;

                // Increase distributed quantity
                $supply->qty_distributed =
                    ($supply->qty_distributed ?? 0) + $totalQuantity;

                // Update stock status
                if ($supply->qty_available <= 0) {

                    $supply->status = 'out';

                } elseif (
                    $supply->qty_available <= $supply->low_threshold
                ) {

                    $supply->status = 'low';

                } else {

                    $supply->status = 'in-stock';
                }

                $supply->save();
            }

            /*
             * ==========================================
             * 4. Publish event
             * ==========================================
             */

            $now = now();

            $event->update([
                'status' => 'published',
                'published_at' => $now,
            ]);

            /*
             * ==========================================
             * 5. Publish barangay lists
             * ==========================================
             */

            $event->lists()->update([
                'status' => 'published',
                'published_at' => $now,
            ]);

            return $event->fresh($this->getEventRelations());
        });

        return response()->json([
            'message' => 'Distribution event published successfully. Inventory updated.',
            'event' => $event,
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'message' => $e->getMessage(),
        ], 422);
    }
}

    public function complete($id)
    {
        $event = DistributionEvent::with('lists')->findOrFail($id);

        if ($event->status !== 'published') {
            return response()->json(['message' => 'Only published events can be completed.'], 422);
        }

        $now = now();
        $event->update(['status' => 'completed', 'completed_at' => $now]);
        $event->lists()->update(['status' => 'completed', 'completed_at' => $now]); // Optimized mass update

        return response()->json([
            'message' => 'Distribution event completed successfully.',
            'event' => $event->fresh($this->getEventRelations()),
        ]);
    }

    public function destroy($id)
{
    try {
        return DB::transaction(function () use ($id) {

            $event = DistributionEvent::with('lists')->findOrFail($id);

            // Only draft events can be deleted
            if ($event->status !== 'draft') {
                return response()->json([
                    'message' => 'Only draft distribution events can be deleted.'
                ], 422);
            }

            // Delete related records first
            foreach ($event->lists as $list) {

                // Delete allocations
                DistributionAllocation::where(
                    'distribution_list_id',
                    $list->id
                )->delete();

                // Delete farmers/recipients
                DistributionFarmer::where(
                    'distribution_list_id',
                    $list->id
                )->delete();

                // Delete supply items
                DistributionItem::where(
                    'distribution_list_id',
                    $list->id
                )->delete();

                // Delete barangay list
                $list->delete();
            }

            // Delete the event
            $event->delete();

            return response()->json([
                'message' => 'Distribution event deleted successfully.'
            ]);
        });

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'Failed to delete distribution event.',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
