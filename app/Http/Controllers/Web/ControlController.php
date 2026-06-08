<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Control;
use App\Models\Risk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ControlController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'risk_id'       => 'required|exists:risks,id',
            'description'   => 'required|string',
            'effectiveness' => 'required|in:low,medium,high',
        ]);

        Control::create($data);

        // Trigger Risk save to recompute residual score
        $risk = Risk::find($data['risk_id']);
        $risk->save();

        return back()->with('success', 'Control added.');
    }

    public function update(Request $request, Control $control): RedirectResponse
    {
        $data = $request->validate([
            'description'   => 'required|string',
            'effectiveness' => 'required|in:low,medium,high',
        ]);

        $control->update($data);

        // Recompute residual
        $control->risk->save();

        return back()->with('success', 'Control updated.');
    }

    public function destroy(Control $control): RedirectResponse
    {
        $risk = $control->risk;
        $control->delete();

        // Recompute residual after deletion
        $risk->save();

        return back()->with('success', 'Control deleted.');
    }
}
