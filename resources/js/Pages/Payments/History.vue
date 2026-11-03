<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ filters: Object, summary: Object, payments: Object, therapists: Array });
const form = useForm({
    period: props.filters.period,
    date: props.filters.date,
    month: props.filters.month,
    therapist_id: String(props.filters.therapist_id ?? ''),
    method: props.filters.method,
});

const exportUrl = computed(() => {
    const query = new URLSearchParams();

    Object.entries(form.data()).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            query.set(key, value);
        }
    });

    return `/payment-history/export?${query.toString()}`;
});

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value ?? 0));

const paymentDate = (value) => new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    timeZoneName: 'short',
    hourCycle: 'h23',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date(value));

const paymentMethod = (method) => ({
    cash: 'Tunai',
    bank_transfer: 'Transfer bank',
    qris: 'QRIS',
}[method] ?? method);

const monthlyPeriodLabel = computed(() => {
    if (!form.month) {
        return '';
    }

    const [year, month] = form.month.split('-').map(Number);
    const start = new Date(Date.UTC(year, month - 2, 20, 12));
    const end = new Date(Date.UTC(year, month - 1, 19, 12));
    const formatter = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'UTC',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return `${formatter.format(start)} – ${formatter.format(end)}`;
});

function applyFilters() {
    form.get('/payment-history', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <MainLayout>
        <header>
            <p class="text-xs font-semibold uppercase tracking-wide text-clinic-700">Kasir / Keuangan</p>
            <h1 class="mt-1 text-2xl font-semibold text-clinic-900">Riwayat pembayaran</h1>
            <p class="mt-2 text-sm text-slate-600">Lihat transaksi lunas dan unduh laporan. Periode bulanan mengikuti pembukuan tanggal 20–19.</p>
        </header>

        <form class="mt-6 rounded-xl border border-slate-200 bg-white p-4 sm:p-5" @submit.prevent="applyFilters">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <label class="field-label">
                    Periode
                    <select v-model="form.period" class="field-input">
                        <option value="daily">Harian</option>
                        <option value="weekly">Mingguan</option>
                        <option value="monthly">Bulanan</option>
                    </select>
                </label>
                <label v-if="form.period !== 'monthly'" class="field-label">
                    {{ form.period === 'weekly' ? 'Tanggal dalam minggu' : 'Tanggal' }}
                    <input v-model="form.date" type="date" class="field-input" required>
                </label>
                <label v-else class="field-label">
                    Bulan akhir periode pembukuan
                    <input v-model="form.month" type="month" class="field-input" required>
                    <span class="mt-1 block font-normal text-slate-500">{{ monthlyPeriodLabel }}</span>
                </label>
                <label class="field-label">
                    Terapis
                    <select v-model="form.therapist_id" class="field-input">
                        <option value="">Semua terapis</option>
                        <option v-for="therapist in therapists" :key="therapist.id" :value="String(therapist.id)">{{ therapist.user.name }}</option>
                    </select>
                </label>
                <label class="field-label">
                    Metode pembayaran
                    <select v-model="form.method" class="field-input">
                        <option value="">Semua metode</option>
                        <option value="cash">Tunai</option>
                        <option value="bank_transfer">Transfer bank</option>
                        <option value="qris">QRIS</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button :disabled="form.processing" class="h-10 flex-1 rounded-lg bg-clinic-700 px-4 text-xs font-semibold text-white hover:bg-clinic-900 disabled:opacity-60">
                        {{ form.processing ? 'Memuat...' : 'Terapkan filter' }}
                    </button>
                    <a :href="exportUrl" class="inline-flex h-10 items-center justify-center rounded-lg border border-clinic-200 px-3 text-xs font-semibold text-clinic-800 hover:bg-clinic-50" download>Ekspor CSV</a>
                </div>
            </div>
        </form>

        <section class="mt-5 grid gap-3 sm:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <p class="text-xs font-medium text-slate-500">Total pembayaran terfilter</p>
                <p class="mt-2 text-xl font-semibold text-clinic-900">{{ money(summary.amount) }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <p class="text-xs font-medium text-slate-500">Jumlah transaksi</p>
                <p class="mt-2 text-xl font-semibold text-clinic-900">{{ summary.count }}</p>
            </article>
        </section>

        <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-4 sm:px-5">
                <div>
                    <h2 class="text-sm font-semibold text-clinic-900">Transaksi pembayaran</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ payments.total }} transaksi sesuai filter</p>
                </div>
            </div>
            <div v-if="payments.data.length" class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-xs">
                    <thead class="bg-[#F9F7F5] text-[10px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Tanggal bayar</th>
                            <th class="px-4 py-3 font-semibold">Invoice / Pasien</th>
                            <th class="px-4 py-3 font-semibold">Item</th>
                            <th class="px-4 py-3 font-semibold">Terapis</th>
                            <th class="px-4 py-3 font-semibold">Pembayaran</th>
                            <th class="px-4 py-3 text-right font-semibold">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="payment in payments.data" :key="payment.id" class="align-top text-slate-600">
                            <td class="whitespace-nowrap px-4 py-3">{{ paymentDate(payment.received_at) }}</td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/sales/${payment.sale.id}/receipt`"
                                    :aria-label="`Lihat invoice ${payment.sale.invoice_number}`"
                                    class="inline-flex min-h-8 items-center gap-1 rounded-sm font-semibold text-clinic-700 underline decoration-clinic-200 underline-offset-2 hover:text-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                                >
                                    <FileText :size="13" aria-hidden="true" />
                                    {{ payment.sale.invoice_number }}
                                </Link>
                                <div class="mt-1">{{ payment.sale.patient.name }} <span class="font-mono text-[10px] text-slate-400">{{ payment.sale.patient.patient_code }}</span></div>
                            </td>
                            <td class="max-w-[240px] px-4 py-3">{{ payment.sale.lines.map((line) => line.description).join(', ') }}</td>
                            <td class="px-4 py-3">{{ payment.sale.therapist?.user?.name ?? 'Tanpa terapis' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-700">{{ paymentMethod(payment.method) }}</div>
                                <div v-if="payment.bank_name" class="mt-1 text-[10px] text-slate-500">Bank: {{ payment.bank_name }}</div>
                                <div v-if="payment.reference_number" class="mt-1 text-[10px] text-slate-400">{{ payment.reference_number }}</div>
                                <a v-if="payment.has_proof" :href="`/payments/${payment.id}/proof`" target="_blank" rel="noopener" class="mt-1 inline-flex min-h-8 items-center font-semibold text-clinic-700 underline underline-offset-2">Lihat bukti</a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-clinic-900">{{ money(payment.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-5 py-14 text-center">
                <p class="text-sm font-semibold text-slate-700">Belum ada pembayaran pada filter ini</p>
                <p class="mt-1 text-xs text-slate-500">Coba ubah periode, terapis, atau metode pembayaran.</p>
            </div>
            <nav v-if="payments.links.length > 3" aria-label="Halaman riwayat pembayaran" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-3">
                <template v-for="link in payments.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-md border px-3 py-2 text-xs" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" v-html="link.label" />
                    <span v-else class="rounded-md border border-slate-100 px-3 py-2 text-xs text-slate-300" v-html="link.label"></span>
                </template>
            </nav>
        </section>
    </MainLayout>
</template>

<style scoped>
.field-label { display: block; color: #475569; font-size: 11px; font-weight: 600; }
.field-input { display: block; width: 100%; min-height: 40px; margin-top: 7px; border: 1px solid #d8dde5; border-radius: 8px; background: white; padding: 8px 10px; color: #334155; font-size: 12px; font-weight: 400; outline: none; }
.field-input:focus { border-color: #6A8EC4; box-shadow: 0 0 0 3px rgb(106 142 196 / 16%); }
</style>
