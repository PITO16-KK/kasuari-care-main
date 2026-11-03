<script setup>
import { ref } from 'vue';
import { Link, router, useForm, useHttp } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, UserRoundPlus, X } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ patients: Object, filters: Object, therapyTypes: Array, therapists: Array });
const form = useForm({
    name: '',
    birth_date: '',
    gender: '',
    address: '',
    primary_whatsapp: '',
    main_complaint: '',
    school_name: '',
    medical_history: '',
    father_name: '',
    father_whatsapp: '',
    mother_name: '',
    mother_whatsapp: '',
    therapist_assignments: { TO: '', TW: '' },
});

const showingForm = ref(false);
const editingId = ref(null);
const viewingPatient = ref(null);
const patientDetails = ref(null);
const patientDetailsError = ref('');
const sessionHistoryError = ref('');
const detailsHttp = useHttp({});
const search = ref(props.filters?.q ?? '');
const status = ref(props.filters?.status ?? 'all');
let detailsRequestId = 0;

function applyFilters() {
    router.get('/patients', { q: search.value, status: status.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetForm() {
    form.reset();
    form.clearErrors();
    showingForm.value = false;
    editingId.value = null;
}

function openCreateForm() {
    form.reset();
    form.clearErrors();
    editingId.value = null;
    showingForm.value = true;
}

function openEditForm(patient) {
    const father = patient.parents.find((parent) => parent.pivot.relation === 'father');
    const mother = patient.parents.find((parent) => parent.pivot.relation === 'mother');

    form.clearErrors();
    Object.assign(form, {
        name: patient.name ?? '',
        birth_date: patient.birth_date ? String(patient.birth_date).slice(0, 10) : '',
        gender: patient.gender ?? '',
        address: patient.address ?? '',
        primary_whatsapp: patient.primary_whatsapp ?? '',
        main_complaint: patient.main_complaint ?? '',
        school_name: patient.school_name ?? '',
        medical_history: patient.medical_history ?? '',
        father_name: father?.name ?? '',
        father_whatsapp: father?.whatsapp ?? '',
        mother_name: mother?.name ?? '',
        mother_whatsapp: mother?.whatsapp ?? '',
        therapist_assignments: {
            TO: patient.therapist_assignments?.TO ?? '',
            TW: patient.therapist_assignments?.TW ?? '',
        },
    });
    editingId.value = patient.id;
    closePatientDetails();
    showingForm.value = true;
}

async function viewPatientDetails(patient) {
    detailsRequestId += 1;
    const requestId = detailsRequestId;
    detailsHttp.cancel();
    viewingPatient.value = patient;
    patientDetails.value = null;
    patientDetailsError.value = '';
    sessionHistoryError.value = '';

    try {
        const response = await detailsHttp.get(`/patients/${patient.id}/details`);

        if (requestId === detailsRequestId) {
            patientDetails.value = response;
        }
    } catch {
        if (requestId === detailsRequestId) {
            patientDetailsError.value = 'Informasi paket dan riwayat sesi tidak dapat dimuat.';
        }
    }
}

function closePatientDetails() {
    detailsRequestId += 1;
    detailsHttp.cancel();
    viewingPatient.value = null;
    patientDetails.value = null;
    patientDetailsError.value = '';
    sessionHistoryError.value = '';
}

async function loadOlderSessions() {
    const nextPageUrl = patientDetails.value?.sessions?.next_page_url;

    if (!nextPageUrl || detailsHttp.processing) {
        return;
    }

    const requestId = detailsRequestId;
    sessionHistoryError.value = '';

    try {
        const response = await detailsHttp.get(nextPageUrl);

        if (requestId === detailsRequestId && patientDetails.value) {
            patientDetails.value.sessions.data.push(...response.sessions.data);
            patientDetails.value.sessions.next_page_url = response.sessions.next_page_url;
        }
    } catch {
        if (requestId === detailsRequestId) {
            sessionHistoryError.value = 'Riwayat sesi berikutnya tidak dapat dimuat.';
        }
    }
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: resetForm,
    };

    if (editingId.value) {
        form.put(`/patients/${editingId.value}`, options);

        return;
    }

    form.post('/patients', options);
}

function isTherapistUnavailable(therapist, code) {
    const assignedPatientId = Number(therapist.assigned_patient_id ?? 0);

    if (assignedPatientId && assignedPatientId !== Number(editingId.value ?? 0)) {
        return true;
    }

    return Object.entries(form.therapist_assignments).some(([otherCode, profileId]) => {
        return otherCode !== code && Number(profileId) === Number(therapist.id);
    });
}

function archive(patient) {
    if (!window.confirm(`Arsipkan data ${patient.name}? Riwayat pasien akan tetap tersimpan.`)) {
        return;
    }

    router.delete(`/patients/${patient.id}`, { preserveScroll: true });
}

function restore(patient) {
    router.patch(`/patients/${patient.id}/restore`, {}, { preserveScroll: true });
}

function age(date) {
    if (!date) {
        return 'Belum diisi';
    }

    const birthDate = new Date(date);
    let years = new Date().getFullYear() - birthDate.getFullYear();
    const beforeBirthday = new Date().getMonth() < birthDate.getMonth()
        || (new Date().getMonth() === birthDate.getMonth() && new Date().getDate() < birthDate.getDate());

    if (beforeBirthday) {
        years -= 1;
    }

    return `${years} tahun`;
}

function formatDate(date) {
    if (!date) {
        return 'Belum diisi';
    }

    const value = new Date(`${String(date).slice(0, 10)}T12:00:00`);

    return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(value);
}

function formatDateTime(date) {
    return new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
}

function packageStatus(packageData) {
    if (packageData.status === 'completed') {
        return 'Kuota habis';
    }

    if (packageData.status === 'expired' || new Date(packageData.expires_at) < new Date()) {
        return 'Kedaluwarsa';
    }

    return 'Aktif';
}

function sessionFields(session) {
    return [
        { label: 'Aktivitas sesi', value: session.report.activities },
        { label: 'Catatan untuk orang tua', value: session.report.parent_notes },
        { label: 'Latihan di rumah', value: session.report.homework },
    ].filter((field) => field.value);
}

const parentNames = (patient) => patient.parents.map((parent) => parent.name).join(', ') || 'Belum diisi';
</script>

<template>
    <MainLayout>
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-clinic-700">Data klinik</p>
                <h1 class="mt-1 text-2xl font-semibold text-clinic-900">Pasien</h1>
                <p class="mt-2 text-sm text-slate-600">Kelola profil pasien dan informasi keluarga.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link href="/therapists" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-[#F9F7F5]">Daftar terapis</Link>
                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white hover:bg-clinic-900" @click="showingForm ? resetForm() : openCreateForm()">
                    <component :is="showingForm ? X : Plus" :size="16" />{{ showingForm ? 'Tutup form' : 'Tambah pasien' }}
                </button>
            </div>
        </header>

        <section v-if="showingForm" class="mt-6 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="patient-form-heading">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-clinic-50 text-clinic-700"><UserRoundPlus :size="18" /></span>
                <div>
                    <h2 id="patient-form-heading" class="text-base font-semibold text-clinic-900">{{ editingId ? 'Ubah data pasien' : 'Tambah pasien' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Kolom bertanda * wajib diisi.</p>
                </div>
            </div>

            <form class="mt-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="field-label xl:col-span-2">Nama anak *<input v-model="form.name" class="field-input" maxlength="160" required autocomplete="name"><span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span></label>
                    <label class="field-label">Tanggal lahir<input v-model="form.birth_date" type="date" class="field-input"><span v-if="form.errors.birth_date" class="field-error">{{ form.errors.birth_date }}</span></label>
                    <label class="field-label">Jenis kelamin<select v-model="form.gender" class="field-input"><option value="">Belum diisi</option><option value="male">Laki-laki</option><option value="female">Perempuan</option></select><span v-if="form.errors.gender" class="field-error">{{ form.errors.gender }}</span></label>
                    <label class="field-label">WhatsApp utama<input v-model="form.primary_whatsapp" type="tel" class="field-input" maxlength="30" autocomplete="tel"><span v-if="form.errors.primary_whatsapp" class="field-error">{{ form.errors.primary_whatsapp }}</span></label>
                    <label class="field-label">Sekolah / TK<input v-model="form.school_name" class="field-input" maxlength="160"><span v-if="form.errors.school_name" class="field-error">{{ form.errors.school_name }}</span></label>
                    <label class="field-label">Nama ayah<input v-model="form.father_name" class="field-input" maxlength="160"><span v-if="form.errors.father_name" class="field-error">{{ form.errors.father_name }}</span></label>
                    <label class="field-label">WhatsApp ayah<input v-model="form.father_whatsapp" type="tel" class="field-input" maxlength="30"><span v-if="form.errors.father_whatsapp" class="field-error">{{ form.errors.father_whatsapp }}</span></label>
                    <label class="field-label">Nama ibu<input v-model="form.mother_name" class="field-input" maxlength="160"><span v-if="form.errors.mother_name" class="field-error">{{ form.errors.mother_name }}</span></label>
                    <label class="field-label">WhatsApp ibu<input v-model="form.mother_whatsapp" type="tel" class="field-input" maxlength="30"><span v-if="form.errors.mother_whatsapp" class="field-error">{{ form.errors.mother_whatsapp }}</span></label>
                    <label class="field-label sm:col-span-2 xl:col-span-3">Alamat<textarea v-model="form.address" rows="2" class="field-input" maxlength="2000"></textarea><span v-if="form.errors.address" class="field-error">{{ form.errors.address }}</span></label>
                    <label class="field-label sm:col-span-2 xl:col-span-3">Keluhan utama<textarea v-model="form.main_complaint" rows="3" class="field-input" maxlength="3000"></textarea><span v-if="form.errors.main_complaint" class="field-error">{{ form.errors.main_complaint }}</span></label>
                    <label class="field-label sm:col-span-2 xl:col-span-3">Riwayat kesehatan<textarea v-model="form.medical_history" rows="3" class="field-input" maxlength="3000"></textarea><span v-if="form.errors.medical_history" class="field-error">{{ form.errors.medical_history }}</span></label>
                </div>

                <section class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-sm font-semibold text-clinic-900">Rekomendasi terapis</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Setiap anak dapat memiliki satu terapis okupasi dan satu terapis wicara. Terapis yang sama hanya dapat ditugaskan kepada satu anak.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label v-for="code in ['TO', 'TW']" :key="code" class="field-label">
                            {{ therapyTypes.find((type) => type.code === code)?.name ?? (code === 'TO' ? 'Terapi Okupasi' : 'Terapi Wicara') }}
                            <select v-model="form.therapist_assignments[code]" class="field-input">
                                <option value="">Belum ditentukan</option>
                                <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id" :disabled="isTherapistUnavailable(therapist, code)">
                                    {{ therapist.name }}{{ therapist.specialty ? ` · ${therapist.specialty}` : '' }}{{ isTherapistUnavailable(therapist, code) ? ' · sudah ditugaskan' : '' }}
                                </option>
                            </select>
                            <span v-if="form.errors['therapist_assignments.' + code]" class="field-error">{{ form.errors['therapist_assignments.' + code] }}</span>
                        </label>
                    </div>
                    <p v-if="form.errors.therapist_assignments" class="field-error">{{ form.errors.therapist_assignments }}</p>
                </section>

                <div class="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" class="h-10 rounded-lg px-4 text-sm font-medium text-slate-600 hover:bg-slate-50" @click="resetForm">Batal</button>
                    <button :disabled="form.processing" class="h-10 rounded-lg bg-clinic-700 px-5 text-sm font-semibold text-white hover:bg-clinic-900 disabled:opacity-60">{{ form.processing ? 'Menyimpan...' : editingId ? 'Simpan perubahan' : 'Simpan pasien' }}</button>
                </div>
            </form>
        </section>

        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <header class="flex flex-col justify-between gap-4 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:px-5">
                <div>
                    <h2 class="text-sm font-semibold text-clinic-900">Daftar pasien</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ patients.total }} profil pasien</p>
                </div>
                <form class="grid gap-2 sm:grid-cols-[minmax(190px,1fr)_150px]" @submit.prevent="applyFilters">
                    <label class="relative block">
                        <span class="sr-only">Cari pasien</span>
                        <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input v-model="search" class="h-10 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm outline-none focus:border-clinic-600" placeholder="Nama atau ID pasien">
                    </label>
                    <label>
                        <span class="sr-only">Filter status pasien</span>
                        <select v-model="status" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-clinic-600" @change="applyFilters">
                            <option value="all">Semua status</option>
                            <option value="active">Aktif</option>
                            <option value="archived">Diarsipkan</option>
                        </select>
                    </label>
                </form>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left">
                    <thead><tr class="bg-[#F9F7F5] text-[11px] font-semibold text-slate-500"><th class="px-5 py-3">Pasien</th><th class="px-4 py-3">Usia</th><th class="px-4 py-3">Orang tua</th><th class="px-4 py-3">Kontak</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="patient in patients.data" :key="patient.id" class="hover:bg-slate-50/70">
                            <td class="px-5 py-4"><p class="text-sm font-medium text-slate-800">{{ patient.name }}</p><p class="mt-1 font-mono text-xs text-clinic-700">{{ patient.patient_code }}</p></td>
                            <td class="px-4 py-4 text-sm text-slate-600">{{ age(patient.birth_date) }}</td>
                            <td class="max-w-48 truncate px-4 py-4 text-sm text-slate-600">{{ parentNames(patient) }}</td>
                            <td class="px-4 py-4 text-sm text-slate-600">{{ patient.primary_whatsapp || patient.parents[0]?.whatsapp || 'Belum diisi' }}</td>
                            <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="patient.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ patient.is_active ? 'Aktif' : 'Diarsipkan' }}</span></td>
                            <td class="px-4 py-4"><div class="flex justify-end gap-1">
                                <button class="icon-button" :aria-label="`Lihat data ${patient.name}`" title="Lihat detail" @click="viewPatientDetails(patient)"><Eye :size="16" /></button>
                                <button class="icon-button" :aria-label="`Ubah data ${patient.name}`" title="Ubah data" @click="openEditForm(patient)"><Pencil :size="16" /></button>
                                <button v-if="patient.is_active" class="icon-button text-rose-600 hover:bg-rose-50" :aria-label="`Arsipkan ${patient.name}`" title="Arsipkan pasien" @click="archive(patient)"><X :size="16" /></button>
                                <button v-else class="rounded-lg px-2.5 text-xs font-semibold text-clinic-700 hover:bg-clinic-50" @click="restore(patient)">Aktifkan</button>
                            </div></td>
                        </tr>
                        <tr v-if="!patients.data.length"><td colspan="6" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-700">Belum ada data pasien</p><p class="mt-1 text-xs text-slate-500">Coba ubah pencarian atau tambahkan pasien baru.</p></td></tr>
                    </tbody>
                </table>
            </div>

            <footer v-if="patients.links?.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                <p class="text-xs text-slate-500">Menampilkan {{ patients.from ?? 0 }}–{{ patients.to ?? 0 }} dari {{ patients.total }}</p>
                <nav class="flex flex-wrap gap-1" aria-label="Halaman pasien">
                    <template v-for="link in patients.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-lg border px-3 py-2 text-xs" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" v-html="link.label" />
                        <span v-else class="rounded-lg border border-slate-100 px-3 py-2 text-xs text-slate-300" v-html="link.label"></span>
                    </template>
                </nav>
            </footer>
        </section>

        <div v-if="viewingPatient" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-3 sm:p-4" @click.self="closePatientDetails">
            <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="patient-detail-heading" @keydown.esc="closePatientDetails">
                <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div><p class="font-mono text-xs text-clinic-700">{{ viewingPatient.patient_code }}</p><h2 id="patient-detail-heading" class="mt-1 text-lg font-semibold text-clinic-900">{{ viewingPatient.name }}</h2></div>
                    <button class="icon-button" aria-label="Tutup detail pasien" @click="closePatientDetails"><X :size="18" /></button>
                </header>
                <div class="grid gap-x-6 gap-y-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
                    <div><p class="detail-label">Tanggal lahir / usia</p><p class="detail-value">{{ formatDate(viewingPatient.birth_date) }} <span v-if="viewingPatient.birth_date">· {{ age(viewingPatient.birth_date) }}</span></p></div>
                    <div><p class="detail-label">Jenis kelamin</p><p class="detail-value">{{ viewingPatient.gender === 'male' ? 'Laki-laki' : viewingPatient.gender === 'female' ? 'Perempuan' : 'Belum diisi' }}</p></div>
                    <div><p class="detail-label">WhatsApp utama</p><p class="detail-value">{{ viewingPatient.primary_whatsapp || 'Belum diisi' }}</p></div>
                    <div><p class="detail-label">Sekolah / TK</p><p class="detail-value">{{ viewingPatient.school_name || 'Belum diisi' }}</p></div>
                    <div class="sm:col-span-2"><p class="detail-label">Orang tua / wali</p><p class="detail-value">{{ parentNames(viewingPatient) }}</p></div>
                    <div class="sm:col-span-2"><p class="detail-label">Alamat</p><p class="detail-value whitespace-pre-line">{{ viewingPatient.address || 'Belum diisi' }}</p></div>
                    <div class="sm:col-span-2"><p class="detail-label">Keluhan utama</p><p class="detail-value whitespace-pre-line">{{ viewingPatient.main_complaint || 'Belum diisi' }}</p></div>
                    <div class="sm:col-span-2"><p class="detail-label">Riwayat kesehatan</p><p class="detail-value whitespace-pre-line">{{ viewingPatient.medical_history || 'Belum diisi' }}</p></div>
                </div>

                <div v-if="detailsHttp.processing && !patientDetails" role="status" class="border-t border-slate-100 px-5 py-5 text-sm text-slate-600 sm:px-6">Memuat informasi paket dan riwayat sesi...</div>
                <div v-else-if="patientDetailsError" role="alert" class="border-t border-slate-100 px-5 py-5 sm:px-6">
                    <p class="text-sm text-rose-700">{{ patientDetailsError }}</p>
                    <button class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="viewPatientDetails(viewingPatient)">Coba lagi</button>
                </div>
                <template v-else-if="patientDetails">
                    <section class="border-t border-slate-100 px-5 py-5 sm:px-6" aria-labelledby="patient-packages-heading">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 id="patient-packages-heading" class="text-sm font-semibold text-clinic-900">Informasi paket</h3>
                            <p class="text-xs text-slate-500">{{ patientDetails.packages.length }} paket tercatat</p>
                        </div>
                        <p v-if="!patientDetails.packages.length" class="mt-3 text-sm text-slate-600">Belum ada paket yang tercatat untuk pasien ini.</p>
                        <div v-else class="mt-3 divide-y divide-slate-100">
                            <article v-for="packageData in patientDetails.packages" :key="packageData.id" class="py-3 first:pt-0 last:pb-0">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-slate-800">{{ packageData.name }}</h4>
                                    <span class="text-xs font-semibold" :class="packageStatus(packageData) === 'Aktif' ? 'text-emerald-700' : 'text-slate-600'">{{ packageStatus(packageData) }}</span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ formatDate(packageData.starts_at) }} – {{ formatDate(packageData.expires_at) }}</p>
                                <ul v-if="packageData.entitlements.length" class="mt-2 space-y-1">
                                    <li v-for="entitlement in packageData.entitlements" :key="entitlement.name" class="text-xs leading-5 text-slate-700">{{ entitlement.name }}: {{ entitlement.remaining_sessions }} dari {{ entitlement.granted_sessions }} sesi tersisa</li>
                                </ul>
                                <p v-else class="mt-2 text-xs text-slate-500">Rincian kuota sesi belum tersedia.</p>
                            </article>
                        </div>
                    </section>

                    <section class="border-t border-slate-100 px-5 py-5 sm:px-6" aria-labelledby="patient-session-history-heading">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 id="patient-session-history-heading" class="text-sm font-semibold text-clinic-900">Riwayat sesi terapi selesai</h3>
                            <p class="text-xs text-slate-500">{{ patientDetails.sessions.total }} sesi</p>
                        </div>
                        <p v-if="!patientDetails.sessions.total" class="mt-3 text-sm text-slate-600">Belum ada sesi terapi selesai dengan laporan yang dikirim.</p>
                        <div v-else class="mt-3 space-y-3">
                            <article v-for="session in patientDetails.sessions.data" :key="session.id" class="rounded-lg border border-slate-200 p-3.5 sm:p-4">
                                <header class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-800">{{ session.therapy_name || 'Jenis terapi' }}</h4>
                                        <p class="mt-1 text-xs leading-5 text-slate-600">{{ formatDateTime(session.starts_at) }} · Terapis: {{ session.therapist_name || 'Belum ditentukan' }}</p>
                                    </div>
                                    <p class="text-xs text-slate-500">Laporan: {{ formatDateTime(session.report.submitted_at) }}</p>
                                </header>
                                <dl v-if="sessionFields(session).length" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                                    <div v-for="field in sessionFields(session)" :key="field.label">
                                        <dt class="text-xs font-semibold text-slate-600">{{ field.label }}</dt>
                                        <dd class="mt-1 whitespace-pre-line break-words text-sm leading-5 text-slate-700">{{ field.value }}</dd>
                                    </div>
                                </dl>
                                <p v-else class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">Laporan ini tidak berisi catatan tambahan.</p>
                            </article>
                        </div>
                        <p v-if="sessionHistoryError" role="alert" class="mt-3 text-sm text-rose-700">{{ sessionHistoryError }}</p>
                        <div v-if="patientDetails.sessions.next_page_url" class="mt-4 flex justify-center">
                            <button :disabled="detailsHttp.processing" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-clinic-700 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-wait disabled:opacity-60" @click="loadOlderSessions">{{ detailsHttp.processing ? 'Memuat...' : 'Muat sesi sebelumnya' }}</button>
                        </div>
                    </section>
                </template>
                <footer class="flex justify-end border-t border-slate-100 px-5 py-4 sm:px-6"><button class="h-10 rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white hover:bg-clinic-900" @click="openEditForm(viewingPatient)">Ubah data</button></footer>
            </section>
        </div>
    </MainLayout>
</template>

<style scoped>
.field-label { display: block; color: #475569; font-size: 12px; font-weight: 600; }
.field-input { display: block; width: 100%; min-height: 42px; margin-top: 7px; border: 1px solid #d8dde5; border-radius: 8px; background: white; padding: 9px 11px; color: #334155; font-size: 13px; font-weight: 400; outline: none; }
.field-input:focus { border-color: #6A8EC4; box-shadow: 0 0 0 3px rgb(106 142 196 / 16%); }
.field-error { display: block; margin-top: 5px; color: #be123c; font-size: 11px; font-weight: 400; }
.icon-button { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 8px; color: #64748b; transition: background-color 150ms ease, color 150ms ease; }
.icon-button:hover { background: #f1f5f9; color: #334155; }
.detail-label { color: #64748b; font-size: 11px; font-weight: 600; }
.detail-value { margin-top: 5px; color: #334155; font-size: 13px; line-height: 1.55; }
</style>
