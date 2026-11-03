<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ClipboardCheck } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ assessment: Object, therapyTypes: Array, therapistAssignments: Object, therapists: Array });
const form = useForm({
    chief_complaint: props.assessment.chief_complaint ?? '',
    development_history: props.assessment.development_history ?? { motorik: '', sensorik: '', kemandirian: '', komunikasi: '' },
    learning_behavior: props.assessment.learning_behavior ?? { belajar: '', fokus: '', perilaku: '' },
    summary: props.assessment.summary ?? '',
    referral_notes: props.assessment.referral_notes ?? '',
    therapist_assignments: {
        TO: props.therapistAssignments?.TO ?? '',
        TW: props.therapistAssignments?.TW ?? '',
    },
});
const recommendedTherapies = [
    { code: 'TO', fallbackName: 'Terapi Okupasi' },
    { code: 'TW', fallbackName: 'Terapi Wicara' },
];

const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);
const date = (value) => value
    ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(value))
    : 'Belum dijadwalkan';

function isTherapistUnavailable(therapist, code) {
    const assignedPatientId = Number(therapist.assigned_patient_id ?? 0);

    if (assignedPatientId && assignedPatientId !== Number(props.assessment.patient.id)) {
        return true;
    }

    return Object.entries(form.therapist_assignments).some(([otherCode, profileId]) => {
        return otherCode !== code && Number(profileId) === Number(therapist.id);
    });
}

function submit() {
    form.put(`/assessments/${props.assessment.id}/complete`, { preserveScroll: true });
}
</script>

