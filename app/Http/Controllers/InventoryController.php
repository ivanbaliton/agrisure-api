<?php

namespace App\Http\Controllers;

use App\Models\InventorySupply;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        return InventorySupply::latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'category' => 'required',
            'unit' => 'required',
            'qty_available' => 'required|integer',
            'low_threshold' => 'required|integer',
        ]);

        $qty = $validated['qty_available'];
        $threshold = $validated['low_threshold'];

        $validated['status'] =
            $qty == 0 ? 'out'
            : ($qty < $threshold ? 'low' : 'in-stock');

        return InventorySupply::create($validated);
    }

    public function update(Request $request, $id)
    {
        $supply = InventorySupply::findOrFail($id);

        $supply->update($request->all());

        return response()->json([
            'message' => 'Supply updated successfully',
            'data' => $supply
        ]);
    }

    public function destroy($id)
    {
        InventorySupply::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Supply deleted'
        ]);
    }
}