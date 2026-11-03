<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Clock3, PackageCheck } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({
    packages: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    validity_days: 90,
    session_duration_minutes: 60,
    session_quantity: 1,
    price: 160000,
});

const packageView = ref('active');
const changingPackageId = ref(null);
const activePackages = computed(() => props.packages.filter((item) => Number(item.is_active) === 1));
const archivedPackages = computed(() => props.packages.filter((item) => Number(item.is_active) !== 1));
const visiblePackages = computed(() => packageView.value === 'active' ? activePackages.value : archivedPackages.value);
const packageGroups = computed(() => {
    const durations = [...new Set(visiblePackages.value
        .map((item) => Number(item.session_duration_minutes))
        .filter((duration) => Number.isInteger(duration) && duration > 0))]
        .sort((first, second) => first - second);

    return durations.map((duration) => ({
        duration,
        title: 'Sesi ' + durationLabel(duration),
        packages: visiblePackages.value.filter((item) => Number(item.session_duration_minutes) === duration),
    }));
});
const legacyPackages = computed(() => visiblePackages.value.filter((item) => !Number(item.session_duration_minutes)));
const averagePrice = computed(() => {
    const quantity = Number(form.session_quantity);
    const price = Number(form.price);

    return Number.isFinite(price) && quantity > 0 ? price / quantity : 0;
});

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value) || 0);

function durationLabel(duration) {
    const minutes = Number(duration);

    if (!Number.isFinite(minutes) || minutes <= 0) {
        return 'durasi belum diisi';
    }

    if (minutes % 60 === 0) {
        return (minutes / 60) + ' jam';
    }

    if (minutes > 60) {
        return Math.floor(minutes / 60) + ' jam ' + (minutes % 60) + ' menit';
    }

    return minutes + ' menit';
}

