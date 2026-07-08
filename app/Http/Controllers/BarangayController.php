<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Barangay;

class BarangayController extends Controller
{
    public function index()
    {
        return User::where('role', 'barangay')
            ->with('barangay:id,name')
            ->select(
                'id',
                'first_name',
                'last_name',
                'barangay_id'
            )
            ->get();
    }

    public function farmers($barangayId)
    {
        return User::where('role', 'farmer')
            ->where('barangay_id', $barangayId)
            ->select(
                'id',
                'first_name',
                'middle_name',
                'last_name'
            )
            ->orderBy('last_name')
            ->get();
    }

    public function list()
{
    return Barangay::select('id', 'name')
        ->orderBy('name')
        ->get();
}
}