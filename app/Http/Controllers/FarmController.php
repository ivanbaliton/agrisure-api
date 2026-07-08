<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FarmController extends Controller
{
   
    public function all()
    {
        $farms = Farm::with('farmerProfile.user')->get();
        return response()->json($farms);
    }
    public function index($user_id)
    {
        $profile = FarmerProfile::where('user_id', $user_id)->first();

        if (!$profile) {
            return response()->json([
                'message' => 'Farmer profile not found'
            ], 404);
        }

        $farms = Farm::where(
            'farmer_profile_id',
            $profile->id
        )->latest()->get();

        return response()->json($farms);
    }

    /**
     * Register new farm
     */
    public function store(Request $request)
    {
        $request->validate([
            'farmer_profile_id' => 'required|exists:farmer_profiles,id',
            'farm_name' => 'required|string|max:255',
            'crop_type' => 'required|in:Rice,Corn',
            'farm_area' => 'required|numeric|min:0.01',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'farm_image' => 'required|image|mimes:jpg,jpeg,png|max:5120',

            // Offline sync fields
            'client_uuid' => 'nullable|uuid',
            'sync_source' => 'nullable|in:online,offline',
            'captured_at' => 'nullable|date',
        ]);

        if ($request->client_uuid) {
            $existingFarm = Farm::where('client_uuid', $request->client_uuid)->first();

            if ($existingFarm) {
                return response()->json([
                    'message' => 'Farm already synced.',
                    'farm' => $existingFarm,
                ], 200);
            }
        }

        $imagePath = $request->file('farm_image')
            ->store('farms', 'public');

        $farm = Farm::create([
            'farmer_profile_id' => $request->farmer_profile_id,
            'farm_name' => $request->farm_name,
            'crop_type' => $request->crop_type,
            'farm_area' => $request->farm_area,
            'farm_image_path' => $imagePath,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'insurance_status' => 'not_insured',

            'client_uuid' => $request->client_uuid,
            'sync_source' => $request->sync_source ?? 'online',
            'captured_at' => $request->captured_at,
        ]);

        return response()->json([
            'message' => 'Farm registered successfully.',
            'farm' => $farm,
        ], 201);
    }
    /**
     * View farm details
     */
    public function show($id)
    {
        $farm = Farm::find($id);

        if (!$farm) {
            return response()->json([
                'message' => 'Farm not found'
            ], 404);
        }

        return response()->json($farm);
    }

    /**
     * Update farm
     */
    public function update(Request $request, $id)
    {
        $farm = Farm::find($id);

        if (!$farm) {
            return response()->json([
                'message' => 'Farm not found'
            ], 404);
        }

        $request->validate([
            'farm_name' => 'sometimes|string|max:255',
            'crop_type' => 'sometimes|in:Rice,Corn',
            'farm_area' => 'sometimes|numeric|min:0.01',
        ]);

        $farm->update($request->only([
            'farm_name',
            'crop_type',
            'farm_area',
        ]));

        return response()->json([
            'message' => 'Farm updated successfully.',
            'farm' => $farm,
        ]);
    }

    /**
     * Delete farm
     */
    public function destroy($id)
    {
        $farm = Farm::find($id);

        if (!$farm) {
            return response()->json([
                'message' => 'Farm not found'
            ], 404);
        }

        if ($farm->farm_image_path) {
            Storage::disk('public')
                ->delete($farm->farm_image_path);
        }

        $farm->delete();

        return response()->json([
            'message' => 'Farm deleted successfully.'
        ]);
    }
}