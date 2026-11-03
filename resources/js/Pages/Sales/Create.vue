<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Banknote, CreditCard, PackageCheck, ShoppingBag } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ patients: Array, therapists: Array, packages: Array, patientPackages: Array, services: Array, products: Array });
const form = useForm({ patient_id: '', therapist_profile_id: '', line_type: 'package', item_id: '', quantity: 1, discount_type: 'fixed', discount_value: 0, payment_method: 'cash', reference_number: '', bank_name: '', payment_proof: null });
const paymentProofInput = ref(null);
const items = computed(() => form.line_type === 'package'
    ? props.packages
    : form.line_type === 'product'
        ? props.products
        : props.services.filter((item) => item.service_type === form.line_type));
const selectedItem = computed(() => items.value.find((item) => item.id === Number(form.item_id)));
const patientPackage = computed(() => props.patientPackages.find((item) => Number(item.patient_id) === Number(form.patient_id)));
const remainingPackageSessions = computed(() => Number(patientPackage.value?.duration_remaining_sessions ?? 0) + Number(patientPackage.value?.legacy_remaining_sessions ?? 0));
const packageExpiresAt = computed(() => patientPackage.value ? new Date(patientPackage.value.expires_at) : null);
const packageHasRemainingTime = computed(() => packageExpiresAt.value && packageExpiresAt.value >= new Date());
const packagePurchaseBlocked = computed(() => form.line_type === 'package' && Boolean(patientPackage.value) && packageHasRemainingTime.value && remainingPackageSessions.value > 0);
const packageStatusNotice = computed(() => {
    if (!patientPackage.value) {
        return '';
    }

    if (!packageHasRemainingTime.value) {
        return 'Paket sebelumnya sudah kedaluwarsa. Paket baru bisa dibeli.';
    }

    if (remainingPackageSessions.value < 1) {
        return 'Kuota paket sebelumnya sudah habis. Paket baru bisa langsung dibeli.';
    }

    return `Masih ada ${remainingPackageSessions.value} sesi pada ${patientPackage.value.name}. Paket berlaku sampai ${new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(packageExpiresAt.value)}.`;
});
const quantityLabel = computed(() => form.line_type === 'package' ? 'Jumlah paket' : form.line_type === 'product' ? 'Jumlah produk' : 'Jumlah sesi');
const subtotal = computed(() => (selectedItem.value?.price ?? 0) * Number(form.quantity || 1));
const discount = computed(() => form.discount_type === 'percent'
    ? Math.round(subtotal.value * Math.min(100, Number(form.discount_value || 0)) / 100)
    : Math.min(subtotal.value, Number(form.discount_value || 0)));
const total = computed(() => Math.max(0, subtotal.value - discount.value));
const packageDetails = computed(() => {
    if (form.line_type !== 'package' || !selectedItem.value?.session_duration_minutes || !selectedItem.value?.session_quantity) {
        return null;
    }

    const duration = selectedItem.value.session_duration_minutes === 60 ? '1 jam' : `${selectedItem.value.session_duration_minutes} menit`;
    const unitPrice = selectedItem.value.price / selectedItem.value.session_quantity;

    const totalSessions = selectedItem.value.session_quantity * Number(form.quantity || 1);

    return `${totalSessions} sesi · ${duration} per sesi · ${money(unitPrice)} rata-rata per sesi`;
});
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

watch(() => form.line_type, () => { form.item_id = ''; form.quantity = 1; });
watch(() => form.payment_method, (method) => {
    if (method !== 'bank_transfer') {
        form.bank_name = '';
    }
});
function selectPaymentProof(event) {
    form.payment_proof = event.target.files?.[0] ?? null;
}
function clearPaymentProof() {
    form.payment_proof = null;
    if (paymentProofInput.value) {
        paymentProofInput.value.value = '';
    }
}
function submit() { form.post('/sales'); }
</script>

