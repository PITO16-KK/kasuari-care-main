<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowUpRight, FileText, Receipt, UsersRound } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({
    expenses: Object,
    payrolls: Object,
    therapists: Array,
    canManagePayroll: Boolean,
    today: String,
    accountingPeriodMonth: String,
    expenseSummary: Object,
    payrollSummary: Object,
    currentPayrollPeriod: String,
});

const expenseProofInput = ref(null);
const payrollProofInput = ref(null);
const periodFilter = useForm({ month: props.accountingPeriodMonth });
const expenseForm = useForm({
    name: '',
    amount: '',
    expense_date: props.today,
    proof: null,
});
const payrollForm = useForm({
    therapist_profile_id: props.therapists[0]?.id ?? '',
    payroll_period: props.currentPayrollPeriod,
    amount: '',
    payment_date: props.today,
    proof: null,
});

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value ?? 0));

const date = (value) => new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
}).format(new Date(`${value}T12:00:00+07:00`));

const formatAccountingPeriod = (value) => {
    if (!value) {
        return '';
    }

    const [year, month] = value.split('-').map(Number);
    const start = new Date(Date.UTC(year, month - 2, 20, 12));
    const end = new Date(Date.UTC(year, month - 1, 19, 12));
    const formatter = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'UTC',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return `${formatter.format(start)} – ${formatter.format(end)}`;
};

const selectedAccountingPeriod = computed(() => formatAccountingPeriod(periodFilter.month));
const selectedPayrollPeriod = computed(() => formatAccountingPeriod(payrollForm.payroll_period));

function applyAccountingPeriod() {
    periodFilter.get('/finance/records', {
        preserveScroll: true,
        replace: true,
    });
}

function clearFileInput(input) {
    if (input.value) {
        input.value.value = '';
    }
}

function submitExpense() {
    expenseForm.post('/finance/expenses', {
        preserveScroll: true,
        onSuccess: () => {
            expenseForm.reset();
            clearFileInput(expenseProofInput);
        },
    });
}

function submitPayroll() {
    payrollForm.post('/finance/payroll', {
        preserveScroll: true,
        onSuccess: () => {
            payrollForm.reset();
            clearFileInput(payrollProofInput);
        },
    });
}
</script>

