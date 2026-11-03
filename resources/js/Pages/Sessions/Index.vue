<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Activity, Camera, Check, ChevronDown, ClipboardCheck } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({
    appointments: Object,
    canFilterTherapist: Boolean,
    filters: Object,
    therapists: Array,
});
const activeId = ref(null);
const viewingReportId = ref(null);
const photoInput = ref(null);
const cameraInput = ref(null);
const filtersForm = useForm({
    period: props.filters.period,
    date: props.filters.date,
    month: props.filters.month,
    therapist_id: String(props.filters.therapist_id ?? ''),
});
const form = useForm({ activities: '', parent_notes: '', homework: '', photo: null, remove_photo: false });
const photoPreviewUrl = ref(null);
let photoReader = null;

watch(() => form.photo, (photo) => {
    const previousReader = photoReader;
    photoReader = null;
    previousReader?.abort();
    photoPreviewUrl.value = null;

    if (!photo) {
        return;
    }

    const reader = new FileReader();
    photoReader = reader;

    reader.addEventListener('load', () => {
        if (photoReader !== reader) {
            return;
        }

        photoPreviewUrl.value = typeof reader.result === 'string' ? reader.result : null;
        photoReader = null;
    });

    reader.addEventListener('error', () => {
        if (photoReader !== reader) {
            return;
        }

        photoReader = null;
        form.setError('photo', 'Foto tidak dapat dibaca untuk pratinjau. Pilih file foto lain.');
    });

    reader.readAsDataURL(photo);
});

onBeforeUnmount(() => {
    const reader = photoReader;
    photoReader = null;
    reader?.abort();
});

const exportUrl = computed(() => {
    const query = new URLSearchParams();

    Object.entries(filtersForm.data()).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            query.set(key, value);
        }
    });

    return `/sessions/export?${query.toString()}`;
});

function openReport(appointment) {
    activeId.value = activeId.value === appointment.id ? null : appointment.id;
    viewingReportId.value = null;
    form.reset();
    form.clearErrors();
    form.activities = appointment.report?.activities ?? '';
    form.parent_notes = appointment.report?.parent_notes ?? '';
    form.homework = appointment.report?.homework ?? '';
}

function editReport(appointment) {
    activeId.value = appointment.id;
    viewingReportId.value = null;
    form.reset();
    form.clearErrors();
    form.activities = appointment.report?.activities ?? '';
    form.parent_notes = appointment.report?.parent_notes ?? '';
    form.homework = appointment.report?.homework ?? '';
}

function selectPhoto(event) {
    form.photo = event.target.files?.[0] ?? null;
    form.remove_photo = false;

    if (photoInput.value && photoInput.value !== event.target) {
        photoInput.value.value = '';
    }

    if (cameraInput.value && cameraInput.value !== event.target) {
        cameraInput.value.value = '';
    }
}

function cancelSelectedPhoto() {
    form.photo = null;
    form.remove_photo = false;
    form.clearErrors('photo');

    if (photoInput.value) {
        photoInput.value.value = '';
    }

    if (cameraInput.value) {
        cameraInput.value.value = '';
    }
}

function togglePhotoRemoval(event) {
    form.remove_photo = event.target.checked;

    if (form.remove_photo) {
        form.photo = null;
    }
}

function cancelReportEdit(appointmentId) {
    activeId.value = null;
    viewingReportId.value = appointmentId;
    form.reset();
    form.clearErrors();
}

function submit(appointment) {
    const url = `/sessions/${appointment.id}/report`;
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            activeId.value = null;
            viewingReportId.value = appointment.id;
            form.reset();
        },
    };

    if (appointment.report?.status === 'submitted') {
        form.put(url, options);
        return;
    }

    form.post(url, options);
}

function toggleReportDetails(appointmentId) {
    viewingReportId.value = viewingReportId.value === appointmentId ? null : appointmentId;
}

