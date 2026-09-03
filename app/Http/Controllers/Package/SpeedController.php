<?php

namespace App\Http\Controllers\Package;

use App\Http\Controllers\Controller;
use App\Models\Speed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpeedController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mbps' => ['required', 'integer', 'min:1'],
        ]);

        Speed::query()->create($data);

        return redirect()->route('packages.index')->with('success', 'packages.speeds.created');
    }

    public function update(Request $request, Speed $speed): RedirectResponse
    {
        $data = $request->validate([
            'mbps' => ['required', 'integer', 'min:1'],
        ]);

        $speed->update($data);

        return redirect()->route('packages.index')->with('success', 'packages.speeds.updated');
    }

    public function destroy(Speed $speed): RedirectResponse
    {
        $speed->delete();

        return redirect()->route('packages.index')->with('success', 'packages.speeds.deleted');
    }
}
