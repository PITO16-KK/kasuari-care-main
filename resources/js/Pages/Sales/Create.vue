<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Banknote, CreditCard, PackageCheck, ShoppingBag } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ patients: Array, packages: Array, patientPackages: Array, services: Array, products: Array });
const patientSearch = ref('');
const patientOptionsOpen = ref(false);
const activePatientIndex = ref(-1);
function matchingPatients(query) {
    const q = query.trim().toLocaleLowerCase('id-ID');
    return (props.patients ?? []).filter(p => !q || (p.patient_code + ' ' + p.name).toLocaleLowerCase('id-ID').includes(q)).slice(0, 15);
}
const filteredPatients = computed(() => matchingPatients(patientSearch.value));
function choosePatient(patient) {
    const changed = Number(form.patient_id) !== Number(patient.id);
    form.patient_id = patient.id;
    patientSearch.value = patient.patient_code + ' · ' + patient.name;
    patientOptionsOpen.value = false;
    activePatientIndex.value = -1;
    form.clearErrors('patient_id');
    if (changed) {
        form.items = [];
        draftError.value = '';
        draftSuccess.value = '';
    }
}
function handlePatientSearch(event) {
    patientSearch.value = event.target.value;
    patientOptionsOpen.value = true;
    if (form.patient_id) {
        form.patient_id = '';
    }
}
function openPatientOptions() { patientSearch.value = patientSearch.value; patientOptionsOpen.value = true; }
function closePatientOptions() { patientOptionsOpen.value = false; activePatientIndex.value = -1; }
const form = useForm({ patient_id: '', items: [], discount_type: 'fixed', discount_value: 0, payment_method: 'cash', reference_number: '', bank_name: '', payment_proof: null });
const paymentProofInput = ref(null);
const draftLineType = ref('package');
const draftItemId = ref('');
const draftQuantity = ref(1);
const draftError = ref('');
const draftSuccess = ref('');
const draftItems = computed(() => draftLineType.value === 'package'
    ? (props.packages ?? [])
    : draftLineType.value === 'product'
        ? (props.products ?? [])
        : (props.services ?? []).filter((item) => item.service_type === draftLineType.value));
const selectedItem = computed(() => draftItems.value.find((item) => Number(item.id) === Number(draftItemId.value)));
const patientPackages = computed(() => (props.patientPackages ?? [])
    .filter((item) => Number(item.patient_id) === Number(form.patient_id))
    .map((item) => ({
        ...item,
        expiresAt: new Date(item.expires_at),
        remainingSessions: Number(item.duration_remaining_sessions ?? 0) + Number(item.legacy_remaining_sessions ?? 0),
    })));
const packageStatusNotice = computed(() => {
    if (!patientPackages.value.length) {
        return '';
    }

    const usablePackages = patientPackages.value.filter((item) => item.expiresAt >= new Date() && item.remainingSessions > 0);
    const remainingSessions = usablePackages.reduce((total, item) => total + item.remainingSessions, 0);

    if (!usablePackages.length) {
        return 'Pasien memiliki paket sebelumnya, tetapi tidak ada kuota paket aktif. Paket baru tetap bisa dibeli.';
    }

    return `Pasien memiliki ${usablePackages.length} paket aktif dengan total ${remainingSessions} sesi tersisa. Pembelian paket baru tetap dapat dilakukan.`;
});
const quantityLabel = computed(() => draftLineType.value === 'package' ? 'Jumlah paket' : draftLineType.value === 'product' ? 'Jumlah produk' : 'Jumlah sesi');
const cartItems = computed(() => form.items.map((line) => {
    const catalog = line.line_type === 'package'
        ? props.packages
        : line.line_type === 'product'
            ? props.products
            : props.services;
    const item = (catalog ?? []).find((entry) => Number(entry.id) === Number(line.item_id));
    const unitPrice = Number(item?.price ?? 0);

    return {
        ...line,
        key: `${line.line_type}-${line.item_id}`,
        name: item?.name ?? line.name ?? 'Item tidak tersedia',
        unitPrice,
        lineTotal: unitPrice * Number(line.quantity || 0),
    };
}));
const subtotal = computed(() => cartItems.value.reduce((sum, item) => sum + item.lineTotal, 0));
const discount = computed(() => form.discount_type === 'percent'
    ? Math.round(subtotal.value * Math.min(100, Number(form.discount_value || 0)) / 100)
    : Math.min(subtotal.value, Number(form.discount_value || 0)));
