<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, CreditCard, PackageCheck, Printer } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ sale: Object });
const paymentForm = useForm({ payment_method: 'cash', reference_number: '', bank_name: '', payment_proof: null });
const paymentProofInput = ref(null);
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);
const date = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(value)) : 'Belum dibayar';
const sessionDate = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(value)) : '';
const paymentMethods = [
    { key: 'cash', label: 'Tunai', icon: Banknote },
    { key: 'bank_transfer', label: 'Transfer bank', icon: CreditCard },
    { key: 'qris', label: 'QRIS', icon: PackageCheck },
];

watch(() => paymentForm.payment_method, (method) => {
    if (method !== 'bank_transfer') {
        paymentForm.bank_name = '';
    }
});

function selectPaymentProof(event) {
    paymentForm.payment_proof = event.target.files?.[0] ?? null;
}

function clearPaymentProof() {
    paymentForm.payment_proof = null;
    if (paymentProofInput.value) {
        paymentProofInput.value.value = '';
    }
}

function recordPayment() {
    paymentForm.post(`/sales/${props.sale.id}/payment`, { preserveScroll: true });
}

function printReceipt() {
    window.print();
}
</script>

<template>
    <MainLayout>
        <a href="/sales/create" class="inline-flex min-h-11 items-center gap-2 text-xs font-bold text-clinic-700"><ArrowLeft :size="15" /> Kembali ke kasir</a>
        <section class="receipt-print-area mx-auto mt-5 max-w-[640px] overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="receipt-header border-b border-slate-100 px-6 py-6 text-center">
                <div class="text-xs font-bold uppercase text-clinic-700">Kasuari Care</div>
                <h1 class="mt-1 text-lg font-bold text-clinic-900">{{ sale.status === 'paid' ? 'Bukti pembayaran' : 'Tagihan' }}</h1>
                <p class="mt-1 text-xs text-slate-500">{{ sale.invoice_number }}</p>
            </div>

            <div class="receipt-content space-y-5 px-6 py-5">
                <div class="receipt-meta grid grid-cols-2 gap-3 text-xs">
                    <div><div class="text-[10px] uppercase text-slate-400">Pasien</div><div class="mt-1 font-bold text-slate-800">{{ sale.patient.name }}</div><div class="mt-1 font-mono text-[10px] text-clinic-700">{{ sale.patient.patient_code }}</div></div>
                    <div class="text-right"><div class="text-[10px] uppercase text-slate-400">{{ sale.status === 'paid' ? 'Tanggal bayar' : 'Status tagihan' }}</div><div class="mt-1 font-semibold text-slate-700">{{ sale.status === 'paid' ? date(sale.paid_at) : 'Menunggu pembayaran' }}</div></div>
                </div>
                <div v-if="sale.appointment" class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Jadwal sesi terapi: <span class="font-semibold text-slate-800">{{ sessionDate(sale.appointment.starts_at) }}</span>
                </div>

                <div class="receipt-items divide-y divide-slate-100 border-y border-slate-100">
                    <div v-for="line in sale.lines" :key="line.id" class="receipt-item flex justify-between gap-3 py-3 text-xs">
                        <div><div class="font-semibold text-slate-800">{{ line.description }}</div><div class="mt-1 text-[10px] text-slate-500">{{ line.quantity }} × {{ money(line.unit_price) }}</div></div>
                        <div class="font-bold text-slate-800">{{ money(line.line_total) }}</div>
                    </div>
                </div>

                <div class="receipt-totals ml-auto max-w-[250px] space-y-2 text-xs">
                    <div class="flex justify-between text-slate-500"><span>Subtotal</span><span>{{ money(sale.subtotal) }}</span></div>
                    <div class="flex justify-between text-slate-500"><span>Diskon</span><span>- {{ money(sale.discount_total) }}</span></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3 text-sm font-bold text-clinic-900"><span>{{ sale.status === 'paid' ? 'Total lunas' : 'Total tagihan' }}</span><span>{{ money(sale.grand_total) }}</span></div>
                    <div v-if="sale.payment" class="flex justify-between text-slate-500"><span>Metode</span><span class="capitalize">{{ sale.payment.method.replace('_', ' ') }}</span></div>
                    <div v-if="sale.payment?.bank_name" class="flex justify-between text-slate-500"><span>Bank pengirim</span><span>{{ sale.payment.bank_name }}</span></div>
                    <div v-if="sale.payment?.has_proof" class="flex justify-between text-slate-500 print:hidden"><span>Bukti</span><a :href="`/payments/${sale.payment.id}/proof`" target="_blank" rel="noopener" class="font-semibold text-clinic-700 underline underline-offset-2">Lihat bukti pembayaran</a></div>
                </div>

                <form v-if="sale.status !== 'paid'" class="border-t border-slate-100 pt-4" @submit.prevent="recordPayment">
                    <h2 class="text-sm font-bold text-clinic-900">Catat pembayaran</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        <label v-for="method in paymentMethods" :key="method.key" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 text-xs font-bold" :class="paymentForm.payment_method === method.key ? 'border-clinic-600 bg-clinic-50 text-clinic-700' : 'border-slate-200 text-slate-600'">
                            <input v-model="paymentForm.payment_method" type="radio" :value="method.key" class="accent-clinic-700">
                            <component :is="method.icon" :size="15" /> {{ method.label }}
                        </label>
                    </div>
                    <label v-if="paymentForm.payment_method !== 'cash'" class="mt-3 block text-xs font-semibold text-slate-700">Nomor referensi
                        <input v-model="paymentForm.reference_number" type="text" maxlength="100" class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm font-normal">
                        <span v-if="paymentForm.errors.reference_number" class="mt-1 block text-[11px] text-rose-700">{{ paymentForm.errors.reference_number }}</span>
                    </label>
                    <label v-if="paymentForm.payment_method === 'bank_transfer'" class="mt-3 block text-xs font-semibold text-slate-700">Nama bank pengirim *
                        <input v-model="paymentForm.bank_name" type="text" maxlength="100" required placeholder="Contoh: BCA" class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm font-normal">
                        <span v-if="paymentForm.errors.bank_name" class="mt-1 block text-[11px] text-rose-700">{{ paymentForm.errors.bank_name }}</span>
                    </label>
                    <label class="mt-3 block text-xs font-semibold text-slate-700">Bukti pembayaran (opsional)
                        <input ref="paymentProofInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3 py-2 text-xs font-normal" @change="selectPaymentProof">
                        <span class="mt-1 block text-[10px] font-normal text-slate-500">JPG, PNG, WebP, atau PDF; maksimal 5 MB.</span>
                        <span v-if="paymentForm.payment_proof" class="mt-2 flex flex-wrap items-center gap-2 text-[11px] font-normal text-slate-600">
                            {{ paymentForm.payment_proof.name }}
                            <button type="button" class="min-h-9 rounded-md px-2 font-semibold text-rose-700 hover:bg-rose-50" @click="clearPaymentProof">Hapus file</button>
                        </span>
                        <span v-if="paymentForm.errors.payment_proof" class="mt-1 block text-[11px] text-rose-700">{{ paymentForm.errors.payment_proof }}</span>
                    </label>
                    <p v-if="paymentForm.errors.payment_method" role="alert" class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ paymentForm.errors.payment_method }}</p>
                    <button :disabled="paymentForm.processing" class="mt-4 min-h-11 w-full rounded-md bg-clinic-700 px-4 text-sm font-bold text-white hover:bg-clinic-900 disabled:opacity-60">{{ paymentForm.processing ? 'Mencatat pembayaran...' : `Konfirmasi pembayaran ${money(sale.grand_total)}` }}</button>
                </form>

                <div v-else class="receipt-paid-note border-t border-dashed border-slate-300 pt-4 text-center text-[10px] leading-5 text-slate-500">Pembayaran sudah tercatat.</div>

                <div v-if="sale.status === 'paid'" class="flex justify-center gap-2 print:hidden">
                    <a :href="`/sales/${sale.id}/pdf`" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-md border border-slate-200 px-4 text-xs font-bold text-slate-700">Buka PDF</a>
                    <button class="inline-flex min-h-11 items-center gap-2 rounded-md bg-clinic-700 px-4 text-xs font-bold text-white" @click="printReceipt"><Printer :size="14" /> Cetak</button>
                </div>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
@media print {
    @page {
        size: A4 portrait;
        margin: 14mm;
    }

    :global(html),
    :global(body) {
        min-width: 0 !important;
        min-height: 0 !important;
        background: #fff !important;
    }

    :global(body *) {
        visibility: hidden !important;
    }

    .receipt-print-area,
    .receipt-print-area * {
        visibility: visible !important;
    }

    .receipt-print-area {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        overflow: visible !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: #fff !important;
        box-shadow: none !important;
        color: #111827 !important;
    }

    .receipt-print-area * {
        color: #111827 !important;
        background-color: transparent !important;
        box-shadow: none !important;
    }

    .receipt-header {
        border-color: #9ca3af !important;
        padding: 0 0 14px !important;
    }

    .receipt-content {
        padding: 16px 0 0 !important;
    }

    .receipt-meta,
    .receipt-item,
    .receipt-totals {
        break-inside: avoid;
    }

    .receipt-items {
        border-color: #9ca3af !important;
    }

    .receipt-paid-note,
    .receipt-print-area form,
    .receipt-print-area button {
        display: none !important;
    }
}
</style>
