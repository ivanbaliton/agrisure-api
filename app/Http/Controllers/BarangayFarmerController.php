<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class BarangayFarmerController extends Controller
{
    public function index(Request $request)
    {
        $barangayId = $request->user()->barangay_id;

        return User::with([
            'farmerProfile.farms'
        ])
        ->where('role', 'farmer')
        ->where('barangay_id', $barangayId)
        ->orderBy('last_name')
        ->get();
    }

    public function show(Request $request, $id)
    {
        $barangayId = $request->user()->barangay_id;

        return User::with([
            'farmerProfile.farms'
        ])
        ->where('role', 'farmer')
        ->where('barangay_id', $barangayId)
        ->findOrFail($id);
    }
}