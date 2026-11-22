<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { BookOpen, PackageCheck, Pencil, Plus, Search, Trash2, X } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({
    products: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    nextSku: { type: String, default: 'PRD-0001' },
});

const form = useForm({
    name: '',
    sku: props.nextSku,
    price: 50000,
    stock_quantity: 10,
});

const editingProduct = ref(null);
const search = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'all');
const changingStatusId = ref(null);

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value) || 0);

function applyFilters() {
    router.get('/products', {
        q: search.value,
        status: status.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function openCreate() {
    editingProduct.value = null;
    form.reset();
    form.clearErrors();
    form.sku = props.nextSku;
    form.price = 50000;
    form.stock_quantity = 10;
}

function openEdit(product) {
    editingProduct.value = product;
    form.clearErrors();
    form.name = product.name;
    form.sku = product.sku;
    form.price = product.price;
    form.stock_quantity = product.stock_quantity;
}

function cancelEdit() {
    editingProduct.value = null;
    openCreate();
}

function submit() {
    if (editingProduct.value) {
        form.put(`/products/${editingProduct.value.id}`, {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
        return;
    }

    form.post('/products', {
        preserveScroll: true,
        onSuccess: () => openCreate(),
    });
}

function toggleStatus(product) {
    changingStatusId.value = product.id;
    router.patch(`/products/${product.id}/status`, {}, {
        preserveScroll: true,
        onFinish: () => {
            changingStatusId.value = null;
        },
    });
}

function deleteProduct(product) {
    if (window.confirm(`Hapus item ${product.name}?`)) {
        router.delete(`/products/${product.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <MainLayout>
        <header>
            <p class="text-xs font-semibold uppercase tracking-wide text-clinic-700">Pengaturan layanan & produk</p>
            <div class="mt-1 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-clinic-900">Produk & Item Fisik</h1>
                    <p class="mt-1 text-sm text-slate-600">Kelola item seperti buku terapi, media kartu, perlengkapan stimulasi, dan produk lainnya untuk kasir.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/packages" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-xs font-semibold text-slate-700 hover:bg-[#f6f5ef]">
                        <PackageCheck :size="16" /> Lihat Paket Terapi
                    </Link>
                </div>
            </div>

            <!-- Tab Navigasi -->
            <div class="mt-5 flex gap-2 border-b border-slate-200">
                <Link href="/packages" class="border-b-2 border-transparent px-4 py-2.5 text-xs font-medium text-slate-600 hover:text-clinic-900">
                    Paket Terapi
                </Link>
                <Link href="/products" class="border-b-2 border-clinic-700 px-4 py-2.5 text-xs font-bold text-clinic-900">
                    Produk & Buku (Item Kasir)
                </Link>
            </div>
        </header>

        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white" aria-labelledby="product-form-heading">
            <header class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-coral-50 text-coral-700"><BookOpen :size="18" /></span>
                    <div>
                        <h2 id="product-form-heading" class="text-sm font-semibold text-clinic-900">
                            {{ editingProduct ? `Edit Item: ${editingProduct.name}` : 'Tambah Produk / Buku Baru' }}
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-600">Produk yang aktif akan langsung muncul di menu Kasir & POS saat memilih jenis transaksi Produk.</p>
                    </div>
                </div>
            </header>

            <form class="grid gap-4 p-5 sm:p-6 lg:grid-cols-4" @submit.prevent="submit">
                <div class="lg:col-span-2">
                    <label class="field-label" for="product-name">
                        Nama item / produk *
                        <input
                            id="product-name"
                            v-model="form.name"
                            type="text"
                            maxlength="255"
                            placeholder="Contoh: Buku Panduan Terapi Wicara"
                            class="field-input mt-1.5"
                            required
                        >
                    </label>
                    <p v-if="form.errors.name" class="field-error mt-1">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="field-label" for="product-sku">
                        Kode / SKU
                        <input
                            id="product-sku"
                            v-model="form.sku"
                            type="text"
                            maxlength="100"
                            placeholder="Contoh: BUKU-01"
                            class="field-input mt-1.5"
                            required
                        >
                    </label>
                    <p v-if="form.errors.sku" class="field-error mt-1">{{ form.errors.sku }}</p>
                </div>

                <div>
                    <label class="field-label" for="product-price">
                        Harga jual (Rp) *
                        <input
                            id="product-price"
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            step="1000"
                            class="field-input mt-1.5"
                            required
                        >
                    </label>
                    <p v-if="form.errors.price" class="field-error mt-1">{{ form.errors.price }}</p>
                </div>

                <div>
                    <label class="field-label" for="product-stock">
                        Jumlah stok (unit) *
                        <input
                            id="product-stock"
                            v-model.number="form.stock_quantity"
                            type="number"
                            min="0"
                            step="1"
                            class="field-input mt-1.5"
                            required
                        >
                    </label>
                    <p v-if="form.errors.stock_quantity" class="field-error mt-1">{{ form.errors.stock_quantity }}</p>
                </div>

                <div class="flex items-end gap-2 lg:col-span-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-clinic-700 px-5 text-xs font-bold text-white hover:bg-clinic-900 disabled:opacity-60"
                    >
                        <Plus v-if="!editingProduct" :size="15" />
                        {{ form.processing ? 'Menyimpan...' : (editingProduct ? 'Simpan Perubahan' : 'Tambah Produk') }}
                    </button>
                    <button
                        v-if="editingProduct"
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="cancelEdit"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </section>

        <!-- Daftar Produk -->
        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <header class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-sm font-semibold text-clinic-900">Katalog Produk</h2>
                    <p class="text-xs text-slate-500">Total {{ products.total || products.data?.length || 0 }} item terdaftar</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative min-w-[200px]">
                        <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari nama atau SKU..."
                            class="h-9 w-full rounded-lg border border-slate-200 pl-8 pr-3 text-xs focus:border-clinic-700 focus:outline-none"
                            @keyup.enter="applyFilters"
                        >
                    </div>

                    <select v-model="status" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" @change="applyFilters">
                        <option value="all">Semua Status</option>
                        <option value="active">Hanya Aktif</option>
                        <option value="archived">Diarsipkan</option>
                    </select>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                        @click="applyFilters"
                    >
                        Filter
                    </button>
                </div>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-slate-100 bg-[#f9f8f5] text-slate-600">
                        <tr>
                            <th class="px-5 py-3 font-semibold">SKU</th>
                            <th class="px-5 py-3 font-semibold">Nama Item / Produk</th>
                            <th class="px-5 py-3 font-semibold">Harga Satuan</th>
                            <th class="px-5 py-3 font-semibold">Stok Tersedia</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in products.data" :key="item.id" class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-slate-600">
                                {{ item.sku }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-semibold text-slate-800">{{ item.name }}</div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-semibold text-clinic-900">
                                {{ money(item.price) }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="item.stock_quantity > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                >
                                    {{ item.stock_quantity }} unit
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ item.is_active ? 'Aktif' : 'Diarsipkan' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        type="button"
                                        title="Edit produk"
                                        class="grid size-8 place-items-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-100"
                                        @click="openEdit(item)"
                                    >
                                        <Pencil :size="14" />
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="changingStatusId === item.id"
                                        class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100"
                                        @click="toggleStatus(item)"
                                    >
                                        {{ item.is_active ? 'Arsipkan' : 'Aktifkan' }}
                                    </button>
                                    <button
                                        type="button"
                                        title="Hapus produk"
                                        class="grid size-8 place-items-center rounded-md border border-rose-200 text-rose-600 hover:bg-rose-50"
                                        @click="deleteProduct(item)"
                                    >
                                        <Trash2 :size="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!products.data || !products.data.length">
                            <td colspan="6" class="px-5 py-8 text-center text-slate-500">
                                Belum ada item produk. Tambahkan produk di atas agar dapat dijual di kasir.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="products.links?.length > 3" class="flex flex-wrap gap-1.5 border-t border-slate-100 px-5 py-3.5">
                <Link
                    v-for="link in products.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded border px-2.5 py-1 text-xs"
                    :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                    v-html="link.label"
                />
            </div>
        </section>
    </MainLayout>
</template>