function applyFilters() {
    filtersForm.get('/sessions', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

const time = (value) => new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date(value));
</script>

<template>
    <MainLayout>
        <div class="space-y-5">
            <header>
                <p class="text-xs font-bold uppercase tracking-wide text-clinic-600">Ruang terapis</p>
                <h1 class="mt-1 text-2xl font-bold text-clinic-900">Laporan sesi</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Catat aktivitas dan rekomendasi rumah setelah sesi berlangsung.</p>
            </header>

            <form class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5" @submit.prevent="applyFilters">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="field-label">
                        Periode
                        <select v-model="filtersForm.period" class="field-input">
                            <option value="daily">Harian</option>
                            <option value="weekly">Mingguan, mulai Senin</option>
                            <option value="monthly">Bulanan</option>
                        </select>
                    </label>
                    <label v-if="filtersForm.period !== 'monthly'" class="field-label">
                        {{ filtersForm.period === 'weekly' ? 'Tanggal dalam minggu' : 'Tanggal sesi' }}
                        <input v-model="filtersForm.date" type="date" class="field-input" required>
                    </label>
                    <label v-else class="field-label">
                        Bulan sesi
                        <input v-model="filtersForm.month" type="month" class="field-input" required>
                    </label>
                    <label v-if="canFilterTherapist" class="field-label">
                        Terapis yang ditugaskan
                        <select v-model="filtersForm.therapist_id" class="field-input">
                            <option value="">Semua terapis</option>
                            <option v-for="therapist in therapists" :key="therapist.id" :value="String(therapist.id)">{{ therapist.user.name }}</option>
                        </select>
                    </label>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <button
                            type="submit"
                            :disabled="filtersForm.processing"
                            class="min-h-11 w-full rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-not-allowed disabled:opacity-60 sm:flex-1"
                        >
                            {{ filtersForm.processing ? 'Memuat...' : 'Terapkan filter' }}
                        </button>
                        <a
                            :href="exportUrl"
                            download
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-clinic-700 px-4 text-sm font-semibold text-clinic-800 transition-colors hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 sm:flex-1"
                        >
                            Ekspor CSV
                        </a>
                    </div>
                </div>
                <p v-if="!canFilterTherapist" class="text-xs leading-5 text-slate-600">Daftar dan ekspor hanya memuat sesi yang ditugaskan kepada akun terapis Anda.</p>
                <p class="text-xs leading-5 text-slate-500">Ekspor CSV memuat laporan yang sudah dikirim dan mengikuti filter periode serta terapis di atas.</p>
            </form>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
                    <div>
                        <h2 class="text-sm font-bold text-clinic-900 sm:text-base">Sesi pada periode terpilih</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-600">{{ appointments.total }} sesi ditemukan. Laporan hanya dapat dikirim sesuai sisa kuota paket.</p>
                    </div>
                    <Activity :size="20" class="mt-0.5 shrink-0 text-clinic-700" aria-hidden="true" />
                </div>

                <div v-if="appointments.data.length" class="divide-y divide-slate-100">
                    <article v-for="appointment in appointments.data" :key="appointment.id" class="px-4 py-4 sm:px-5 sm:py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-clinic-50 text-clinic-700" aria-hidden="true">
                                    <ClipboardCheck :size="19" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="break-words text-sm font-semibold leading-5 text-slate-800">{{ appointment.patient.name }}</h3>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs leading-5 text-slate-600">
                                        <span class="font-mono text-slate-500">{{ appointment.patient.patient_code }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span>{{ appointment.therapy_type.name }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span>{{ time(appointment.starts_at) }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span>{{ appointment.therapist?.user?.name || 'Terapis belum ditentukan' }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span>{{ appointment.room?.name || 'Ruangan belum ditentukan' }}</span>
                                    </div>
                                    <a v-if="appointment.assessment" :href="`/assessments/${appointment.assessment.id}/pdf`" target="_blank" rel="noopener" class="mt-2 inline-flex min-h-11 items-center text-xs font-semibold text-clinic-700 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">Buka assessment yang terkait</a>
                                </div>
                            </div>

                            <button
                                v-if="appointment.can_submit_report"
                                type="button"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 sm:w-auto"
                                :aria-expanded="activeId === appointment.id"
                                :aria-controls="`session-report-${appointment.id}`"
                                @click="openReport(appointment)"
                            >
                                {{ activeId === appointment.id ? 'Tutup form' : 'Isi laporan' }}
                                <ChevronDown :size="16" aria-hidden="true" />
                            </button>
                            <button
                                v-else-if="appointment.report?.status === 'submitted'"
                                type="button"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-emerald-700 px-4 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 sm:w-auto"
                                :aria-expanded="viewingReportId === appointment.id"
                                :aria-controls="`session-report-details-${appointment.id}`"
                                @click="toggleReportDetails(appointment.id)"
                            >
                                <Check :size="16" aria-hidden="true" />
                                {{ viewingReportId === appointment.id ? 'Tutup hasil sesi' : 'Lihat hasil sesi' }}
                                <ChevronDown :size="16" aria-hidden="true" />
                            </button>
                            <span v-else class="inline-flex min-h-10 items-center self-start rounded-lg bg-amber-50 px-3 text-sm font-semibold text-amber-800 sm:self-auto" role="status">
                                Di luar kuota paket aktif
                            </span>
                        </div>

                        <section
                            v-if="viewingReportId === appointment.id && appointment.report?.status === 'submitted'"
                            :id="`session-report-details-${appointment.id}`"
                            class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5"
                            :aria-labelledby="`session-report-details-title-${appointment.id}`"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h4 :id="`session-report-details-title-${appointment.id}`" class="text-sm font-bold text-clinic-900">Hasil sesi</h4>
                                <div class="text-xs leading-5 text-slate-600">
                                    <p v-if="appointment.report.submitted_at">Dikirim {{ time(appointment.report.submitted_at) }}</p>
                                    <p v-if="appointment.report.edited_at" class="font-semibold text-clinic-800">
                                        Diedit {{ time(appointment.report.edited_at) }}<span v-if="appointment.report.edited_by?.name"> oleh {{ appointment.report.edited_by.name }}</span>
                                    </p>
                                </div>
                            </div>

                            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="min-w-0 sm:col-span-2">
                                    <dt class="text-xs font-semibold text-slate-600">Aktivitas yang dilakukan</dt>
                                    <dd class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-slate-800">{{ appointment.report.activities || 'Tidak ada catatan.' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold text-slate-600">Catatan untuk orang tua</dt>
                                    <dd class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-slate-800">{{ appointment.report.parent_notes || 'Tidak ada catatan.' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold text-slate-600">Latihan di rumah</dt>
                                    <dd class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-slate-800">{{ appointment.report.homework || 'Tidak ada catatan.' }}</dd>
                                </div>
                            </dl>

                            <div v-if="appointment.report.photo_url" class="mt-4">
                                <p class="text-xs font-semibold text-slate-600">Foto dokumentasi sesi</p>
                                <a :href="appointment.report.photo_url" target="_blank" rel="noopener" class="mt-2 inline-block rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                    <img :src="appointment.report.photo_url" alt="Foto dokumentasi sesi terapi" class="max-h-96 max-w-full rounded-lg border border-slate-200 object-contain">
                                </a>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                                    :aria-expanded="activeId === appointment.id"
                                    :aria-controls="`session-report-${appointment.id}`"
                                    @click="editReport(appointment)"
                                >
                                    Edit laporan
                                </button>
                            </div>
                        </section>

                        <form
                            v-if="activeId === appointment.id"
                            :id="`session-report-${appointment.id}`"
                            class="mt-4 grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5"
                            @submit.prevent="submit(appointment)"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="text-sm font-bold text-clinic-900">{{ appointment.report?.status === 'submitted' ? 'Edit laporan sesi' : 'Isi laporan sesi' }}</h4>
                                <button
                                    v-if="appointment.report?.status === 'submitted'"
                                    type="button"
                                    class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                                    @click="cancelReportEdit(appointment.id)"
                                >
                                    Batal edit
                                </button>
                            </div>

                            <div v-if="form.hasErrors" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm leading-5 text-rose-800">
                                Periksa kembali kolom yang ditandai sebelum mengirim laporan.
                            </div>

                            <p class="text-sm leading-5 text-slate-600">Catatan dan foto bersifat opsional. Isi catatan, tambahkan foto, atau keduanya.</p>

                            <label class="field-label">
                                Aktivitas yang dilakukan
                                <textarea v-model="form.activities" rows="4" class="field-input" maxlength="5000" placeholder="Aktivitas dan respons anak selama sesi"></textarea>
                                <span v-if="form.errors.activities" class="field-error">{{ form.errors.activities }}</span>
                            </label>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="field-label">
                                    Catatan untuk orang tua
                                    <textarea v-model="form.parent_notes" rows="4" class="field-input" maxlength="3000" placeholder="Catatan yang perlu diketahui keluarga"></textarea>
                                    <span v-if="form.errors.parent_notes" class="field-error">{{ form.errors.parent_notes }}</span>
                                </label>
                                <label class="field-label">
                                    Latihan di rumah
                                    <textarea v-model="form.homework" rows="4" class="field-input" maxlength="3000" placeholder="Latihan yang dapat dilakukan di rumah"></textarea>
                                    <span v-if="form.errors.homework" class="field-error">{{ form.errors.homework }}</span>
                                </label>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                <label class="field-label">
                                    Foto dokumentasi sesi
                                    <input
                                        ref="photoInput"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="field-input cursor-pointer file:mr-3 file:rounded-md file:border-0 file:bg-clinic-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-clinic-800"
                                        @change="selectPhoto"
                                    >
                                    <span class="mt-1 block text-xs font-normal leading-5 text-slate-600">Pilih JPG, PNG, atau WebP. Maksimal 5 MB.</span>
                                    <span v-if="form.errors.photo" class="field-error">{{ form.errors.photo }}</span>
                                </label>
                                <div class="sm:hidden">
                                    <input
                                        ref="cameraInput"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        capture="environment"
                                        tabindex="-1"
                                        aria-hidden="true"
                                        class="sr-only"
                                        @change="selectPhoto"
                                    >
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-clinic-700 px-4 text-sm font-semibold text-clinic-800 transition-colors hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                                        @click="cameraInput?.click()"
                                    >
                                        <Camera :size="17" aria-hidden="true" />
                                        Ambil foto dengan kamera
                                    </button>
                                </div>
                            </div>

                            <div v-if="photoPreviewUrl" class="grid gap-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-slate-600">{{ appointment.report?.photo_url ? 'Foto baru, menggantikan foto saat ini' : 'Pratinjau foto' }}</p>
                                    <button
                                        type="button"
                                        class="min-h-11 rounded-lg border border-slate-400 px-3 text-sm font-semibold text-slate-700 transition-colors hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                                        @click="cancelSelectedPhoto"
                                    >
                                        Batalkan foto
                                    </button>
                                </div>
                                <img :src="photoPreviewUrl" alt="Pratinjau foto dokumentasi yang dipilih" class="max-h-80 max-w-full rounded-lg border border-slate-200 object-contain">
                            </div>

                            <div v-else-if="appointment.report?.photo_url" class="grid gap-2">
                                <template v-if="!form.remove_photo">
                                    <p class="text-xs font-semibold text-slate-600">Foto saat ini</p>
                                    <img :src="appointment.report.photo_url" alt="Foto dokumentasi sesi saat ini" class="max-h-80 max-w-full rounded-lg border border-slate-200 object-contain">
                                </template>
                                <label class="inline-flex min-h-11 w-fit cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-slate-400 text-clinic-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                                        :checked="form.remove_photo"
                                        @change="togglePhotoRemoval"
                                    >
                                    Hapus foto saat ini
                                </label>
                                <p v-if="form.remove_photo" class="text-sm leading-5 text-slate-700">Foto saat ini akan dihapus saat perubahan disimpan.</p>
                            </div>

                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <button
                                    :disabled="form.processing"
                                    class="min-h-11 w-full rounded-lg bg-clinic-700 px-5 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                                >
                                    {{ form.processing ? 'Menyimpan...' : (appointment.report?.status === 'submitted' ? 'Simpan perubahan' : 'Kirim laporan & selesaikan sesi') }}
                                </button>
                            </div>
                        </form>
                    </article>
                </div>

                <div v-else class="grid min-h-56 place-items-center px-5 py-10 text-center">
                    <div class="max-w-sm">
                        <Activity :size="28" class="mx-auto text-slate-400" aria-hidden="true" />
                        <p class="mt-3 text-base font-semibold text-slate-800">Tidak ada sesi pada filter ini</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Coba pilih tanggal, bulan, atau terapis yang lain.</p>
                    </div>
                </div>

                <nav v-if="appointments.links.length > 3" aria-label="Halaman laporan sesi" class="flex flex-wrap justify-center gap-1 border-t border-slate-100 px-4 py-3">
                    <template v-for="link in appointments.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            :aria-current="link.active ? 'page' : undefined"
                            class="inline-flex min-h-11 items-center rounded-md border px-3 py-2 text-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                            :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            v-html="link.label"
                        />
                        <span v-else class="inline-flex min-h-11 items-center rounded-md border border-slate-100 px-3 py-2 text-xs text-slate-400" v-html="link.label"></span>
                    </template>
                </nav>
            </section>
        </div>
    </MainLayout>
</template>

<style scoped>
.field-label { display: block; color: #334155; font-size: 13px; font-weight: 600; line-height: 1.5; }
.field-input { display: block; width: 100%; min-height: 44px; margin-top: 8px; border: 1px solid #64748b; border-radius: 8px; background: white; padding: 10px 12px; color: #334155; font-size: 14px; font-weight: 400; outline: none; }
.field-input::placeholder { color: #64748b; }
.field-input:focus { border-color: #405f91; box-shadow: 0 0 0 3px #dce8f5; }
.field-error { display: block; margin-top: 5px; color: #be123c; font-size: 12px; line-height: 1.5; }
</style>
