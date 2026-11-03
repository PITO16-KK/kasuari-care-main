<?php

namespace App\Http\Controllers;

use App\Models\PackageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PackageCatalogController extends Controller
{
    public function index(): Response
    {
        $packages = DB::table('package_catalog')
            ->orderBy('session_duration_minutes')
            ->orderBy('session_quantity')
            ->orderBy('name')
            ->get();
        $itemsByPackage = DB::table('package_catalog_items')
            ->join('therapy_types', 'therapy_types.id', '=', 'package_catalog_items.therapy_type_id')
            ->select([
                'package_catalog_items.package_catalog_id',
                'therapy_types.code',
                'therapy_types.name',
                'package_catalog_items.session_quantity',
            ])
            ->orderBy('therapy_types.name')
            ->get()
            ->groupBy('package_catalog_id');

        return Inertia::render('Packages/Index', [
            'services' => DB::table('service_catalog')
                ->where('is_active', true)
                ->where('service_type', 'assessment')
                ->orderBy('name')
                ->get(['code', 'name', 'price', 'description']),
            'packages' => $packages->map(function (object $package) use ($itemsByPackage): object {
                $package->items = $itemsByPackage->get($package->id, collect())->values();

                return $package;
            }),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:65535'],
            'session_duration_minutes' => ['required', 'integer', 'min:1', 'max:65535'],
            'session_quantity' => ['required', 'integer', 'min:1', 'max:65535'],
            'price' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);

        DB::transaction(function () use ($data): void {
            PackageCatalog::create([
                'name' => $data['name'],
                'price' => $data['price'],
                'validity_days' => $data['validity_days'],
                'session_duration_minutes' => $data['session_duration_minutes'],
                'session_quantity' => $data['session_quantity'],
                'is_active' => true,
            ]);
        });

        return redirect()->route('packages.index')->with('success', 'Paket terapi berhasil ditambahkan.');
    }

    public function toggleStatus(PackageCatalog $packageCatalog): RedirectResponse
    {
        $packageCatalog->update(['is_active' => ! $packageCatalog->is_active]);

        $message = $packageCatalog->is_active
            ? 'Paket berhasil diaktifkan kembali.'
            : 'Paket diarsipkan dan tidak bisa dipilih untuk transaksi baru.';

        return redirect()->route('packages.index')->with('success', $message);
    }
}
