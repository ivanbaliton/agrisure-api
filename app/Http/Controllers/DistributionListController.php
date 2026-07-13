<?php

namespace App\Http\Controllers;

use App\Models\DistributionList;
use App\Models\DistributionItem;
use App\Models\DistributionFarmer;
use App\Models\DistributionAllocation;
use App\Models\InventorySupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistributionListController extends Controller
{
    private function relations(): array
    {
        return [
            'barangay',
            'items.supply',
            'farmers.farmer',
            'allocations.supply',
            'allocations.farmer',
        ];
    }

    public function index()
    {
        return DistributionList::with($this->relations())
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'distribution_event_id' => 'nullable|exists:distribution_events,id',
            'barangay_id' => 'required|exists:barangays,id',
            'items' => 'required|array|min:1',
            'items.*.supply_id' => 'required|exists:inventory_supplies,id',
            'farmer_ids' => 'required|array|min:1',
            'farmer_ids.*' => 'exists:users,id',
            'allocations' => 'required|array|min:1',
            'allocations.*.farmer_id' => 'required|exists:users,id',
            'allocations.*.supply_id' => 'required|exists:inventory_supplies,id',
            'allocations.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            $list = DistributionList::create([
                'distribution_event_id' => $request->distribution_event_id,
                'barangay_id' => $request->barangay_id,
                'status' => 'draft',
            ]);

            $selectedFarmerIds = collect($request->farmer_ids);
            $selectedSupplyIds = collect($request->items)->pluck('supply_id');

            foreach ($request->allocations as $allocation) {
                if (!$selectedFarmerIds->contains($allocation['farmer_id'])) {
                    return response()->json([
                        'message' => 'Allocation contains a farmer not selected in the barangay list.',
                    ], 422);
                }

                if (!$selectedSupplyIds->contains($allocation['supply_id'])) {
                    return response()->json([
                        'message' => 'Allocation contains a supply not selected in the barangay list.',
                    ], 422);
                }
            }

            $allocationsCollection = collect($request->allocations);

            foreach ($request->items as $item) {
                $totalQuantity = $allocationsCollection
                    ->where('supply_id', $item['supply_id'])
                    ->sum('quantity');

                DistributionItem::create([
                    'distribution_list_id' => $list->id,
                    'supply_id' => $item['supply_id'],
                    'quantity' => $totalQuantity,
                ]);
            }

            foreach ($request->farmer_ids as $farmerId) {
                DistributionFarmer::create([
                    'distribution_list_id' => $list->id,
                    'farmer_id' => $farmerId,
                    'claim_status' => 'pending',
                    'received_at' => null,
                ]);
            }

            foreach ($request->allocations as $allocation) {
                DistributionAllocation::create([
                    'distribution_list_id' => $list->id,
                    'farmer_id' => $allocation['farmer_id'],
                    'supply_id' => $allocation['supply_id'],
                    'quantity' => $allocation['quantity'],
                ]);
            }

            return $list->load($this->relations());
        });
    }

    public function publish($id)
    {
        return DB::transaction(function () use ($id) {
            $list = DistributionList::with([
                'items.supply',
                'allocations',
            ])->findOrFail($id);

            if ($list->status !== 'draft') {
                return response()->json([
                    'message' => 'Only draft distribution lists can be published.',
                ], 422);
            }

            // Check stock first for all items before making any modifications
            foreach ($list->items as $item) {
                $supply = InventorySupply::findOrFail($item->supply_id);

                if ($supply->qty_available < $item->quantity) {
                    return response()->json([
                        'message' => "Insufficient stock for {$supply->name}.",
                    ], 422);
                }
            }

            // Deduct overall batch inventory immediately upon publishing
            foreach ($list->items as $item) {
                $supply = InventorySupply::findOrFail($item->supply_id);

                $supply->qty_available -= $item->quantity;
                $supply->qty_distributed += $item->quantity;

                // Update stock threshold statuses
                if ($supply->qty_available <= 0) {
                    $supply->status = 'out';
                } elseif ($supply->qty_available <= $supply->low_threshold) {
                    $supply->status = 'low';
                } else {
                    $supply->status = 'in-stock';
                }

                $supply->save();
            }

            $list->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            return response()->json([
                'message' => 'Distribution published successfully.',
                'list' => $list->fresh($this->relations()),
            ]);
        });
    }

    public function markFarmerReceived(Request $request, $listId, $farmerId)
    {
        $request->validate([
            'received_items' => 'required|array|min:1',
            'received_items.*.supply_id' => 'required|exists:inventory_supplies,id',
            'received_items.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request, $listId, $farmerId) {
            $list = DistributionList::findOrFail($listId);

            if ($list->status !== 'published') {
                return response()->json([
                    'message' => 'Only published distributions can release supplies.',
                ], 422);
            }

            $farmer = DistributionFarmer::where('distribution_list_id', $listId)
                ->where('farmer_id', $farmerId)
                ->firstOrFail();

            $allocatedItems = DistributionAllocation::where('distribution_list_id', $listId)
                ->where('farmer_id', $farmerId)
                ->get()
                ->keyBy('supply_id');

            foreach ($request->received_items as $receivedItem) {
                $supplyId = $receivedItem['supply_id'];
                $receivedQty = (int) $receivedItem['quantity'];

                $allocation = $allocatedItems->get($supplyId);

                if (!$allocation) {
                    return response()->json([
                        'message' => 'This farmer has no allocation for one of the selected supplies.',
                    ], 422);
                }

                if ($receivedQty > $allocation->quantity) {
                    return response()->json([
                        'message' => 'Received quantity cannot exceed allocated quantity.',
                    ], 422);
                }

                // NOTE: Inventory deduction lines removed from here because stock 
                // was already committed and deducted when the list was published.
            }

            $totalAllocated = $allocatedItems->sum('quantity');
            $totalReceived = collect($request->received_items)->sum('quantity');

            $farmer->update([
                'claim_status' => $totalReceived >= $totalAllocated ? 'received' : 'partial',
                'received_at' => now(),
            ]);

            return response()->json([
                'message' => 'Farmer distribution claim updated.',
                'list' => $list->fresh($this->relations()),
            ]);
        });
    }

    public function complete($id)
    {
        $list = DistributionList::findOrFail($id);

        if ($list->status !== 'published') {
            return response()->json([
                'message' => 'Only published distributions can be completed.',
            ], 422);
        }

        $list->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Distribution completed.',
            'list' => $list->fresh($this->relations()),
        ]);
    }

    public function barangayIndex(Request $request)
    {
        $barangayId = $request->user()->barangay_id;

        return DistributionList::with([
            'event',
            'items.supply',
            'farmers.farmer',
            'allocations.supply',
            'allocations.farmer',
        ])
            ->where('barangay_id', $barangayId)
            ->whereIn('status', ['published', 'completed'])
            ->latest()
            ->get();
    }

    public function barangayShow(Request $request, $id)
    {
        $barangayId = $request->user()->barangay_id;

        return DistributionList::with([
            'event',
            'barangay',
            'items.supply',
            'farmers.farmer',
            'allocations.supply',
            'allocations.farmer',
        ])
            ->where('barangay_id', $barangayId)
            ->whereIn('status', ['published', 'completed'])
            ->findOrFail($id);
    }
}