<template>
    <MainLayout>
        <div class="space-y-5">
            <header>
                <p class="text-xs font-bold uppercase tracking-wide text-clinic-600">Assessment sudah dibayar</p>
                <h1 class="mt-1 text-2xl font-bold text-clinic-900">Isi hasil assessment</h1>
                <p class="mt-2 text-sm text-slate-600">Catat pemeriksaan setelah layanan dilakukan. Hasil yang dikirim dapat dilihat dan dipakai sebagai acuan jadwal terapi.</p>
                <Link href="/assessments" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-clinic-700 hover:bg-clinic-50">Kembali ke riwayat assessment</Link>
            </header>

            <section class="grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:grid-cols-3">
                <div><p class="text-[10px] font-bold uppercase tracking-wide text-emerald-800">Pasien</p><p class="mt-1 text-sm font-semibold text-emerald-950">{{ assessment.patient.name }} <span class="font-mono text-xs font-normal">{{ assessment.patient.patient_code }}</span></p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wide text-emerald-800">Jadwal assessment</p><p class="mt-1 text-sm font-semibold text-emerald-950">{{ date(assessment.scheduled_at) }}</p></div>
                <div><p class="text-[10px] font-bold uppercase tracking-wide text-emerald-800">Pembayaran</p><p class="mt-1 text-sm font-semibold text-emerald-950">{{ assessment.sale.invoice_number }} · {{ money(assessment.sale.grand_total) }} · Lunas</p></div>
            </section>

            <form class="space-y-4" @submit.prevent="submit">
                <div v-if="form.hasErrors" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Ada data yang perlu diperiksa. Pesan kesalahan ditampilkan di bawah kolom terkait.</div>

                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 class="text-sm font-semibold text-clinic-900">Informasi pemeriksaan</h2>
                    <label class="field-label mt-4">Keluhan utama *
                        <textarea v-model="form.chief_complaint" rows="3" class="field-input" maxlength="3000" required></textarea>
                        <span v-if="form.errors.chief_complaint" class="field-error">{{ form.errors.chief_complaint }}</span>
                    </label>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 class="text-sm font-semibold text-clinic-900">Riwayat tumbuh kembang</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label v-for="(label, key) in { motorik: 'Motorik', sensorik: 'Sensorik', kemandirian: 'Kemandirian', komunikasi: 'Komunikasi' }" :key="key" class="field-label">{{ label }}
                            <textarea v-model="form.development_history[key]" rows="2" class="field-input" :placeholder="`Catatan ${label.toLowerCase()}`"></textarea>
                            <span v-if="form.errors['development_history.' + key]" class="field-error">{{ form.errors['development_history.' + key] }}</span>
                        </label>
                    </div>
                    <h3 class="mt-5 text-xs font-semibold text-slate-700">Belajar, fokus, dan perilaku</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <label v-for="(label, key) in { belajar: 'Belajar', fokus: 'Fokus', perilaku: 'Perilaku' }" :key="key" class="field-label">{{ label }}
                            <textarea v-model="form.learning_behavior[key]" rows="2" class="field-input"></textarea>
                            <span v-if="form.errors['learning_behavior.' + key]" class="field-error">{{ form.errors['learning_behavior.' + key] }}</span>
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div>
                        <h2 class="text-sm font-semibold text-clinic-900">Rekomendasi terapis</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Tetapkan terapis okupasi dan terapis wicara untuk pasien. Boleh mengisi salah satu terlebih dahulu; satu terapis hanya dapat ditugaskan kepada satu anak.</p>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label v-for="therapy in recommendedTherapies" :key="therapy.code" class="field-label">
                            {{ therapyTypes.find((type) => type.code === therapy.code)?.name ?? therapy.fallbackName }}
                            <select v-model="form.therapist_assignments[therapy.code]" class="field-input">
                                <option value="">Belum ditentukan</option>
                                <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id" :disabled="isTherapistUnavailable(therapist, therapy.code)">
                                    {{ therapist.name }}{{ therapist.specialty ? ` · ${therapist.specialty}` : '' }}{{ isTherapistUnavailable(therapist, therapy.code) ? ' · sudah ditugaskan' : '' }}
                                </option>
                            </select>
                            <span v-if="form.errors['therapist_assignments.' + therapy.code]" class="field-error">{{ form.errors['therapist_assignments.' + therapy.code] }}</span>
                        </label>
                    </div>
                    <p v-if="!therapists.length" class="mt-4 rounded-lg bg-[#F9F7F5] px-4 py-3 text-xs text-slate-500">Belum ada profil terapis yang dapat dipilih.</p>
                    <p v-if="form.errors.therapist_assignments" class="field-error">{{ form.errors.therapist_assignments }}</p>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <h2 class="text-sm font-semibold text-clinic-900">Kesimpulan tim</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="field-label">Ringkasan hasil<textarea v-model="form.summary" rows="4" class="field-input" maxlength="5000"></textarea><span v-if="form.errors.summary" class="field-error">{{ form.errors.summary }}</span></label>
                        <label class="field-label">Rujukan / catatan lanjutan<textarea v-model="form.referral_notes" rows="4" class="field-input" maxlength="3000"></textarea><span v-if="form.errors.referral_notes" class="field-error">{{ form.errors.referral_notes }}</span></label>
                    </div>
                </section>

                <div class="flex justify-end"><button :disabled="form.processing" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-clinic-700 px-5 text-sm font-semibold text-white hover:bg-clinic-900 disabled:cursor-not-allowed disabled:opacity-60">{{ form.processing ? 'Mengirim hasil...' : 'Kirim hasil assessment' }}</button></div>
            </form>
        </div>
    </MainLayout>
</template>

<style scoped>
.field-label { display: block; color: #475569; font-size: 12px; font-weight: 600; }
.field-input { display: block; width: 100%; min-height: 42px; margin-top: 7px; border: 1px solid #d8dde5; border-radius: 8px; background: white; padding: 9px 11px; color: #334155; font-size: 13px; font-weight: 400; outline: none; }
.field-input:focus-visible { border-color: #6A8EC4; box-shadow: 0 0 0 3px rgb(106 142 196 / 16%); }
.field-error { display: block; margin-top: 5px; color: #be123c; font-size: 11px; }
</style>
