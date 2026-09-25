<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAppVersionRequest;
use App\Http\Requests\Settings\UpdateAppVersionRequest;
use App\Models\AppVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppVersionController extends Controller
{
    public function index(Request $request): Response
    {
        $versions = AppVersion::query()
            ->when(
                $request->filled('platform') && $request->input('platform') !== 'all',
                fn($query) => $query->where('platform', $request->input('platform')),
            )
            ->when($request->filled('version') && $request->input('version') !== 'all', function ($query) use (
                $request,
            ): void {
                $query->where('version', $request->input('version'));
            })
            ->latest()
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        $versionOptions = AppVersion::query()
            ->when(
                $request->filled('platform') && $request->input('platform') !== 'all',
                fn($query) => $query->where('platform', $request->input('platform')),
            )
            ->select('version')
            ->distinct()
            ->orderBy('version')
            ->pluck('version')
            ->filter()
            ->values();

        return Inertia::render('Settings/AppVersion/Index', [
            'items' => $versions,

            'filters' => [
                'platform' => $request->input('platform', ''),
                'version' => $request->input('version', ''),
            ],

            'versionOptions' => $versionOptions,
        ]);
    }

    public function store(StoreAppVersionRequest $request): RedirectResponse
    {
        $version = AppVersion::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($version)
            ->event('created')
            ->log('app_version_created');

        return redirect()->route('settings.app-version.index')->with('success', 'settings.app_version.created');
    }

    public function update(UpdateAppVersionRequest $request, AppVersion $appVersion): RedirectResponse
    {
        $appVersion->update([...$request->validated(), 'updated_by' => $request->user()->getAuthIdentifier()]);

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($appVersion)
            ->event('updated')
            ->log('app_version_updated');

        return redirect()->route('settings.app-version.index')->with('success', 'settings.app_version.updated');
    }

    public function destroy(Request $request, AppVersion $appVersion): RedirectResponse
    {
        $appVersion->delete();

        activity('settings')
            ->causedBy($request->user())
            ->performedOn($appVersion)
            ->event('deleted')
            ->log('app_version_deleted');

        return redirect()->route('settings.app-version.index')->with('success', 'settings.app_version.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        AppVersion::query()
            ->whereKey($ids)
            ->get()
            ->each(function (AppVersion $version) use ($request): void {
                $version->delete();
                activity('settings')
                    ->causedBy($request->user())
                    ->performedOn($version)
                    ->event('deleted')
                    ->log('app_version_deleted');
            });

        return redirect()->route('settings.app-version.index')->with('success', 'settings.app_version.deleted');
    }
}
