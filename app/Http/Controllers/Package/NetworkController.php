<?php

namespace App\Http\Controllers\Package;

use App\Http\Controllers\Controller;
use App\Models\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NetworkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:50'],
            'name_zh' => ['required', 'string', 'max:50'],
            'name_my' => ['required', 'string', 'max:50'],
        ]);

        Network::query()->create($data);

        return redirect()->route('packages.index')->with('success', 'packages.reference_created');
    }

    public function update(Request $request, Network $network): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:50'],
            'name_zh' => ['required', 'string', 'max:50'],
            'name_my' => ['required', 'string', 'max:50'],
        ]);

        $network->update($data);

        return redirect()->route('packages.index')->with('success', 'packages.reference_updated');
    }

    public function destroy(Network $network): RedirectResponse
    {
        $network->delete();

        return redirect()->route('packages.index')->with('success', 'packages.reference_deleted');
    }
}
