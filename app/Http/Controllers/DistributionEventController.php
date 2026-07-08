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
        $event = DistributionEvent::with('lists')->findOrFail($id);

        if ($event->status !== 'draft') {
            return response()->json(['message' => 'Only draft events can be published.'], 422);
        }

        $now = now();
        $event->update(['status' => 'published', 'published_at' => $now]);
        $event->lists()->update(['status' => 'published', 'published_at' => $now]); // Optimized mass update

        return response()->json([
            'message' => 'Distribution event published successfully.',
            'event' => $event->fresh($this->getEventRelations()),
        ]);
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
}
