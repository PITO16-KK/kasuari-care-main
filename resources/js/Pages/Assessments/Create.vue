<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarClock } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ patients: Array, services: Array, therapists: Array, initialPatientId: Number });
const form = useForm({ patient_id: props.initialPatientId ?? '', scheduled_at: '', service_catalog_id: '', therapist_profile_id: '' });
const scheduleDate = ref('');
const scheduleTime = ref('');
const selectedService = computed(() => props.services.find((service) => service.id === Number(form.service_catalog_id)));
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

watch([scheduleDate, scheduleTime], ([date, time]) => {
    form.scheduled_at = date && isWibTime(time) ? jakartaDateTimeAsUtcIso(date, time) : '';
    if (form.scheduled_at) {
        form.clearErrors('scheduled_at');
    }
});

function submit() {
    if (!scheduleDate.value || !isWibTime(scheduleTime.value)) {
        form.setError('scheduled_at', 'Isi tanggal dan jam format 24 jam, contoh 14:05 WIB.');
        return;
    }

    form.scheduled_at = jakartaDateTimeAsUtcIso(scheduleDate.value, scheduleTime.value);
    form.post('/assessments', { preserveScroll: true });
}

function isWibTime(value) {
    return /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
}

function formatWibTimeInput(value) {
    const digits = String(value).replace(/\D/g, '').slice(0, 4);

    return digits.length > 2 ? `${digits.slice(0, 2)}:${digits.slice(2)}` : digits;
}

function updateScheduleTime(event) {
    scheduleTime.value = formatWibTimeInput(event.target.value);
}

function jakartaDateTimeAsUtcIso(date, time) {
    return new Date(`${date}T${time}:00+07:00`).toISOString();
}
</script>

<template>
    <MainLayout>
        <div class="mx-auto max-w-3xl space-y-5">
            <header>
                <p class="text-xs font-bold uppercase tracking-wide text-clinic-600">{{ initialPatientId ? 'Evaluasi berkala' : 'Pemeriksaan awal' }}</p>
                <h1 class="mt-1 text-2xl font-bold text-clinic-900">Jadwalkan assessment</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ initialPatientId ? 'Jadwalkan evaluasi lanjutan untuk pasien ini. Hasil dapat diisi setelah kasir mencatat pembayaran.' : 'Buat jadwal dan tagihan terlebih dahulu. Hasil pemeriksaan baru bisa diisi setelah kasir mencatat pembayaran.' }}</p>
                <Link href="/assessments" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-clinic-700 hover:bg-clinic-50">Kembali ke riwayat assessment</Link>
            </header>

            <form class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" @submit.prevent="submit">
                <div class="flex items-center gap-2">
                    <CalendarClock :size="18" class="text-clinic-700" />
                    <h2 class="text-sm font-bold text-clinic-900">Data jadwal dan tagihan</h2>
                </div>

                <div v-if="form.hasErrors" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Ada data yang perlu diperiksa. Pesan kesalahan ditampilkan di bawah kolom terkait.
                </div>

                <label class="field-label">
                    Pasien *
                    <select v-model="form.patient_id" class="field-input" required>
                        <option value="">Pilih pasien</option>
                        <option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.patient_code }} · {{ patient.name }}</option>
                    </select>
                    <span v-if="form.errors.patient_id" class="field-error">{{ form.errors.patient_id }}</span>
                </label>

                <label class="field-label">
                    Layanan assessment *
                    <select v-model="form.service_catalog_id" class="field-input" required>
                        <option value="">Pilih layanan</option>
                        <option v-for="service in services" :key="service.id" :value="service.id">{{ service.name }} · {{ money(service.price) }}</option>
                    </select>
                    <span v-if="form.errors.service_catalog_id" class="field-error">{{ form.errors.service_catalog_id }}</span>
                </label>

                <label class="field-label">
                    Terapis sesi assessment *
                    <select v-model="form.therapist_profile_id" class="field-input" required :disabled="!therapists.length">
                        <option value="">Pilih terapis</option>
                        <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id">{{ therapist.user.name }} Â· {{ therapist.specialty || 'Terapis' }}</option>
                    </select>
                    <span v-if="form.errors.therapist_profile_id" class="field-error">{{ form.errors.therapist_profile_id }}</span>
                </label>
                <p class="-mt-3 text-xs leading-5 text-slate-500">Sesi assessment dialokasikan selama 60 menit. Sistem akan menolak pilihan terapis yang bentrok dengan jadwal terapi atau assessment lain.</p>

                <div v-if="selectedService" class="rounded-lg border border-clinic-100 bg-clinic-50 px-4 py-3">
                    <p class="text-xs font-bold text-clinic-900">Tagihan assessment</p>
                    <p class="mt-1 text-sm font-semibold text-clinic-800">{{ selectedService.name }} · {{ money(selectedService.price) }}</p>
                    <p v-if="selectedService.description" class="mt-1 text-xs leading-5 text-clinic-700">{{ selectedService.description }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="field-label">
                        Tanggal assessment *
                        <input v-model="scheduleDate" type="date" class="field-input" required>
                    </label>
                    <label class="field-label">
                        Jam assessment (WIB, 24 jam) *
                        <input v-model="scheduleTime" type="text" inputmode="numeric" autocomplete="off" maxlength="5" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" placeholder="14:05" title="Gunakan format 24 jam, contoh 14:05" class="field-input" required @input="updateScheduleTime">
                        <span class="mt-1 block text-[10px] font-normal text-slate-500">Format HH:MM, contoh 14:05.</span>
                    </label>
                    <span v-if="form.errors.scheduled_at" role="alert" class="field-error sm:col-span-2">{{ form.errors.scheduled_at }}</span>
                </div>

                <div class="flex justify-end">
                    <button :disabled="form.processing || !services.length || !therapists.length" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-clinic-700 px-5 text-sm font-semibold text-white hover:bg-clinic-900 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ form.processing ? 'Membuat tagihan...' : 'Buat jadwal dan tagihan' }}
                    </button>
                </div>
                <p v-if="!services.length" class="text-xs text-rose-700">Belum ada layanan assessment aktif di katalog. Aktifkan layanan di katalog sebelum membuat jadwal.</p>
                <p v-if="!therapists.length" class="text-xs text-rose-700">Belum ada terapis yang dapat dipilih. Tambahkan profil terapis sebelum membuat jadwal assessment.</p>
            </form>
        </div>
    </MainLayout>
</template>

<style scoped>
.field-label { display: block; color: #334155; font-size: 12px; font-weight: 600; }
.field-input { display: block; width: 100%; min-height: 44px; margin-top: 7px; border: 1px solid #d8dde5; border-radius: 8px; background: white; padding: 9px 11px; color: #334155; font-size: 13px; font-weight: 400; outline: none; }
.field-input:focus-visible { border-color: #6A8EC4; box-shadow: 0 0 0 3px rgb(106 142 196 / 16%); }
.field-error { display: block; margin-top: 5px; color: #be123c; font-size: 11px; }
</style>
