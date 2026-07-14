<?php

namespace App\Http\Controllers;

use App\Models\InsuranceSeason;
use Illuminate\Http\Request;

class InsuranceSeasonController extends Controller
{
    /**
     * Get the current ("default") season.
     * Does NOT auto-create or auto-rotate seasons as a side effect —
     * it only lazily creates a season if none exists yet at all.
     * Rotation to a new season only happens via createNewSeason().
     */
    private function getOrCreateCurrentSeason()
    {
        $season = InsuranceSeason::where('is_default', true)->latest()->first();

        if (!$season) {
            // No season exists at all yet (e.g. fresh install) — bootstrap one.
            return InsuranceSeason::create([
                'season_name' => 'Default Season ' . now()->year,
                'deadline_date' => null,
                'status' => 'application_open',
                'is_default' => true,
            ]);
        }

        // If the deadline has passed, reflect that in status only.
        // Do NOT touch is_default here — closed seasons stay "current"
        // until someone explicitly starts a new season.
        if (
            $season->status === 'application_open' &&
            $season->deadline_date !== null &&
            now()->toDateString() > $season->deadline_date->toDateString()
        ) {
            $season->update([
                'status' => 'application_closed',
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
            'status' => 'application_open',
            'is_default' => true,
        ]);

        return response()->json([
            'message' => 'Current insurance season updated successfully.',
            'season' => $season,
        ]);
    }

    /**
     * Close the current season's applications.
     * This ONLY changes status. It does NOT create a new season
     * and does NOT unset is_default — the closed season remains
     * "current" (and its damage reports remain in the Current tab)
     * until a new season is explicitly created via createNewSeason().
     */
    public function closeCurrent()
    {
        $season = $this->getOrCreateCurrentSeason();

        $season->update([
            'status' => 'application_closed',
        ]);

        return response()->json([
            'message' => 'Current season closed. It remains the active season until a new one is created.',
            'closed_season' => $season,
        ]);
    }

    /**
     * Explicitly start a new season. This is the ONLY place
     * is_default should move from one season to another.
     */
    public function createNewSeason(Request $request)
    {
        $request->validate([
            'season_name' => 'required|string|max:255',
            'deadline_date' => 'nullable|date',
        ]);

        // Demote whatever was previously default (there should only ever
        // be one, but guard against drift with updateAll rather than one row).
        InsuranceSeason::where('is_default', true)->update(['is_default' => false]);

        $newSeason = InsuranceSeason::create([
            'season_name' => $request->season_name,
            'deadline_date' => $request->deadline_date,
            'status' => 'application_open',
            'is_default' => true,
        ]);

        return response()->json([
            'message' => 'New season created and is now current.',
            'season' => $newSeason,
        ]);
    }

    public function show($id)
    {
        return InsuranceSeason::with('applications.farm.farmerProfile.user')
            ->findOrFail($id);
    }
}