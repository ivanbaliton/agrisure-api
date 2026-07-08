<?php

namespace App\Http\Controllers;

use App\Models\InsuranceSeason;
use Illuminate\Http\Request;

class InsuranceSeasonController extends Controller
{
    private function getOrCreateCurrentSeason()
    {
        $season = InsuranceSeason::where('status', 'open')
            ->latest()
            ->first();

        if (!$season) {
            return InsuranceSeason::create([
                'season_name' => 'Default Season ' . now()->year,
                'deadline_date' => null,
                'status' => 'open',
                'is_default' => true,
            ]);
        }

        if (
            $season->deadline_date !== null &&
            now()->toDateString() > $season->deadline_date->toDateString()
        ) {
            $season->update([
                'status' => 'closed',
            ]);

            return InsuranceSeason::create([
                'season_name' => 'Default Season ' . now()->year,
                'deadline_date' => null,
                'status' => 'open',
                'is_default' => true,
            ]);
        }

        return $season;
    }

    public function index()
    {
        return InsuranceSeason::latest()->get();
    }

    public function current()
    {
        $season = $this->getOrCreateCurrentSeason();

        return response()->json([
            'season' => $season,
        ]);
    }

    public function updateCurrent(Request $request)
    {
        $season = $this->getOrCreateCurrentSeason();

        $request->validate([
            'season_name' => 'required|string|max:255',
            'deadline_date' => 'nullable|date',
        ]);

        $season->update([
            'season_name' => $request->season_name,
            'deadline_date' => $request->deadline_date,
            'status' => 'open',
            'is_default' => false,
        ]);

        return response()->json([
            'message' => 'Current insurance season updated successfully.',
            'season' => $season,
        ]);
    }

    public function closeCurrent()
    {
        $season = $this->getOrCreateCurrentSeason();

        $season->update([
            'status' => 'closed',
        ]);

        $newSeason = InsuranceSeason::create([
            'season_name' => 'Default Season ' . now()->year,
            'deadline_date' => null,
            'status' => 'open',
            'is_default' => true,
        ]);

        return response()->json([
            'message' => 'Current season closed. A new default season is now active.',
            'closed_season' => $season,
            'new_season' => $newSeason,
        ]);
    }

    public function show($id)
    {
        return InsuranceSeason::with('applications.farm.farmerProfile.user')
            ->findOrFail($id);
    }
}