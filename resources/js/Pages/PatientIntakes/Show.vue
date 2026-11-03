<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, CircleAlert, Link2, UserRoundPlus } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ submission: Object, canManage: Boolean });

const responseGroups = [
    { key: 'child_data', title: 'Data anak', fields: [['child_name', 'Nama anak'], ['birth_date', 'Tanggal lahir'], ['current_address', 'Alamat/tempat tinggal']] },
    { key: 'mother_data', title: 'Data ibu', fields: [['contact_details', 'Nama, umur, pendidikan, dan WhatsApp ibu'], ['occupation', 'Pekerjaan ibu']] },
    { key: 'father_data', title: 'Data ayah', fields: [['contact_details', 'Nama, umur, pendidikan, dan WhatsApp ayah'], ['occupation', 'Pekerjaan ayah']] },
    { key: 'family_context', title: 'Keluarga dan lingkungan', fields: [['siblings', 'Saudara'], ['lives_with_parents', 'Tinggal dengan orang tua'], ['primary_companions', 'Teman aktivitas sehari-hari'], ['family_history', 'Riwayat keluarga'], ['referral_source', 'Sumber informasi/rujukan']] },
    { key: 'development_history', title: 'Perkembangan dan fungsi', fields: [['daily_language', 'Bahasa sehari-hari'], ['independence_skills', 'Kemandirian'], ['communicates_needs', 'Menyampaikan keinginan'], ['parent_perceived_causes', 'Dugaan orang tua tentang penyebab masalah'], ['concerns_first_noticed', 'Pertama kali masalah disadari'], ['previous_therapies', 'Riwayat terapi sebelumnya'], ['feeding', 'Masalah makan'], ['communication_and_storytelling', 'Komunikasi dan bercerita'], ['speech_clarity', 'Kejelasan bicara'], ['attention_duration', 'Lama fokus'], ['reading_and_writing', 'Membaca dan menulis'], ['understands_simple_instructions', 'Memahami perintah sederhana'], ['tantrums_and_triggers', 'Tantrum dan pemicu'], ['walking', 'Riwayat berjalan'], ['crawling', 'Riwayat merangkak']] },
    { key: 'health_history', title: 'Riwayat pemeriksaan dan kesehatan', fields: [['previous_exam', 'Pernah diperiksa dokter/psikolog'], ['previous_exam_provider', 'Tempat dan pemeriksa'], ['doctor_or_psychologist_diagnosis', 'Diagnosis dokter/psikolog'], ['medical_conditions', 'Riwayat penyakit']] },
];

const statusLabels = {
    pending: 'Menunggu tinjauan',
    verified: 'Terverifikasi',
    needs_review: 'Perlu ditinjau',
};

const statusForm = useForm({ verification_status: props.submission.verification_status });
const linkForm = useForm({ patient_code: '' });
const createPatientForm = useForm({
    name: props.submission.child_data?.child_name ?? '',
    birth_date: /^\d{4}-\d{2}-\d{2}$/.test(props.submission.child_data?.birth_date ?? '')
        ? props.submission.child_data.birth_date
        : '',
    address: props.submission.child_data?.current_address ?? '',
    father_name: '',
    father_whatsapp: '',
    mother_name: '',
    mother_whatsapp: '',
});

