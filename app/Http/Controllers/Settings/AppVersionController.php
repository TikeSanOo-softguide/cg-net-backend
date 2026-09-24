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
        $search = trim((string) $request->input('search', ''));

        $versions = AppVersion::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereLike('version', "%{$search}%")
                        ->orWhereLike('minimum_version', "%{$search}%")
                        ->orWhereLike('platform', "%{$search}%");
                });
            })
            ->when($request->filled('platform'), fn($query) => $query->where('platform', $request->input('platform')))
            ->when($request->filled('status'), fn($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('Settings/AppVersion/Index', [
            'items' => $versions,
            'filters' => [
                'search' => $search,
                'platform' => $request->input('platform', ''),
                'status' => $request->input('status', ''),
            ],
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