<template>
    <MainLayout>
        <div>
            <p class="text-xs font-bold uppercase text-clinic-600">Kasir / Keuangan</p>
            <h1 class="mt-1 text-2xl font-bold text-clinic-900">Kasir &amp; pembayaran</h1>
            <p class="mt-1 text-sm text-slate-500">Transaksi dicatat lunas dan invoice dibuat otomatis.</p>
        </div>

        <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.75fr)]">
            <form class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6" @submit.prevent="submit">
                <div class="flex items-center gap-2">
                    <ShoppingBag :size="18" class="text-clinic-700" />
                    <h2 class="text-sm font-bold text-clinic-900">Detail transaksi</h2>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="field-label sm:col-span-2">
                        Pasien *
                        <select v-model="form.patient_id" class="field-input" required>
                            <option value="">Pilih pasien</option>
                            <option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.patient_code }} · {{ patient.name }}</option>
                        </select>
                        <span v-if="form.errors.patient_id" class="field-error">{{ form.errors.patient_id }}</span>
                    </label>
                    <p v-if="form.line_type === 'package' && packageStatusNotice" class="sm:col-span-2 rounded-md px-3 py-2 text-xs leading-5" :class="packagePurchaseBlocked ? 'bg-amber-50 text-amber-800' : 'bg-clinic-50 text-clinic-900'">{{ packageStatusNotice }}</p>
                    <label class="field-label sm:col-span-2">
                        Terapis terkait (opsional)
                        <select v-model="form.therapist_profile_id" class="field-input">
                            <option value="">Tanpa atribusi terapis</option>
                            <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id">{{ therapist.user.name }}</option>
                        </select>
                        <span v-if="form.errors.therapist_profile_id" class="field-error">{{ form.errors.therapist_profile_id }}</span>
                    </label>
                    <label class="field-label">
                        Jenis item
                        <select v-model="form.line_type" class="field-input">
                            <option value="package">Paket terapi</option>
                            <option value="session">Sesi eceran</option>
                            <option value="product">Produk / media</option>
                        </select>
                    </label>
                    <label class="field-label">
                        Pilih item
                        <select v-model="form.item_id" class="field-input" required>
                            <option value="">Pilih item</option>
                            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }} · {{ money(item.price) }}</option>
                        </select>
                        <span v-if="form.errors.item_id" class="field-error">{{ form.errors.item_id }}</span>
                    </label>
                    <p v-if="packageDetails" class="sm:col-span-2 rounded-md bg-clinic-50 px-3 py-2 text-xs leading-5 text-clinic-900">{{ packageDetails }}</p>
                    <p v-else-if="selectedItem?.description" class="sm:col-span-2 rounded-md bg-coral-50 px-3 py-2 text-xs leading-5 text-coral-700">{{ selectedItem.description }}</p>
                    <label class="field-label">
                        {{ quantityLabel }}
                        <input v-model.number="form.quantity" type="number" min="1" max="50" class="field-input" required>
                        <span v-if="form.errors.quantity" class="field-error">{{ form.errors.quantity }}</span>
                    </label>
                    <label class="field-label">
                        Harga satuan
                        <div class="field-readonly">{{ money(selectedItem?.price) }}</div>
                    </label>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="text-xs font-bold text-slate-700">Diskon</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="field-label">
                            Tipe diskon
                            <select v-model="form.discount_type" class="field-input">
                                <option value="fixed">Nominal rupiah</option>
                                <option value="percent">Persentase</option>
                            </select>
                        </label>
                        <label class="field-label">
                            Nilai diskon
                            <input v-model.number="form.discount_value" type="number" min="0" :max="form.discount_type === 'percent' ? 100 : subtotal" class="field-input">
                            <span v-if="form.errors.discount_value" class="field-error">{{ form.errors.discount_value }}</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="text-xs font-bold text-slate-700">Pembayaran lunas</h3>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        <label v-for="method in [{ key: 'cash', label: 'Tunai', icon: Banknote }, { key: 'bank_transfer', label: 'Transfer bank', icon: CreditCard }, { key: 'qris', label: 'QRIS', icon: PackageCheck }]" :key="method.key" class="flex h-12 cursor-pointer items-center gap-2 rounded-md border px-3 text-xs font-bold" :class="form.payment_method === method.key ? 'border-clinic-600 bg-clinic-50 text-clinic-700' : 'border-slate-200 text-slate-600'">
                            <input v-model="form.payment_method" type="radio" :value="method.key" class="accent-clinic-700">
                            <component :is="method.icon" :size="16" />{{ method.label }}
                        </label>
                    </div>
                    <label v-if="form.payment_method !== 'cash'" class="field-label mt-4">
                        Nomor referensi
                        <input v-model="form.reference_number" class="field-input" maxlength="100">
                        <span v-if="form.errors.reference_number" class="field-error">{{ form.errors.reference_number }}</span>
                    </label>
                    <label v-if="form.payment_method === 'bank_transfer'" class="field-label mt-4">
                        Nama bank pengirim *
                        <input v-model="form.bank_name" class="field-input" maxlength="100" required placeholder="Contoh: BCA">
                        <span v-if="form.errors.bank_name" class="field-error">{{ form.errors.bank_name }}</span>
                    </label>
                    <label class="field-label mt-4">
                        Bukti pembayaran (opsional)
                        <input ref="paymentProofInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="field-input" @change="selectPaymentProof">
                        <span class="mt-1 block text-[10px] font-normal text-slate-500">JPG, PNG, WebP, atau PDF; maksimal 5 MB.</span>
                        <span v-if="form.payment_proof" class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-slate-600">
                            {{ form.payment_proof.name }}
                            <button type="button" class="min-h-9 rounded-md px-2 font-semibold text-rose-700 hover:bg-rose-50" @click="clearPaymentProof">Hapus file</button>
                        </span>
                        <span v-if="form.errors.payment_proof" class="field-error">{{ form.errors.payment_proof }}</span>
                    </label>
                </div>

                <div v-if="form.errors.patient_id" class="mt-4 text-xs text-rose-600">{{ form.errors.patient_id }}</div>
                <button :disabled="form.processing || !selectedItem || total < 1 || packagePurchaseBlocked" class="mt-6 h-11 w-full rounded-md bg-clinic-700 text-xs font-bold text-white hover:bg-clinic-900 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ form.processing ? 'Memproses pembayaran...' : packagePurchaseBlocked ? 'Kuota paket masih tersedia' : 'Konfirmasi pembayaran lunas' }}
                </button>
            </form>

            <aside class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                    <CreditCard :size="17" class="text-clinic-700" />
                    <h2 class="text-sm font-bold text-clinic-900">Ringkasan pembayaran</h2>
                </div>
                <div class="p-5">
                    <div v-if="selectedItem" class="flex justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold text-slate-800">{{ selectedItem.name }}</div>
                            <div class="mt-1 text-[11px] text-slate-500">{{ form.quantity }} × {{ money(selectedItem.price) }}</div>
                        </div>
                        <div class="text-xs font-semibold text-slate-700">{{ money(subtotal) }}</div>
                    </div>
                    <div v-else class="py-5 text-center text-xs text-slate-400">Pilih item untuk melihat ringkasan.</div>
                    <div class="mt-5 space-y-3 border-t border-slate-100 pt-4 text-xs">
                        <div class="flex justify-between text-slate-500"><span>Subtotal</span><span>{{ money(subtotal) }}</span></div>
                        <div class="flex justify-between text-slate-500"><span>Diskon</span><span>- {{ money(discount) }}</span></div>
                        <div class="flex justify-between border-t border-slate-100 pt-3 text-sm font-bold text-clinic-900"><span>Total dibayar</span><span>{{ money(total) }}</span></div>
                    </div>
                    <div class="mt-5 rounded-md bg-coral-50 p-3 text-[11px] leading-5 text-coral-700">Pembayaran harus lunas di awal. Paket diaktifkan otomatis setelah transaksi berhasil.</div>
                </div>
            </aside>
        </div>
    </MainLayout>
</template>

<style scoped>
.field-label { display: block; font-size: 11px; font-weight: 700; color: #475569; }
.field-input,.field-readonly { display: block; width: 100%; min-height: 40px; margin-top: 7px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; padding: 8px 10px; font-size: 12px; font-weight: 400; outline: none; }
.field-readonly { background: #f8fafc; color: #475569; }
.field-input:focus { border-color: #405f91; box-shadow: 0 0 0 3px #dce8f5; }
.field-error { display: block; margin-top: 4px; color: #be123c; font-size: 10px; }
</style>
