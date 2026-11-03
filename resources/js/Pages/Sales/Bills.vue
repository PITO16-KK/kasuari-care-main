<script setup>
import { Link } from '@inertiajs/vue3';
import { Banknote, ClipboardList } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ sales: Object, filters: Object, counts: Object });
const filters = [
    { key: 'all', label: 'Semua tagihan' },
    { key: 'assessment', label: 'Assessment' },
    { key: 'therapy', label: 'Terapi' },
];

const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);
const date = (value) => value
    ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(value))
    : 'Belum dijadwalkan';
const billType = (sale) => {
    if (sale.lines.some((line) => line.line_type === 'assessment')) {
        return 'Assessment';
    }

    return sale.lines.some((line) => line.line_type === 'package') ? 'Paket terapi' : 'Sesi terapi';
};
const dateLabel = (sale) => sale.assessment?.scheduled_at ? 'Jadwal assessment' : sale.appointment?.starts_at ? 'Jadwal sesi' : 'Dibuat';
const dateValue = (sale) => sale.assessment?.scheduled_at ?? sale.appointment?.starts_at ?? sale.created_at;
const emptyTitle = () => ({
    all: 'Belum ada tagihan',
    assessment: 'Belum ada tagihan assessment',
    therapy: 'Belum ada tagihan terapi',
}[props.filters.type] ?? 'Belum ada tagihan');
</script>

<template>
    <MainLayout>
        <div class="space-y-5">
            <header>
                <p class="text-xs font-bold uppercase tracking-wide text-clinic-600">Kasir / Keuangan</p>
                <h1 class="mt-1 text-2xl font-bold text-clinic-900">Tagihan</h1>
                <p class="mt-2 text-sm text-slate-600">Daftar invoice assessment dan terapi yang masih menunggu pembayaran.</p>
            </header>

            <nav class="flex flex-wrap gap-2" aria-label="Filter jenis tagihan">
                <Link
                    v-for="filter in filters"
                    :key="filter.key"
                    :href="`/bills?type=${filter.key}`"
                    preserve-scroll
                    :aria-current="props.filters.type === filter.key ? 'page' : undefined"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg border px-4 text-xs font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                    :class="props.filters.type === filter.key ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                >
                    {{ filter.label }}
                    <span class="rounded-full px-2 py-0.5 text-[10px]" :class="props.filters.type === filter.key ? 'bg-white/15' : 'bg-slate-100'">{{ counts[filter.key] }}</span>
                </Link>
            </nav>

            <section v-if="sales.data.length" class="space-y-3">
                <article v-for="sale in sales.data" :key="sale.id" class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-bold text-clinic-900">{{ sale.patient.name }}</h2>
                            <span class="font-mono text-[10px] text-slate-500">{{ sale.patient.patient_code }}</span>
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-800">Belum dibayar</span>
                            <span class="rounded-full bg-clinic-50 px-2.5 py-1 text-[10px] font-semibold text-clinic-800">{{ billType(sale) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">{{ sale.lines.map((line) => line.description).join(', ') }} · {{ dateLabel(sale) }}: {{ date(dateValue(sale)) }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">Invoice {{ sale.invoice_number }}</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:items-end">
                        <p class="text-base font-bold text-clinic-900">{{ money(sale.grand_total) }}</p>
                        <Link :href="`/sales/${sale.id}/receipt`" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-clinic-700 px-4 text-xs font-bold text-white hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                            <Banknote :size="15" /> Buka tagihan &amp; catat pembayaran
                        </Link>
                    </div>
                </article>
            </section>

            <section v-else class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                <ClipboardList :size="26" class="mx-auto text-slate-400" />
                <h2 class="mt-3 text-sm font-bold text-slate-800">{{ emptyTitle() }}</h2>
                <p class="mt-1 text-xs text-slate-600">Invoice yang sudah lunas dapat dilihat di Riwayat pembayaran.</p>
            </section>

            <nav v-if="sales.links.length > 3" aria-label="Halaman tagihan" class="flex flex-wrap justify-center gap-1">
                <template v-for="link in sales.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll :aria-current="link.active ? 'page' : undefined" class="inline-flex min-h-11 items-center rounded-md border px-3 py-2 text-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" v-html="link.label" />
                    <span v-else class="inline-flex min-h-11 items-center rounded-md border border-slate-100 px-3 py-2 text-xs text-slate-400" v-html="link.label"></span>
                </template>
            </nav>
        </div>
    </MainLayout>
</template>