const total = computed(() => Math.max(0, subtotal.value - discount.value));
const packageDetails = computed(() => {
    if (draftLineType.value !== 'package' || !selectedItem.value?.session_duration_minutes || !selectedItem.value?.session_quantity) {
        return null;
    }

    const duration = selectedItem.value.session_duration_minutes === 60 ? '1 jam' : `${selectedItem.value.session_duration_minutes} menit`;
    const unitPrice = selectedItem.value.price / selectedItem.value.session_quantity;

    const totalSessions = selectedItem.value.session_quantity * Number(draftQuantity.value || 1);

    return `${totalSessions} sesi · ${duration} per sesi · ${money(unitPrice)} rata-rata per sesi`;
});
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

watch(draftLineType, () => { draftItemId.value = ''; draftQuantity.value = 1; draftError.value = ''; draftSuccess.value = ''; });
watch(() => form.patient_id, (patientId, previousPatientId) => {
    if (previousPatientId && patientId !== previousPatientId) {
        form.items = [];
        draftError.value = '';
        draftSuccess.value = '';
    }
});
watch(() => form.payment_method, (method) => {
    if (method !== 'bank_transfer') {
        form.bank_name = '';
    }
});
function addItem() {
    draftError.value = '';
    draftSuccess.value = '';
    form.clearErrors('items');

    if (!form.patient_id) {
        draftError.value = 'Pilih pasien sebelum menambahkan item.';
        return;
    }

    if (!selectedItem.value) {
        draftError.value = 'Pilih item yang ingin ditambahkan.';
        return;
    }

    const quantity = Number(draftQuantity.value || 1);
    const existingItem = form.items.find((item) => item.line_type === draftLineType.value && Number(item.item_id) === Number(selectedItem.value.id));
    const nextQuantity = quantity + Number(existingItem?.quantity ?? 0);

    if (nextQuantity > 50) {
        draftError.value = 'Jumlah untuk item yang sama maksimal 50.';
        return;
    }

    if (existingItem) {
        form.items = form.items.map((item) => item === existingItem ? { ...item, quantity: nextQuantity } : item);
    } else {
        form.items = [...form.items, {
            line_type: draftLineType.value,
            item_id: Number(selectedItem.value.id),
            quantity,
        }];
    }

    draftSuccess.value = `${selectedItem.value.name} ditambahkan ke keranjang.`;
    draftItemId.value = '';
    draftQuantity.value = 1;
}
function removeItem(itemKey) {
    form.items = form.items.filter((item) => `${item.line_type}-${item.item_id}` !== itemKey);
    draftError.value = '';
    form.clearErrors('items');
}
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
            <p class="mt-1 text-sm text-slate-500">Tambahkan beberapa item, lalu bayar sekali dalam satu transaksi.</p>
        </div>

        <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.75fr)]">
            <form class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6" @submit.prevent="submit">
                <div class="flex items-center gap-2">
                    <ShoppingBag :size="18" class="text-clinic-700" />
                    <h2 class="text-sm font-bold text-clinic-900">Detail transaksi</h2>
                </div>

                <div class="mt-5 space-y-4">
                    <div class="space-y-1.5">
                    <label for="sale-patient-search" class="field-label">Pasien *</label>
                    <div class="relative">
                        <input id="sale-patient-search" v-model="patientSearch" type="text" role="combobox" aria-autocomplete="list" aria-controls="sale-patient-options" :aria-expanded="patientOptionsOpen" autocomplete="off" placeholder="Ketik nama atau kode pasien" class="field-input" @focus="openPatientOptions" @blur="closePatientOptions" @input="handlePatientSearch">
                        <ul v-if="patientOptionsOpen && filteredPatients.length" id="sale-patient-options" role="listbox" class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                            <li v-for="patient in filteredPatients" :key="patient.id" role="option" :aria-selected="Number(form.patient_id) === Number(patient.id)" class="cursor-pointer px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" @pointerdown.prevent="choosePatient(patient)">
                                <span class="block font-medium">{{ patient.patient_code }} · {{ patient.name }}</span>
                            </li>
                        </ul>
                        <p v-else-if="patientOptionsOpen && patientSearch.length > 0" class="absolute z-20 mt-1 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 shadow-lg">Pasien tidak ditemukan.</p>
                    </div>
                    <span v-if="form.errors.patient_id" class="field-error">{{ form.errors.patient_id }}</span>
                </div>
                    <p v-if="draftLineType === 'package' && packageStatusNotice" class="rounded-md bg-clinic-50 px-3 py-2 text-xs leading-5 text-clinic-900">{{ packageStatusNotice }}</p>

                    <section aria-labelledby="sale-item-entry-title" class="rounded-md border border-slate-200 bg-slate-50 p-3 sm:p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 id="sale-item-entry-title" class="text-xs font-bold text-slate-700">Tambah item</h3>
                            <span class="text-[11px] font-semibold text-clinic-700">{{ cartItems.length }} item di keranjang</span>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_1.5fr_120px_auto] xl:items-end">
                            <label class="field-label">
                                Jenis item
                                <select v-model="draftLineType" class="field-input">
                                    <option value="package">Paket terapi</option>
                                    <option value="session">Sesi eceran (per sesi)</option>
                                    <option value="product">Produk (buku, alat terapi, dll)</option>
                                </select>
                            </label>
                            <label class="field-label">
                                Pilih item
                                <select v-model="draftItemId" class="field-input">
                                    <option value="">Pilih item</option>
                                    <option v-for="item in draftItems" :key="item.id" :value="item.id">
                                        {{ item.name }} · {{ money(item.price) }}{{ draftLineType === 'product' ? ` (Stok: ${item.stock_quantity})` : '' }}
                                    </option>
                                </select>
                            </label>
                            <label class="field-label">
                                {{ quantityLabel }}
                                <input v-model.number="draftQuantity" type="number" min="1" max="50" class="field-input" required>
                            </label>
                            <button type="button" class="min-h-10 rounded-md bg-clinic-700 px-4 text-xs font-bold text-white hover:bg-clinic-900" @click="addItem">Tambah item</button>
                        </div>
                        <p v-if="!form.patient_id || !selectedItem" class="mt-2 text-[11px] text-slate-500">Pilih pasien dan item sebelum menambahkan.</p>
                        <p v-if="packageDetails" class="mt-3 rounded-md bg-white px-3 py-2 text-xs leading-5 text-clinic-900">{{ packageDetails }}</p>
                        <p v-else-if="selectedItem?.description" class="mt-3 rounded-md bg-white px-3 py-2 text-xs leading-5 text-slate-600">{{ selectedItem.description }}</p>
                        <p v-if="draftError" role="alert" class="mt-2 text-xs text-rose-700">{{ draftError }}</p>
                        <p v-if="draftSuccess" role="status" class="mt-2 text-xs font-medium text-emerald-700">{{ draftSuccess }}</p>
                        <p v-if="form.errors.items" role="alert" class="field-error">{{ form.errors.items }}</p>
                    </section>

                    <section aria-labelledby="sale-cart-title" class="rounded-md border border-slate-200 p-3 sm:p-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 id="sale-cart-title" class="text-xs font-bold text-slate-700">Item transaksi</h3>
                            <span class="text-[11px] text-slate-500">{{ cartItems.length }} item</span>
                        </div>
                        <p v-if="!cartItems.length" class="mt-3 text-xs text-slate-500">Belum ada item. Tambahkan item sebelum melanjutkan pembayaran.</p>
                        <div v-else class="mt-3 space-y-2">
                            <div v-for="(item, index) in cartItems" :key="item.key" class="grid gap-2 rounded-md border border-slate-100 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-800">{{ item.name }}</p>
                                    <p class="mt-1 text-[10px] text-slate-500">{{ item.line_type === 'package' ? 'Paket terapi' : item.line_type === 'session' ? 'Sesi eceran' : 'Produk' }} · {{ money(item.unitPrice) }} per item</p>
                                </div>
                                <span class="text-xs text-slate-600">Jumlah: {{ item.quantity }} <span v-if="form.errors['items.' + index + '.quantity']" class="field-error">{{ form.errors['items.' + index + '.quantity'] }}</span></span>
                                <span class="text-xs font-semibold text-slate-700 sm:text-right">{{ money(item.lineTotal) }}</span>
                                <button type="button" class="min-h-9 rounded-md px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50" :aria-label="`Hapus ${item.name} dari transaksi`" @click="removeItem(item.key)">Hapus</button>
                            </div>
                        </div>
                    </section>
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
                <button :disabled="form.processing || !cartItems.length || total < 1" class="mt-6 h-11 w-full rounded-md bg-clinic-700 text-xs font-bold text-white hover:bg-clinic-900 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ form.processing ? 'Memproses pembayaran...' : 'Konfirmasi pembayaran lunas' }}
                </button>
            </form>

            <aside class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                    <CreditCard :size="17" class="text-clinic-700" />
                    <h2 class="text-sm font-bold text-clinic-900">Ringkasan pembayaran</h2>
                </div>
                <div class="p-5">
                    <div v-if="cartItems.length" class="space-y-3">
                        <div v-for="item in cartItems" :key="item.key" class="flex justify-between gap-3 text-xs">
                            <div class="min-w-0">
                                <div class="truncate font-bold text-slate-800">{{ item.name }}</div>
                                <div class="mt-1 text-[11px] text-slate-500">{{ item.quantity }} × {{ money(item.unitPrice) }}</div>
                            </div>
                            <div class="shrink-0 font-semibold text-slate-700">{{ money(item.lineTotal) }}</div>
                        </div>
                    </div>
                    <div v-else class="py-5 text-center text-xs text-slate-400">Tambahkan item untuk melihat ringkasan.</div>
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