function submit() {
    form.post('/packages', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function toggleStatus(item) {
    changingPackageId.value = item.id;
    router.patch('/packages/' + item.id + '/status', {}, {
        preserveScroll: true,
        onFinish: () => {
            changingPackageId.value = null;
        },
    });
}
</script>

<template>
    <MainLayout>
        <header>
            <p class="text-xs font-semibold uppercase tracking-wide text-clinic-700">Pengaturan layanan</p>
            <h1 class="mt-1 text-2xl font-semibold text-clinic-900">Paket terapi</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Atur nama, durasi, jumlah sesi, harga, dan masa berlaku sesuai kebutuhan klinik.</p>
        </header>

        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white" aria-labelledby="add-package-heading">
            <header class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-coral-50 text-coral-700"><PackageCheck :size="18" /></span>
                    <div>
                        <h2 id="add-package-heading" class="text-sm font-semibold text-clinic-900">Buat paket baru</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-600">Isi nilai paket secara langsung. Durasi, jumlah sesi, harga, dan masa berlaku dapat disesuaikan.</p>
                    </div>
                </div>
            </header>

            <form class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_280px]" @submit.prevent="submit">
                <div class="grid content-start gap-4 sm:grid-cols-2">
                    <label class="field-label sm:col-span-2" for="package-name">
                        Nama paket
                        <input
                            id="package-name"
                            v-model="form.name"
                            type="text"
                            maxlength="255"
                            autocomplete="off"
                            placeholder="Contoh: Paket terapi wicara"
                            class="field-input mt-2"
                            :aria-invalid="Boolean(form.errors.name)"
                            :aria-describedby="form.errors.name ? 'package-name-hint package-name-error' : 'package-name-hint'"
                            required
                        >
                        <span id="package-name-hint" class="field-hint">Gunakan nama yang mudah dikenali saat transaksi.</span>
                        <span v-if="form.errors.name" id="package-name-error" class="field-error" role="alert">{{ form.errors.name }}</span>
                    </label>

                    <label class="field-label" for="package-duration">
                        Durasi per sesi
                        <span class="input-with-unit mt-2">
                            <input
                                id="package-duration"
                                v-model.number="form.session_duration_minutes"
                                type="number"
                                min="1"
                                max="65535"
                                step="1"
                                inputmode="numeric"
                                class="field-input"
                                :aria-invalid="Boolean(form.errors.session_duration_minutes)"
                                :aria-describedby="form.errors.session_duration_minutes ? 'package-duration-error' : null"
                                required
                            >
                            <span class="input-unit">menit</span>
                        </span>
                        <span v-if="form.errors.session_duration_minutes" id="package-duration-error" class="field-error" role="alert">{{ form.errors.session_duration_minutes }}</span>
                    </label>

                    <label class="field-label" for="package-quantity">
                        Jumlah sesi
                        <span class="input-with-unit mt-2">
                            <input
                                id="package-quantity"
                                v-model.number="form.session_quantity"
                                type="number"
                                min="1"
                                max="65535"
                                step="1"
                                inputmode="numeric"
                                class="field-input"
                                :aria-invalid="Boolean(form.errors.session_quantity)"
                                :aria-describedby="form.errors.session_quantity ? 'package-quantity-error' : null"
                                required
                            >
                            <span class="input-unit">sesi</span>
                        </span>
                        <span v-if="form.errors.session_quantity" id="package-quantity-error" class="field-error" role="alert">{{ form.errors.session_quantity }}</span>
                    </label>

                    <label class="field-label" for="package-price">
                        Harga paket
                        <span class="input-with-unit mt-2">
                            <span class="input-unit">Rp</span>
                            <input
                                id="package-price"
                                v-model.number="form.price"
                                type="number"
                                min="1"
                                max="4294967295"
                                step="1"
                                inputmode="numeric"
                                class="field-input"
                                :aria-invalid="Boolean(form.errors.price)"
                                :aria-describedby="form.errors.price ? 'package-price-error' : null"
                                required
                            >
                        </span>
                        <span v-if="form.errors.price" id="package-price-error" class="field-error" role="alert">{{ form.errors.price }}</span>
                    </label>

                    <label class="field-label" for="package-validity">
                        Masa berlaku
                        <span class="input-with-unit mt-2">
                            <input
                                id="package-validity"
                                v-model.number="form.validity_days"
                                type="number"
                                min="1"
                                max="65535"
                                step="1"
                                inputmode="numeric"
                                class="field-input"
                                :aria-invalid="Boolean(form.errors.validity_days)"
                                :aria-describedby="form.errors.validity_days ? 'package-validity-error' : null"
                                required
                            >
                            <span class="input-unit">hari</span>
                        </span>
                        <span v-if="form.errors.validity_days" id="package-validity-error" class="field-error" role="alert">{{ form.errors.validity_days }}</span>
                    </label>
                </div>

                <aside class="flex flex-col rounded-xl border border-slate-200 bg-[#F9F7F5] p-5 lg:sticky lg:top-24 lg:self-start">
                    <p class="text-xs font-semibold text-slate-600">Ringkasan paket</p>
                    <p class="mt-3 break-words text-base font-semibold leading-6 text-clinic-900">{{ form.name || 'Nama paket' }}</p>
                    <p class="mt-3 text-xl font-bold tabular-nums text-clinic-900">{{ money(form.price) }}</p>
                    <p class="mt-1 text-sm text-slate-700">{{ form.session_quantity || 0 }} sesi, {{ durationLabel(form.session_duration_minutes) }} per sesi</p>
                    <p class="mt-1 text-xs text-slate-600">Berlaku {{ form.validity_days || 0 }} hari sejak pembelian</p>

                    <div class="mt-4 border-t border-slate-300 pt-3">
                        <p class="text-xs text-slate-600">Rata-rata harga per sesi</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-clinic-900">{{ money(averagePrice) }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan paket' }}
                    </button>
                </aside>
            </form>
        </section>

        <section class="mt-8" aria-labelledby="packages-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="packages-heading" class="text-base font-semibold text-clinic-900">Kelola paket</h2>
                    <p class="mt-1 text-sm text-slate-600">Arsipkan paket yang tidak digunakan. Paket pasien yang sudah aktif tetap berlaku.</p>
                </div>
                <div class="flex rounded-lg border border-slate-500 bg-white p-1" role="group" aria-label="Tampilkan paket">
                    <button
                        type="button"
                        :aria-pressed="packageView === 'active'"
                        class="min-h-11 rounded-md px-3 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-clinic-700"
                        :class="packageView === 'active' ? 'bg-clinic-50 text-clinic-800' : 'text-slate-600 hover:text-slate-900'"
                        @click="packageView = 'active'"
                    >
                        Aktif ({{ activePackages.length }})
                    </button>
                    <button
                        type="button"
                        :aria-pressed="packageView === 'archived'"
                        class="min-h-11 rounded-md px-3 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-clinic-700"
                        :class="packageView === 'archived' ? 'bg-clinic-50 text-clinic-800' : 'text-slate-600 hover:text-slate-900'"
                        @click="packageView = 'archived'"
                    >
                        Arsip ({{ archivedPackages.length }})
                    </button>
                </div>
            </div>

            <div v-if="packageGroups.length" class="grid items-start gap-4 xl:grid-cols-2">
                <section v-for="group in packageGroups" :key="group.duration" class="overflow-hidden rounded-xl border border-slate-300 bg-white">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-200 bg-[#F9F7F5] px-4 py-4 sm:px-5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white text-clinic-700"><Clock3 :size="18" /></span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-clinic-900">{{ group.title }}</h3>
                                <p class="mt-1 text-xs text-slate-600">Durasi pilihan untuk satu sesi terapi.</p>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-md bg-white px-2.5 py-1.5 text-xs font-medium tabular-nums text-slate-700">{{ group.packages.length }} paket</span>
                    </header>

                    <div class="grid gap-3 p-4">
                        <article v-for="item in group.packages" :key="item.id" class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h4 class="break-words text-sm font-semibold text-slate-900">{{ item.name }}</h4>
                                    <p class="mt-1 text-xs text-slate-600">{{ item.session_quantity }} sesi, {{ durationLabel(item.session_duration_minutes) }} per sesi</p>
                                </div>
                                <p class="shrink-0 text-base font-bold tabular-nums text-clinic-900">{{ money(item.price) }}</p>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-200 pt-3 text-xs">
                                <div>
                                    <p class="text-slate-600">Rata-rata per sesi</p>
                                    <p class="mt-1 font-semibold tabular-nums text-slate-900">{{ money(Number(item.price) / Number(item.session_quantity)) }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-600">Masa berlaku</p>
                                    <p class="mt-1 font-semibold text-slate-900">{{ item.validity_days }} hari</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                :disabled="changingPackageId === item.id"
                                class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-md border border-slate-500 px-3 text-xs font-semibold text-clinic-800 transition-colors hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :aria-label="(packageView === 'active' ? 'Arsipkan ' : 'Aktifkan kembali ') + item.name"
                                @click="toggleStatus(item)"
                            >
                                {{ changingPackageId === item.id ? 'Memproses...' : packageView === 'active' ? 'Arsipkan paket' : 'Aktifkan kembali' }}
                            </button>
                        </article>
                    </div>
                </section>
            </div>

            <section v-if="legacyPackages.length" class="mt-4 rounded-xl border border-slate-300 bg-white p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-clinic-900">Paket lama</h3>
                <p class="mt-1 text-xs text-slate-600">Paket berikut masih terikat pada jenis terapi tertentu.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <article v-for="item in legacyPackages" :key="item.id" class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="break-words text-sm font-semibold text-slate-900">{{ item.name }}</h4>
                                <p class="mt-2 text-xs leading-5 text-slate-600">{{ item.items.map((therapy) => therapy.name + ': ' + therapy.session_quantity + ' sesi').join(', ') }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-bold tabular-nums text-clinic-900">{{ money(item.price) }}</p>
                        </div>
                        <button
                            type="button"
                            :disabled="changingPackageId === item.id"
                            class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-md border border-slate-500 px-3 text-xs font-semibold text-clinic-800 transition-colors hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :aria-label="(packageView === 'active' ? 'Arsipkan ' : 'Aktifkan kembali ') + item.name"
                            @click="toggleStatus(item)"
                        >
                            {{ changingPackageId === item.id ? 'Memproses...' : packageView === 'active' ? 'Arsipkan paket' : 'Aktifkan kembali' }}
                        </button>
                    </article>
                </div>
            </section>

            <div v-if="!packageGroups.length && !legacyPackages.length" class="rounded-xl border border-slate-300 bg-white px-5 py-10 text-center">
                <p class="text-sm font-semibold text-slate-800">{{ packageView === 'active' ? 'Belum ada paket aktif.' : 'Belum ada paket dalam arsip.' }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ packageView === 'active' ? 'Isi form di atas untuk membuat paket terapi.' : 'Paket yang diarsipkan akan muncul di sini.' }}</p>
            </div>
        </section>

        <section class="mt-8" aria-labelledby="services-heading">
            <div class="mb-3 flex items-end justify-between gap-4">
                <div>
                    <h2 id="services-heading" class="text-base font-semibold text-clinic-900">Layanan utama</h2>
                    <p class="mt-1 text-sm text-slate-600">Biaya konsultasi dan evaluasi di luar paket terapi.</p>
                </div>
                <span class="hidden rounded-md bg-[#F9F7F5] px-3 py-2 text-xs text-slate-700 sm:inline">{{ services.length }} layanan</span>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <article v-for="service in services" :key="service.code" class="flex min-h-32 flex-col justify-between gap-4 rounded-xl border border-slate-300 bg-white p-5 sm:flex-row sm:items-start">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">{{ service.name }}</p>
                        <p class="mt-2 max-w-xl text-sm leading-5 text-slate-700">{{ service.description }}</p>
                    </div>
                    <p class="shrink-0 text-base font-semibold tabular-nums text-clinic-900">{{ money(service.price) }}</p>
                </article>
            </div>
        </section>
    </MainLayout>
</template>

<style scoped>
.field-label {
    display: block;
    color: #334155;
    font-size: 12px;
    font-weight: 600;
}

.field-input {
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 44px;
    border: 1px solid #64748b;
    border-radius: 8px;
    background: white;
    padding: 9px 11px;
    color: #1e293b;
    font-size: 13px;
    font-weight: 400;
}

.field-input:focus-visible {
    border-color: #274b7a;
    outline: 3px solid rgb(39 75 122 / 24%);
    outline-offset: 1px;
}

.input-with-unit {
    display: flex;
    min-width: 0;
    align-items: center;
}

.input-with-unit .field-input {
    flex: 1;
}

.input-unit {
    flex: 0 0 auto;
    padding: 0 10px;
    color: #475569;
    font-size: 12px;
    font-weight: 500;
}

.field-hint {
    display: block;
    margin-top: 5px;
    color: #475569;
    font-size: 11px;
    font-weight: 400;
}

.field-error {
    display: block;
    margin-top: 5px;
    color: #9f1239;
    font-size: 11px;
    font-weight: 500;
}
</style>
