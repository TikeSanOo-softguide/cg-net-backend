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
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['price', 'installation_fee', 'sort_order', 'created_at',];

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
            ->when(
                $status !== ''
                    && in_array($status, ['active', 'inactive'], true),
                function ($query) use ($status): void {
                    $query->where(
                        'is_active',
                        $status === 'active'
                    );
                }
            )
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
                'status' => $request->string('status')->toString(),
                'sort' => $request->string('sort', 'created_at')->toString(),
                'direction' => $request->string('direction', 'desc')->toString(),
            ],
            'networks' => $this->networkOptions(),
            'speeds' => $this->speedOptions(),
            'terms' => $this->termOptions(),
            'addons' => $this->addonOptions(),
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

        return redirect()->route('packages.index')->with('success', 'package.created');
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
        $package->update(
            $this->attributes(
                $request->validated(),
                $request->file('image_url'),
                $package->image_url,
            )
        );

        activity('package')->causedBy($request->user())->performedOn($package)->event('created')->log('package_created');

        return redirect()->route('packages.index')->with('success', 'package.created');
    }

    public function destroy(Request $request, Package $package): RedirectResponse
    {
        $package->delete();
        return redirect()->route('packages.index')->with('success', 'packages.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct', 'exists:packages,id',],])['ids'];
        $deleted = Package::query()->whereIn('id', $ids)->delete();
        if ($deleted === 0) {
            return back()->withErrors(['delete' => 'common.bulk_delete_failed',]);
        }
        return redirect()->route('packages.index')->with('success', 'common.bulk_deleted')->with('deleted_count', $deleted);
    }

    private function attributes(
        array $validated,
        ?UploadedFile $image_url = null,
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

        if ($image_url) {
            $data['image_url'] = StoresPublicImage::store(
                $image_url,
                'cms/packages',
                $previousPath,
            );
        }

        return $data;
    }

    /** * @return array<string, mixed> */ private function payload(Package $package): array
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
            'term' => $package->term ? ['id' => $package->term->id, 'months' => $package->term->months,] : null,
            'price' => $package->price,
            'image_url' => StoresPublicImage::url($package->image_url),
            'installation_fee' => $package->installation_fee,
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
        return Speed::query()->orderBy('mbps')->get(['id', 'mbps'])
            ->map(fn(Speed $speed) => ['id' => $speed->id, 'mbps' => $speed->mbps,])
            ->values()->all();
    }

    private function termOptions(): array
    {
        return Term::query()->orderBy('months')->get(['id', 'months'])
            ->map(fn(Term $term) => ['id' => $term->id, 'months' => $term->months,])
            ->values()->all();
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
                'price' => $addon->price,
                'image_url' => StoresPublicImage::url(
                    $addon->image_url
                ),
            ])
            ->values()
            ->all();
    }
}
