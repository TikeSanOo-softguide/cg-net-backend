<?php

namespace App\Http\Controllers\Package;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\StorePackageRequest;
use App\Http\Requests\Package\UpdatePackageRequest;
use App\Models\Addon;
use App\Models\Network;
use App\Models\Package;
use App\Models\Speed;
use App\Models\Term;
use App\Support\StoresPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $networkSearch = trim((string) $request->string('network_search'));
        $speedSearch = trim((string) $request->string('speed_search'));
        $termSearch = trim((string) $request->string('term_search'));
        $addonSearch = trim((string) $request->string('addon_search'));
        $networkIds = array_filter(array_map('intval', (array) $request->input('network_ids', [])));
        $speedIds = array_filter(array_map('intval', (array) $request->input('speed_ids', [])));
        $termIds = array_filter(array_map('intval', (array) $request->input('term_ids', [])));
        $addonIds = array_filter(array_map('intval', (array) $request->input('addon_ids', [])));
        $statusFilters = array_values(array_intersect(
            ['active', 'inactive', 'recommended'],
            (array) $request->input('status_filters', []),
        ));

        $status = $request->string('status')->toString();
        $recommended = $request->string('recommended')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['price', 'installation_fee', 'sort_order', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $packages = Package::query()
            ->with([
                'network:id,name_en,name_zh,name_my',
                'speed:id,mbps',
                'term:id,months',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('network', function ($query) use ($search): void {
                        $query->where(function ($query) use ($search): void {
                            $query
                                ->where('name_en', 'like', '%' . $search . '%')
                                ->orWhere('name_zh', 'like', '%' . $search . '%')
                                ->orWhere('name_my', 'like', '%' . $search . '%');
                        });
                    })
                        ->orWhereHas('speed', function ($query) use ($search): void {
                            $query->where(
                                'mbps',
                                'like',
                                '%' . $search . '%'
                            );
                        })
                        ->orWhereHas('term', function ($query) use ($search): void {
                            $query->where(
                                'months',
                                'like',
                                '%' . $search . '%'
                            );
                        });
                });
            })
            ->when($networkIds !== [], fn($query) => $query->whereIn('network_id', $networkIds))
            ->when($speedIds !== [], fn($query) => $query->whereIn('speed_id', $speedIds))
            ->when($termIds !== [], fn($query) => $query->whereIn('term_id', $termIds))
            ->when($addonIds !== [], fn($query) => $query->whereHas('addons', fn($query) => $query->whereIn('addons.id', $addonIds)))
            ->when(count(array_intersect($statusFilters, ['active', 'inactive'])) === 1, function ($query) use ($statusFilters): void {
                $query->where('is_active', in_array('active', $statusFilters, true));
            })
            ->when(in_array('recommended', $statusFilters, true), fn($query) => $query->where('recommended', true))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(
                fn(Package $package) => $this->payload($package)
            );

        return Inertia::render('Package/Index', [
            'packages' => $packages,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'network_search' => $networkSearch,
                'speed_search' => $speedSearch,
                'term_search' => $termSearch,
                'addon_search' => $addonSearch,
                'network_ids' => array_map('strval', $networkIds),
                'speed_ids' => array_map('strval', $speedIds),
                'term_ids' => array_map('strval', $termIds),
                'addon_ids' => array_map('strval', $addonIds),
                'status_filters' => $statusFilters,
                'status' => $status,
                'recommended' => $recommended,
                'sort' => $request->string('sort', 'created_at')->toString(),
                'direction' => $request->string('direction', 'desc')->toString(),
            ],
            'networks' => $this->networkOptions(),
            'speeds' => $this->speedOptions(),
            'terms' => $this->termOptions(),
            'addons' => $this->addonOptions(),
            'networkTable' => $this->networkTable($networkSearch),
            'speedTable' => $this->speedTable($speedSearch),
            'termTable' => $this->termTable($termSearch),
            'addonTable' => $this->addonTable($addonSearch),
            'stats' => [
                'networks' => Network::query()->count(),
                'speeds' => Speed::query()->count(),
                'terms' => Term::query()->count(),
                'addons' => Addon::query()->count(),
            ],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('packages.index');
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        $package = Package::query()->create($this->attributes(
            $request->validated(),
            $request->file('image_url'),
        ));

        activity('package')->causedBy($request->user())->performedOn($package)->event('created')->log('package_created');

        return redirect()->route('packages.index')->with('success', 'packages.created');
    }

    public function show(Package $package): Response
    {
        $package->load([
            'network:id,name',
            'speed:id,mbps',
            'term:id,months',
        ]);

        return Inertia::render('Package/Show', [
            'package' => $this->payload($package),
            'networks' => $this->networkOptions(),
            'speeds' => $this->speedOptions(),
            'terms' => $this->termOptions(),
        ]);
    }

    public function edit(Package $package): RedirectResponse
    {
        return redirect()->route('packages.show', $package);
    }

    public function update(
        UpdatePackageRequest $request,
        Package $package
    ): RedirectResponse {
        $package = Package::findOrFail($package->id);
        $data = $this->attributes(
            $request->validated(),
            $request->file('image_url'),
            $package->image_url,
        );

        if ($request->exists('image_url') && blank($request->input('image_url'))) {
            StoresPublicImage::delete($package->image_url);
            $data['image_url'] = null;
        }

        $package->update($data);
        activity('package')->causedBy($request->user())->performedOn($package)->event('updated')->log('package_updated');
        return redirect()->route('packages.index')->with('success', 'packages.updated');
    }

    public function destroy(Request $request, Package $package): RedirectResponse
    {
        $package->delete();
        return redirect()->route('packages.index')->with('success', 'packages.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct', 'exists:packages,id']])['ids'];
        $deleted = Package::query()->whereIn('id', $ids)->delete();
        if ($deleted === 0) {
            return back()->withErrors(['delete' => 'common.bulk_delete_failed']);
        }
        return redirect()->route('packages.index')->with('success', 'common.bulk_deleted')->with('deleted_count', $deleted);
    }

    private function attributes(
        array $validated,
        ?UploadedFile $imageUrl = null,
        ?string $previousPath = null,
    ): array {
        $data = [
            'network_id' => $validated['network_id'],
            'speed_id' => $validated['speed_id'],
            'term_id' => $validated['term_id'],
            'price' => $validated['price'],
            'installation_fee' => $validated['installation_fee'],
            'includes_free_iptv' => $validated['includes_free_iptv'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'],
            'recommended' => $validated['recommended'],
        ];

        if ($imageUrl) {
            $data['image_url'] = StoresPublicImage::store(
                $imageUrl,
                'cms/packages',
                $previousPath,
            );
        }
        return $data;
    }

    /** * @return array<string, mixed> */
    private function payload(Package $package): array
    {
        return [
            'id' => $package->id,
            'network_id' => $package->network_id,
            'network' => $package->network
                ? [
                    'id' => $package->network->id,
                    'name_en' => $package->network->name_en,
                    'name_zh' => $package->network->name_zh,
                    'name_my' => $package->network->name_my,
                ]
                : null,
            'speed_id' => $package->speed_id,
            'speed' => $package->speed ? [
                'id' => $package->speed->id,
                'mbps' => $package->speed->mbps,
            ] : null,
            'term_id' => $package->term_id,
            'term' => $package->term ? ['id' => $package->term->id, 'months' => $package->term->months] : null,
            'price' => (int) $package->price,
            'image_url' => StoresPublicImage::url($package->image_url),
            'installation_fee' => (int) $package->installation_fee,
            'includes_free_iptv' => $package->includes_free_iptv,
            'is_active' => $package->is_active,
            'sort_order' => $package->sort_order,
            'recommended' => $package->recommended,
            'created_at' => $package->created_at?->toDateString(),
        ];
    }

    private function networkOptions(): array
    {
        return Network::query()
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_zh', 'name_my'])
            ->map(fn(Network $network) => [
                'id' => $network->id,
                'name_en' => $network->name_en,
                'name_zh' => $network->name_zh,
                'name_my' => $network->name_my,
            ])
            ->values()
            ->all();
    }

    private function speedOptions(): array
    {
        return Speed::query()
            ->orderBy('mbps')
            ->get(['id', 'mbps'])
            ->map(fn(Speed $speed) => ['id' => $speed->id, 'mbps' => $speed->mbps])
            ->values()
            ->all();
    }

    private function termOptions(): array
    {
        return Term::query()
            ->orderBy('months')
            ->get(['id', 'months'])
            ->map(fn(Term $term) => ['id' => $term->id, 'months' => $term->months])
            ->values()
            ->all();
    }

    private function addonOptions(): array
    {
        return Addon::query()
            ->orderBy('name_en')
            ->get([
                'id',
                'name_en',
                'name_zh',
                'name_my',
                'price',
                'image_url',
            ])
            ->map(fn(Addon $addon) => [
                'id' => $addon->id,
                'name_en' => $addon->name_en,
                'name_zh' => $addon->name_zh,
                'name_my' => $addon->name_my,
                'price' => (int) $addon->price,
                'image_url' => StoresPublicImage::url(
                    $addon->image_url
                ),
            ])
            ->values()
            ->all();
    }

    private function networkTable(?string $search = null): array
    {
        return Network::query()
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name_en', 'like', '%' . $search . '%')
                        ->orWhere('name_zh', 'like', '%' . $search . '%')
                        ->orWhere('name_my', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_zh', 'name_my'])
            ->map(fn(Network $network) => [
                'id' => $network->id,
                'name_en' => $network->name_en,
                'name_zh' => $network->name_zh,
                'name_my' => $network->name_my,
            ])
            ->values()
            ->all();
    }

    private function speedTable(?string $search = null): array
    {
        return Speed::query()
            ->when($search, function ($query) use ($search): void {
                $query->where(
                    'mbps',
                    'like',
                    '%' . $search . '%'
                );
            })
            ->orderBy('mbps')
            ->get(['id', 'mbps'])
            ->map(
                fn(Speed $speed) => [
                    'id' => $speed->id,
                    'mbps' => $speed->mbps,
                ]
            )
            ->values()
            ->all();
    }

    private function termTable(?string $search = null): array
    {
        return Term::query()
            ->when($search, function ($query) use ($search): void {
                $query->where(
                    'months',
                    'like',
                    '%' . $search . '%'
                );
            })
            ->orderBy('months')
            ->get(['id', 'months'])
            ->map(
                fn(Term $term) => [
                    'id' => $term->id,
                    'months' => $term->months,
                ]
            )
            ->values()
            ->all();
    }

    private function addonTable(?string $search = null): array
    {
        return Addon::query()
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name_en', 'like', '%' . $search . '%')
                        ->orWhere('name_zh', 'like', '%' . $search . '%')
                        ->orWhere('name_my', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name_en')
            ->get([
                'id',
                'name_en',
                'name_zh',
                'name_my',
                'price',
                'image_url',
            ])
            ->map(fn(Addon $addon) => [
                'id' => $addon->id,
                'name_en' => $addon->name_en,
                'name_zh' => $addon->name_zh,
                'name_my' => $addon->name_my,
                'price' => (int) $addon->price,
                'image_url' => StoresPublicImage::url($addon->image_url),
            ])
            ->values()
            ->all();
    }
}