function formatAnswer(value) {
    if (value === null || value === undefined || value === '') {
        return 'Tidak diisi';
    }

    if (Array.isArray(value)) {
        return value.length ? value.map((item) => String(item)).join(', ') : 'Tidak diisi';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function formatDate(value) {
    if (!value) {
        return 'Waktu tidak tersedia';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}

function updateStatus(status) {
    statusForm.verification_status = status;
    statusForm.patch(`/patient-intakes/${props.submission.id}/status`, { preserveScroll: true });
}

function linkPatient() {
    linkForm.post(`/patient-intakes/${props.submission.id}/link`, { preserveScroll: true });
}

function createPatient() {
    createPatientForm.post(`/patient-intakes/${props.submission.id}/create-patient`, { preserveScroll: true });
}
</script>

<template>
    <MainLayout>
        <div class="space-y-5">
            <header class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <Link href="/patient-intakes" class="inline-flex min-h-9 items-center gap-2 text-xs font-semibold text-clinic-700 hover:text-clinic-900"><ArrowLeft :size="15" /> Kembali ke daftar intake</Link>
                    <p class="mt-3 text-xs font-bold uppercase tracking-wide text-clinic-600">Respons Google Form</p>
                    <h1 class="mt-1 text-2xl font-bold text-clinic-900">{{ submission.child_data?.child_name || 'Nama anak belum diisi' }}</h1>
                    <p class="mt-2 break-all font-mono text-xs text-slate-500">ID respons: {{ submission.response_id }}</p>
                    <p class="mt-1 text-xs text-slate-500">Dikirim {{ formatDate(submission.submitted_at) }} · {{ submission.source }}</p>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-semibold" :class="submission.verification_status === 'verified' ? 'bg-emerald-50 text-emerald-700' : submission.verification_status === 'needs_review' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'">
                    {{ statusLabels[submission.verification_status] ?? submission.verification_status }}
                </span>
            </header>

            <section v-if="submission.patient" class="flex flex-col justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-800">Tertaut ke profil pasien</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-950">{{ submission.patient.patient_code }} · {{ submission.patient.name }}</p>
                </div>
                <Link v-if="canManage" :href="`/patients?q=${submission.patient.patient_code}`" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-emerald-300 bg-white px-4 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Buka data pasien</Link>
            </section>

            <section v-for="group in responseGroups" :key="group.key" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-clinic-900">{{ group.title }}</h2></div>
                <dl class="grid gap-x-6 gap-y-5 px-5 py-5 sm:grid-cols-2">
                    <div v-for="[key, label] in group.fields" :key="key" class="min-w-0">
                        <dt class="text-[11px] font-semibold text-slate-500">{{ label }}</dt>
                        <dd class="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{{ formatAnswer(submission[group.key]?.[key]) }}</dd>
                    </div>
                </dl>
            </section>

            <section v-if="canManage" class="grid gap-4 xl:grid-cols-2">
                <form v-if="!submission.patient" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5" @submit.prevent="linkPatient">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 place-items-center rounded-lg bg-clinic-50 text-clinic-700"><Link2 :size="17" /></span>
                        <div><h2 class="text-sm font-bold text-clinic-900">Tautkan ke pasien yang sudah ada</h2><p class="mt-1 text-xs leading-5 text-slate-500">Gunakan kode pasien setelah memastikan identitas keluarga.</p></div>
                    </div>
                    <label class="block text-xs font-semibold text-slate-600">Kode pasien
                        <input v-model="linkForm.patient_code" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal" maxlength="20" autocomplete="off" placeholder="KL-2026-001">
                        <span v-if="linkForm.errors.patient_code" class="mt-1 block text-xs text-rose-700">{{ linkForm.errors.patient_code }}</span>
                    </label>
                    <button :disabled="linkForm.processing" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-clinic-700 px-4 text-xs font-semibold text-white hover:bg-clinic-900 disabled:opacity-60"><Link2 :size="14" /> Tautkan pasien</button>
                </form>

                <form v-if="!submission.patient" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5" @submit.prevent="createPatient">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 place-items-center rounded-lg bg-clinic-50 text-clinic-700"><UserRoundPlus :size="17" /></span>
                        <div><h2 class="text-sm font-bold text-clinic-900">Buat profil pasien baru</h2><p class="mt-1 text-xs leading-5 text-slate-500">Periksa jawaban terlebih dahulu. Jawaban lengkap tetap tersimpan pada intake.</p></div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-slate-600 sm:col-span-2">Nama anak *
                            <input v-model="createPatientForm.name" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal" maxlength="160" required>
                            <span v-if="createPatientForm.errors.name" class="mt-1 block text-xs text-rose-700">{{ createPatientForm.errors.name }}</span>
                        </label>
                        <label class="block text-xs font-semibold text-slate-600">Tanggal lahir
                            <input v-model="createPatientForm.birth_date" type="date" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal">
                            <span v-if="createPatientForm.errors.birth_date" class="mt-1 block text-xs text-rose-700">{{ createPatientForm.errors.birth_date }}</span>
                        </label>
                        <label class="block text-xs font-semibold text-slate-600 sm:col-span-2">Alamat
                            <textarea v-model="createPatientForm.address" rows="2" maxlength="2000" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-normal"></textarea>
                            <span v-if="createPatientForm.errors.address" class="mt-1 block text-xs text-rose-700">{{ createPatientForm.errors.address }}</span>
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-slate-600">Nama ibu
                            <input v-model="createPatientForm.mother_name" maxlength="160" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600">WhatsApp ibu
                            <input v-model="createPatientForm.mother_whatsapp" maxlength="30" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600">Nama ayah
                            <input v-model="createPatientForm.father_name" maxlength="160" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600">WhatsApp ayah
                            <input v-model="createPatientForm.father_whatsapp" maxlength="30" class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal">
                        </label>
                    </div>
                    <div v-if="createPatientForm.hasErrors" role="alert" class="flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700"><CircleAlert :size="15" /> Ada data yang perlu diperiksa.</div>
                    <button :disabled="createPatientForm.processing" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-clinic-700 px-4 text-xs font-semibold text-white hover:bg-clinic-900 disabled:opacity-60"><UserRoundPlus :size="14" /> Buat dan tautkan pasien</button>
                </form>

                <div v-if="submission.verification_status !== 'verified'" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-5 xl:col-span-2">
                    <p class="mr-auto text-xs leading-5 text-slate-600">Status verifikasi dapat diperbarui setelah seluruh jawaban ditinjau.</p>
                    <button :disabled="statusForm.processing" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" @click="updateStatus('verified')"><Check :size="14" /> Tandai terverifikasi</button>
                    <button :disabled="statusForm.processing" class="inline-flex min-h-10 items-center rounded-lg border border-amber-200 bg-amber-50 px-4 text-xs font-semibold text-amber-800 hover:bg-amber-100" @click="updateStatus('needs_review')">Perlu ditinjau</button>
                </div>
            </section>
        </div>
    </MainLayout>
</template>
