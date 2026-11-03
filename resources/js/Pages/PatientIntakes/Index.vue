<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ClipboardList, Clock3, FileText } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ submissions: Object, filters: Object, counts: Object });

const statusLabels = {
    pending: 'Menunggu tinjauan',
    verified: 'Terverifikasi',
    needs_review: 'Perlu ditinjau',
};

function applyStatus(status) {
    router.get('/patient-intakes', { status }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function submittedAt(value) {
    if (!value) {
        return 'Waktu tidak tersedia';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}
</script>

<template>
    <MainLayout>
        <div class="space-y-5">
            <header class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-clinic-600">Rekam medis awal</p>
                    <h1 class="mt-1 text-2xl font-bold text-clinic-900">Form intake masuk</h1>
                    <p class="mt-1 text-sm text-slate-500">Tinjau jawaban keluarga, lalu tautkan dengan profil pasien.</p>
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                    <ClipboardList :size="16" class="text-clinic-700" />
                    {{ submissions.total }} respons
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-3">
                <button
                    type="button"
                    class="rounded-xl border p-4 text-left transition-colors"
                    :class="filters.status === 'pending' ? 'border-clinic-500 bg-clinic-50' : 'border-slate-200 bg-white hover:bg-slate-50'"
                    @click="applyStatus('pending')"
                >
                    <span class="text-[11px] font-semibold text-slate-500">Menunggu tinjauan</span>
                    <span class="mt-1 block text-2xl font-bold text-clinic-900">{{ counts.pending }}</span>
                </button>
                <button
                    type="button"
                    class="rounded-xl border p-4 text-left transition-colors"
                    :class="filters.status === 'verified' ? 'border-clinic-500 bg-clinic-50' : 'border-slate-200 bg-white hover:bg-slate-50'"
                    @click="applyStatus('verified')"
                >
                    <span class="text-[11px] font-semibold text-slate-500">Terverifikasi</span>
                    <span class="mt-1 block text-2xl font-bold text-clinic-900">{{ counts.verified }}</span>
                </button>
                <button
                    type="button"
                    class="rounded-xl border p-4 text-left transition-colors"
                    :class="filters.status === 'needs_review' ? 'border-clinic-500 bg-clinic-50' : 'border-slate-200 bg-white hover:bg-slate-50'"
                    @click="applyStatus('needs_review')"
                >
                    <span class="text-[11px] font-semibold text-slate-500">Perlu ditinjau</span>
                    <span class="mt-1 block text-2xl font-bold text-clinic-900">{{ counts.needs_review }}</span>
                </button>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-bold text-clinic-900">Respons Google Form</h2>
                        <p class="mt-1 text-xs text-slate-500">Pilih respons untuk membaca seluruh jawaban dan menghubungkannya dengan pasien.</p>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                        Status
                        <select :value="filters.status" class="min-h-10 rounded-lg border border-slate-200 bg-white px-3" @change="applyStatus($event.target.value)">
                            <option value="all">Semua</option>
                            <option value="pending">Menunggu tinjauan</option>
                            <option value="verified">Terverifikasi</option>
                            <option value="needs_review">Perlu ditinjau</option>
                        </select>
                    </label>
                </div>

                <div v-if="submissions.data.length" class="divide-y divide-slate-100">
                    <article v-for="submission in submissions.data" :key="submission.id" class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-800">{{ submission.child_name || 'Nama anak belum diisi' }}</h3>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="submission.verification_status === 'verified' ? 'bg-emerald-50 text-emerald-700' : submission.verification_status === 'needs_review' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'">
                                    {{ statusLabels[submission.verification_status] ?? submission.verification_status }}
                                </span>
                            </div>
                            <p class="mt-1 break-all font-mono text-[11px] text-slate-500">ID respons: {{ submission.response_id }}</p>
                            <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><Clock3 :size="14" /> {{ submittedAt(submission.submitted_at) }}</p>
                            <p v-if="submission.patient" class="mt-2 text-xs font-semibold text-clinic-700">{{ submission.patient.patient_code }} · {{ submission.patient.name }}</p>
                        </div>
                        <Link :href="`/patient-intakes/${submission.id}`" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-clinic-700 px-4 text-xs font-semibold text-white hover:bg-clinic-900">
                            <FileText :size="15" /> Lihat jawaban
                        </Link>
                    </article>
                </div>

                <div v-else class="grid min-h-56 place-items-center px-5 text-center">
                    <div>
                        <ClipboardList :size="28" class="mx-auto text-slate-300" />
                        <p class="mt-3 text-sm font-semibold text-slate-700">Belum ada respons intake</p>
                        <p class="mt-1 text-xs text-slate-500">Respons yang diterima dari Google Form akan tampil di sini.</p>
                    </div>
                </div>

                <div v-if="submissions.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                    <Link v-for="link in submissions.links" :key="link.label" :href="link.url ?? '# '" class="rounded border px-3 py-2 text-xs" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600'" v-html="link.label" />
                </div>
            </section>
        </div>
    </MainLayout>
</template>