<template>
    <MainLayout>
        <header>
            <p class="text-xs font-semibold uppercase tracking-wide text-clinic-700">Keuangan</p>
            <h1 class="mt-1 text-2xl font-semibold text-clinic-900">Pengeluaran dan penggajian</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Catat biaya klinik dan pembayaran gaji terapis. Periode pembukuan berjalan dari tanggal 20 sampai 19 bulan berikutnya.</p>
        </header>

        <form class="mt-5 flex flex-col gap-3 rounded-xl border border-[#e9e6de] bg-white p-4 shadow-sm sm:flex-row sm:items-end sm:justify-between" @submit.prevent="applyAccountingPeriod">
            <label class="block text-sm font-medium text-slate-700">
                Periode pembukuan
                <input v-model="periodFilter.month" type="month" required class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-clinic-700 focus:outline-none focus:ring-2 focus:ring-clinic-100 sm:w-56">
                <span class="mt-1 block text-xs font-normal text-slate-500">{{ selectedAccountingPeriod }}</span>
            </label>
            <button :disabled="periodFilter.processing" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 disabled:opacity-60">
                {{ periodFilter.processing ? 'Memuat...' : 'Tampilkan periode' }}
            </button>
        </form>

        <section class="mt-4 grid gap-3 sm:grid-cols-2" :class="payrollSummary ? 'xl:grid-cols-2' : 'xl:grid-cols-1'" aria-label="Ringkasan pengeluaran periode">
            <article class="rounded-xl border border-[#e9e6de] bg-white p-4 sm:p-5">
                <p class="text-xs font-medium text-slate-500">Pengeluaran periode <span class="font-normal">({{ expenseSummary.count }} catatan)</span></p>
                <p class="mt-1 text-xs text-slate-500">{{ selectedAccountingPeriod }}</p>
                <p class="mt-2 text-xl font-semibold text-clinic-900">{{ money(expenseSummary.amount) }}</p>
            </article>
            <article v-if="payrollSummary" class="rounded-xl border border-[#e9e6de] bg-white p-4 sm:p-5">
                <p class="text-xs font-medium text-slate-500">Gaji dibayar periode <span class="font-normal">({{ payrollSummary.count }} pembayaran)</span></p>
                <p class="mt-1 text-xs text-slate-500">{{ selectedAccountingPeriod }}</p>
                <p class="mt-2 text-xl font-semibold text-clinic-900">{{ money(payrollSummary.amount) }}</p>
            </article>
        </section>

        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            <section aria-labelledby="expense-form-title" class="rounded-xl border border-[#e9e6de] bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-clinic-50 text-clinic-700" aria-hidden="true"><Receipt :size="19" /></span>
                    <div>
                        <h2 id="expense-form-title" class="text-base font-semibold text-clinic-900">Catat pengeluaran</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-600">Isi nama biaya, jumlah, dan tanggal pengeluaran.</p>
                    </div>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submitExpense">
                    <label class="block text-sm font-medium text-slate-700">
                        Nama pengeluaran
                        <input v-model="expenseForm.name" type="text" maxlength="150" required autocomplete="off" placeholder="Contoh: listrik atau perlengkapan terapi" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-clinic-700 focus:outline-none focus:ring-2 focus:ring-clinic-100" :aria-invalid="Boolean(expenseForm.errors.name)">
                        <span v-if="expenseForm.errors.name" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ expenseForm.errors.name }}</span>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Nominal
                            <input v-model="expenseForm.amount" type="number" min="1" max="999999999999" step="1" required inputmode="numeric" placeholder="0" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-clinic-700 focus:outline-none focus:ring-2 focus:ring-clinic-100" :aria-invalid="Boolean(expenseForm.errors.amount)">
                            <span v-if="expenseForm.errors.amount" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ expenseForm.errors.amount }}</span>
                        </label>
                        <label class="block text-sm font-medium text-slate-700">
                            Tanggal pengeluaran
                            <input v-model="expenseForm.expense_date" type="date" required class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-clinic-700 focus:outline-none focus:ring-2 focus:ring-clinic-100" :aria-invalid="Boolean(expenseForm.errors.expense_date)">
                            <span v-if="expenseForm.errors.expense_date" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ expenseForm.errors.expense_date }}</span>
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-slate-700">
                        Bukti pengeluaran <span class="font-normal text-slate-500">(opsional)</span>
                        <input ref="expenseProofInput" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1.5 block min-h-11 w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:min-h-11 file:border-0 file:border-r file:border-slate-300 file:bg-slate-50 file:px-3 file:text-xs file:font-semibold file:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" :aria-invalid="Boolean(expenseForm.errors.proof)" @change="expenseForm.proof = $event.target.files?.[0] ?? null">
                        <span class="mt-1 block text-xs text-slate-500">PDF atau gambar, maksimal 5 MB.</span>
                        <span v-if="expenseForm.errors.proof" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ expenseForm.errors.proof }}</span>
                    </label>

                    <div v-if="expenseForm.progress" role="status" class="text-xs text-slate-600">
                        Mengunggah bukti {{ expenseForm.progress.percentage }}%
                        <progress class="mt-1 block h-2 w-full accent-clinic-700" :value="expenseForm.progress.percentage" max="100" aria-label="Progres unggah bukti pengeluaran"></progress>
                    </div>

                    <button type="submit" :disabled="expenseForm.processing" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-wait disabled:opacity-60">
                        {{ expenseForm.processing ? 'Menyimpan...' : 'Simpan pengeluaran' }}
                    </button>
                </form>
            </section>

            <section v-if="canManagePayroll" aria-labelledby="payroll-form-title" class="rounded-xl border border-[#e9e6de] bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-coral-50 text-coral-700" aria-hidden="true"><UsersRound :size="19" /></span>
                    <div>
                        <h2 id="payroll-form-title" class="text-base font-semibold text-clinic-900">Catat penggajian terapis</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-600">Bagian ini hanya dapat diisi oleh owner.</p>
                    </div>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submitPayroll">
                    <label class="block text-sm font-medium text-slate-700">
                        Terapis
                        <select v-model="payrollForm.therapist_profile_id" required :disabled="!therapists.length" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-clinic-700 focus:outline-none focus:ring-2 focus:ring-clinic-100 disabled:bg-slate-100" :aria-invalid="Boolean(payrollForm.errors.therapist_profile_id)">
                            <option value="" disabled>Pilih terapis</option>
                            <option v-for="therapist in therapists" :key="therapist.id" :value="String(therapist.id)">{{ therapist.name }}</option>
                        </select>
                        <span v-if="payrollForm.errors.therapist_profile_id" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ payrollForm.errors.therapist_profile_id }}</span>
                        <span v-else-if="!therapists.length" class="mt-1 block text-xs text-slate-500">Belum ada terapis aktif.</span>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Periode gaji (20–19)
                            <input v-model="payrollForm.payroll_period" type="month" required class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-clinic-700 focus:outline-none focus:ring-2 focus:ring-clinic-100" :aria-invalid="Boolean(payrollForm.errors.payroll_period)">
                            <span class="mt-1 block text-xs font-normal text-slate-500">{{ selectedPayrollPeriod }}</span>
                            <span v-if="payrollForm.errors.payroll_period" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ payrollForm.errors.payroll_period }}</span>
                        </label>
                        <label class="block text-sm font-medium text-slate-700">
                            Tanggal pembayaran
                            <input v-model="payrollForm.payment_date" type="date" required class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-clinic-700 focus:outline-none focus:ring-2 focus:ring-clinic-100" :aria-invalid="Boolean(payrollForm.errors.payment_date)">
                            <span v-if="payrollForm.errors.payment_date" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ payrollForm.errors.payment_date }}</span>
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-slate-700">
                        Nominal gaji
                        <input v-model="payrollForm.amount" type="number" min="1" max="999999999999" step="1" required inputmode="numeric" placeholder="0" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-clinic-700 focus:outline-none focus:ring-2 focus:ring-clinic-100" :aria-invalid="Boolean(payrollForm.errors.amount)">
                        <span v-if="payrollForm.errors.amount" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ payrollForm.errors.amount }}</span>
                    </label>

                    <label class="block text-sm font-medium text-slate-700">
                        Bukti pembayaran <span class="font-normal text-slate-500">(opsional)</span>
                        <input ref="payrollProofInput" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1.5 block min-h-11 w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:min-h-11 file:border-0 file:border-r file:border-slate-300 file:bg-slate-50 file:px-3 file:text-xs file:font-semibold file:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" :aria-invalid="Boolean(payrollForm.errors.proof)" @change="payrollForm.proof = $event.target.files?.[0] ?? null">
                        <span class="mt-1 block text-xs text-slate-500">PDF atau gambar, maksimal 5 MB.</span>
                        <span v-if="payrollForm.errors.proof" class="mt-1 block text-xs font-medium text-rose-700" role="alert">{{ payrollForm.errors.proof }}</span>
                    </label>

                    <div v-if="payrollForm.progress" role="status" class="text-xs text-slate-600">
                        Mengunggah bukti {{ payrollForm.progress.percentage }}%
                        <progress class="mt-1 block h-2 w-full accent-clinic-700" :value="payrollForm.progress.percentage" max="100" aria-label="Progres unggah bukti pembayaran gaji"></progress>
                    </div>

                    <button type="submit" :disabled="payrollForm.processing || !therapists.length" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ payrollForm.processing ? 'Menyimpan...' : 'Simpan penggajian' }}
                    </button>
                </form>
            </section>
        </div>

        <section aria-labelledby="expense-history-title" class="mt-8 overflow-hidden rounded-xl border border-[#e9e6de] bg-white shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-[#efede6] px-4 py-4 sm:px-5">
                <div>
                    <h2 id="expense-history-title" class="text-base font-semibold text-clinic-900">Riwayat pengeluaran</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ expenses.total }} catatan pengeluaran</p>
                </div>
            </header>

            <div v-if="expenses.data.length" class="divide-y divide-slate-100">
                <article v-for="expense in expenses.data" :key="expense.id" class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-semibold text-slate-800">{{ expense.name }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ date(expense.expense_date) }}<span v-if="expense.created_by"> · Dicatat {{ expense.created_by }}</span></p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:justify-end">
                        <span class="text-sm font-semibold tabular-nums text-clinic-900">{{ money(expense.amount) }}</span>
                        <a v-if="expense.proof_url" :href="expense.proof_url" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2 text-xs font-semibold text-clinic-700 underline underline-offset-2 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                            <FileText :size="14" aria-hidden="true" /> Lihat bukti
                        </a>
                    </div>
                </article>
            </div>
            <div v-else class="px-5 py-12 text-center">
                <p class="text-sm font-semibold text-slate-700">Belum ada pengeluaran tercatat</p>
                <p class="mt-1 text-sm text-slate-500">Pengeluaran yang disimpan akan muncul di sini.</p>
            </div>

            <nav v-if="expenses.links.length > 3" aria-label="Halaman riwayat pengeluaran" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-3">
                <template v-for="link in expenses.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-md border px-3 py-2 text-xs" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" v-html="link.label" />
                    <span v-else class="rounded-md border border-slate-100 px-3 py-2 text-xs text-slate-300" v-html="link.label"></span>
                </template>
            </nav>
        </section>

        <section v-if="canManagePayroll && payrolls" aria-labelledby="payroll-history-title" class="mt-5 overflow-hidden rounded-xl border border-[#e9e6de] bg-white shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-[#efede6] px-4 py-4 sm:px-5">
                <div>
                    <h2 id="payroll-history-title" class="text-base font-semibold text-clinic-900">Riwayat penggajian terapis</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ payrolls.total }} pembayaran tercatat</p>
                </div>
            </header>

            <div v-if="payrolls.data.length" class="divide-y divide-slate-100">
                <article v-for="payroll in payrolls.data" :key="payroll.id" class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-semibold text-slate-800">{{ payroll.therapist_name }}</h3>
                        <p class="mt-1 text-xs text-slate-500">Gaji {{ formatAccountingPeriod(payroll.payroll_period) }} · Dibayar {{ date(payroll.payment_date) }}<span v-if="payroll.created_by"> · Dicatat {{ payroll.created_by }}</span></p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:justify-end">
                        <span class="text-sm font-semibold tabular-nums text-clinic-900">{{ money(payroll.amount) }}</span>
                        <a v-if="payroll.proof_url" :href="payroll.proof_url" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2 text-xs font-semibold text-clinic-700 underline underline-offset-2 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                            <FileText :size="14" aria-hidden="true" /> Lihat bukti
                        </a>
                    </div>
                </article>
            </div>
            <div v-else class="px-5 py-12 text-center">
                <p class="text-sm font-semibold text-slate-700">Belum ada penggajian tercatat</p>
                <p class="mt-1 text-sm text-slate-500">Pembayaran gaji yang disimpan akan muncul di sini.</p>
            </div>

            <nav v-if="payrolls.links.length > 3" aria-label="Halaman riwayat penggajian" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-3">
                <template v-for="link in payrolls.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-md border px-3 py-2 text-xs" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" v-html="link.label" />
                    <span v-else class="rounded-md border border-slate-100 px-3 py-2 text-xs text-slate-300" v-html="link.label"></span>
                </template>
            </nav>
        </section>
    </MainLayout>
</template>
