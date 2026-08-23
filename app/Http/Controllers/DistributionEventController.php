<?php

namespace App\Http\Controllers;

use App\Models\DistributionEvent;
use App\Models\DistributionList;
use App\Models\DistributionItem;
use App\Models\DistributionFarmer;
use App\Models\DistributionAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DistributionEventController extends Controller
{
    // Centralized eager loading
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

    /**
     * Display all distribution events.
     */
    public function index()
    {
        return DistributionEvent::with($this->getEventRelations())
            ->latest()
            ->get();
    }

    /**
     * Create a new distribution event.
     *
     * Flow:
     * 1. Upload letter
     * 2. Enter event details
     * 3. Select multiple barangays
     * 4. Select farmers
     * 5. Set supplies and allocations
     * 6. Save as draft
     */
    public function store(Request $request)
    {
        $request->validate([
            /*
             * ==========================================
             * LETTER
             * ==========================================
             */
            'letter_image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            /*
             * ==========================================
             * EVENT DETAILS
             * ==========================================
             */
            'title' => 'required|string|max:255',
            'distribution_date' => 'required|date',
            'distribution_time' => 'nullable',
            'venue' => 'required|string|max:255',
            'description' => 'nullable|string',

            /*
             * ==========================================
             * BARANGAY LISTS
             * ==========================================
             */
            'barangay_lists' => 'required|array|min:1',

            'barangay_lists.*.barangay_id' =>
                'required|exists:barangays,id',

            /*
             * ==========================================
             * SUPPLIES
             * ==========================================
             */
            'barangay_lists.*.items' =>
                'required|array|min:1',

            'barangay_lists.*.items.*.supply_id' =>
                'required|exists:inventory_supplies,id',

            /*
             * ==========================================
             * FARMERS
             * ==========================================
             */
            'barangay_lists.*.farmer_ids' =>
                'required|array|min:1',

            'barangay_lists.*.farmer_ids.*' =>
                'required|exists:users,id',

            /*
             * ==========================================
             * ALLOCATIONS
             * ==========================================
             */
            'barangay_lists.*.allocations' =>
                'required|array|min:1',

            'barangay_lists.*.allocations.*.farmer_id' =>
                'required|exists:users,id',

            'barangay_lists.*.allocations.*.supply_id' =>
                'required|exists:inventory_supplies,id',

            'barangay_lists.*.allocations.*.quantity' =>
                'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {

            /*
             * ==========================================
             * 1. UPLOAD LETTER IMAGE
             * ==========================================
             */

            $letterPath = $request->file('letter_image')
                ->store('distribution-letters', 'public');


            /*
             * ==========================================
             * 2. CREATE DISTRIBUTION EVENT
             * ==========================================
             */

            $event = DistributionEvent::create([
                'reference_no' =>
                    'DE-' .
                    now()->format('Y') .
                    '-' .
                    strtoupper(Str::random(6)),

                'letter_image_path' => $letterPath,

                'title' => $request->title,

                'distribution_date' =>
                    $request->distribution_date,

                'distribution_time' =>
                    $request->distribution_time,

                'venue' =>
                    $request->venue,

                'description' =>
                    $request->description,

                'status' => 'draft',
            ]);


            /*
             * ==========================================
             * 3. CREATE BARANGAY LISTS
             * ==========================================
             */

            foreach ($request->barangay_lists as $barangayList) {

                $list = DistributionList::create([
                    'distribution_event_id' =>
                        $event->id,

                    'barangay_id' =>
                        $barangayList['barangay_id'],

                    'status' => 'draft',
                ]);


                /*
                 * ==========================================
                 * 4. CREATE SUPPLY ITEMS
                 * ==========================================
                 *
                 * The quantity of each item is calculated
                 * from the farmer allocations.
                 */

                $allocationsCollection =
                    collect($barangayList['allocations']);


                foreach ($barangayList['items'] as $item) {

                    $totalQuantity =
                        $allocationsCollection
                            ->where(
                                'supply_id',
                                $item['supply_id']
                            )
                            ->sum('quantity');

                    DistributionItem::create([
                        'distribution_list_id' =>
                            $list->id,

                        'supply_id' =>
                            $item['supply_id'],

                        'quantity' =>
                            $totalQuantity,
                    ]);
                }


                /*
                 * ==========================================
                 * 5. ADD FARMERS TO BARANGAY LIST
                 * ==========================================
                 */

                foreach (
                    $barangayList['farmer_ids']
                    as $farmerId
                ) {

                    DistributionFarmer::create([
                        'distribution_list_id' =>
                            $list->id,

                        'farmer_id' =>
                            $farmerId,

                        'received' =>
                            false,

                        'received_at' =>
                            null,
                    ]);
                }


                /*
                 * ==========================================
                 * 6. CREATE FARMER ALLOCATIONS
                 * ==========================================
                 */

                foreach (
                    $barangayList['allocations']
                    as $allocation
                ) {

                    DistributionAllocation::create([
                        'distribution_list_id' =>
                            $list->id,

                        'farmer_id' =>
                            $allocation['farmer_id'],

                        'supply_id' =>
                            $allocation['supply_id'],

                        'quantity' =>
                            $allocation['quantity'],
                    ]);
                }
            }


            /*
             * ==========================================
             * 7. RETURN COMPLETE EVENT
             * ==========================================
             */

            return $event->load(
                $this->getEventRelations()
            );
        });
    }

    /**
     * Display a single distribution event.
     */
    public function show($id)
    {
        return DistributionEvent::with(
            $this->getEventRelations()
        )->findOrFail($id);
    }

    /**
     * Publish distribution event.
     *
     * Publishing:
     * 1. Checks all allocations
     * 2. Checks inventory
     * 3. Deducts inventory
     * 4. Publishes the event
     * 5. Publishes all selected barangay lists
     */
    public function publish($id)
    {
        try {

            $event = DB::transaction(function () use ($id) {

                $event = DistributionEvent::with([
                    'lists.allocations'
                ])
                    ->lockForUpdate()
                    ->findOrFail($id);


                /*
                 * ==========================================
                 * 1. CHECK EVENT STATUS
                 * ==========================================
                 */

                if ($event->status !== 'draft') {

                    throw new \Exception(
                        'Only draft events can be published.'
                    );
                }


                /*
                 * ==========================================
                 * 2. CHECK BARANGAY LISTS
                 * ==========================================
                 */

                if ($event->lists->isEmpty()) {

                    throw new \Exception(
                        'This event has no barangay lists.'
                    );
                }


                /*
                 * ==========================================
                 * 3. CALCULATE TOTAL ALLOCATION
                 * ==========================================
                 */

                $supplyTotals = [];

                foreach ($event->lists as $list) {

                    foreach (
                        $list->allocations
                        as $allocation
                    ) {

                        $supplyId =
                            $allocation->supply_id;

                        $quantity =
                            (int) $allocation->quantity;

                        if ($quantity <= 0) {
                            continue;
                        }

                        if (
                            !isset(
                                $supplyTotals[$supplyId]
                            )
                        ) {
                            $supplyTotals[$supplyId] = 0;
                        }

                        $supplyTotals[$supplyId] +=
                            $quantity;
                    }
                }


                if (empty($supplyTotals)) {

                    throw new \Exception(
                        'No valid supply allocations were found.'
                    );
                }


                /*
                 * ==========================================
                 * 4. CHECK INVENTORY
                 * ==========================================
                 */

                foreach (
                    $supplyTotals
                    as $supplyId => $totalQuantity
                ) {

                    $supply =
                        \App\Models\InventorySupply::lockForUpdate()
                            ->findOrFail($supplyId);

                    if (
                        $supply->qty_available
                        < $totalQuantity
                    ) {

                        throw new \Exception(
                            "Insufficient stock for {$supply->name}. " .
                            "Available: {$supply->qty_available}, " .
                            "Required: {$totalQuantity}."
                        );
                    }
                }


                /*
                 * ==========================================
                 * 5. DEDUCT INVENTORY
                 * ==========================================
                 */

                foreach (
                    $supplyTotals
                    as $supplyId => $totalQuantity
                ) {

                    $supply =
                        \App\Models\InventorySupply::lockForUpdate()
                            ->findOrFail($supplyId);

                    $supply->qty_available -=
                        $totalQuantity;

                    $supply->qty_distributed =
                        ($supply->qty_distributed ?? 0)
                        + $totalQuantity;


                    /*
                     * Update inventory status
                     */

                    if (
                        $supply->qty_available <= 0
                    ) {

                        $supply->status = 'out';

                    } elseif (
                        $supply->qty_available
                        <= $supply->low_threshold
                    ) {

                        $supply->status = 'low';

                    } else {

                        $supply->status = 'in-stock';
                    }

                    $supply->save();
                }


                /*
                 * ==========================================
                 * 6. PUBLISH EVENT
                 * ==========================================
                 */

                $now = now();

                $event->update([
                    'status' =>
                        'published',

                    'published_at' =>
                        $now,
                ]);


                /*
                 * ==========================================
                 * 7. PUBLISH BARANGAY LISTS
                 * ==========================================
                 */

                $event->lists()->update([
                    'status' =>
                        'published',

                    'published_at' =>
                        $now,
                ]);


                return $event->fresh(
                    $this->getEventRelations()
                );
            });


            return response()->json([
                'message' =>
                    'Distribution event published successfully. Inventory updated.',

                'event' =>
                    $event,
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' =>
                    $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete distribution event.
     */
    public function complete($id)
    {
        $event =
            DistributionEvent::with('lists')
                ->findOrFail($id);


        if ($event->status !== 'published') {

            return response()->json([
                'message' =>
                    'Only published events can be completed.'
            ], 422);
        }


        $now = now();

        $event->update([
            'status' =>
                'completed',

            'completed_at' =>
                $now,
        ]);


        $event->lists()->update([
            'status' =>
                'completed',

            'completed_at' =>
                $now,
        ]);


        return response()->json([
            'message' =>
                'Distribution event completed successfully.',

            'event' =>
                $event->fresh(
                    $this->getEventRelations()
                ),
        ]);
    }

    /**
     * Delete a draft distribution event.
     */
    public function destroy($id)
    {
        try {

            return DB::transaction(function () use ($id) {

                $event =
                    DistributionEvent::with('lists')
                        ->findOrFail($id);


                /*
                 * Only draft events can be deleted.
                 */

                if ($event->status !== 'draft') {

                    return response()->json([
                        'message' =>
                            'Only draft distribution events can be deleted.'
                    ], 422);
                }


                /*
                 * Delete related records.
                 */

                foreach ($event->lists as $list) {

                    DistributionAllocation::where(
                        'distribution_list_id',
                        $list->id
                    )->delete();


                    DistributionFarmer::where(
                        'distribution_list_id',
                        $list->id
                    )->delete();


                    DistributionItem::where(
                        'distribution_list_id',
                        $list->id
                    )->delete();


                    $list->delete();
                }


                /*
                 * Delete uploaded letter.
                 */

                if ($event->letter_image_path) {

                    Storage::disk('public')->delete(
                        $event->letter_image_path
                    );
                }


                /*
                 * Delete event.
                 */

                $event->delete();


                return response()->json([
                    'message' =>
                        'Distribution event deleted successfully.'
                ]);
            });

        } catch (\Exception $e) {

            return response()->json([
                'message' =>
                    'Failed to delete distribution event.',

                'error' =>
                    $e->getMessage()
            ], 500);
        }
    }
}