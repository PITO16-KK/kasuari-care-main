<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Banknote, ClipboardCheck, FileText, Plus } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

defineProps({ assessments: Object });

const page = usePage();
const canSchedule = computed(() => page.props.auth?.user?.roles?.some((role) => ['owner', 'front_office'].includes(role)) ?? false);
const canCollectPayment = computed(() => page.props.auth?.user?.roles?.some((role) => ['owner', 'front_office', 'finance'].includes(role)) ?? false);
const completedStatuses = ['completed', 'scheduled'];

function statusLabel(status) {
    return {
        awaiting_payment: 'Menunggu pembayaran',
        paid: 'Lunas · hasil belum diisi',
        completed: 'Selesai',
        scheduled: 'Hasil tersedia',
    }[status] ?? status;
}

function statusClass(status) {
    return status === 'completed' || status === 'scheduled'
        ? 'bg-emerald-50 text-emerald-700'
        : status === 'paid'
            ? 'bg-sky-50 text-sky-700'
            : 'bg-amber-50 text-amber-800';
}

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);
}

function assessmentDate(value) {
    if (!value) {
        return 'Tanggal belum diisi';
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
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-clinic-600">Rekam pemeriksaan awal</p>
                    <h1 class="mt-1 text-2xl font-bold text-clinic-900">Riwayat assessment</h1>
                    <p class="mt-2 text-sm text-slate-600">Buat jadwal dan tagihan di sini. Hasil baru dapat dikirim setelah pembayaran tercatat.</p>
                </div>
                <Link href="/assessments/create" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                    <Plus :size="16" /> Assessment baru
                </Link>
            </header>

            <section v-if="page.props.flash?.assessmentPdfUrl" class="flex flex-col gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-sm font-bold text-emerald-900">Hasil assessment siap dilihat</h2><p class="mt-1 text-xs text-emerald-800">Data sudah tersimpan dan dapat digunakan sebagai acuan jadwal terapi.</p></div>
                <a :href="page.props.flash.assessmentPdfUrl" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald-700 px-4 text-xs font-bold text-white hover:bg-emerald-800">Buka hasil PDF</a>
            </section>

            <section v-if="assessments.data.length" class="space-y-3">
                <article v-for="assessment in assessments.data" :key="assessment.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-bold text-clinic-900">{{ assessment.patient.name }}</h2>
                                <span class="font-mono text-[10px] text-slate-500">{{ assessment.patient.patient_code }}</span>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="statusClass(assessment.status)">{{ statusLabel(assessment.status) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Assessment {{ assessmentDate(assessment.scheduled_at || assessment.created_at) }}<span v-if="assessment.therapist?.user"> Â· Terapis {{ assessment.therapist.user.name }}</span></p>

                            <div v-if="assessment.sale" class="mt-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs">
                                <p class="font-semibold text-slate-800">{{ assessment.service_name || assessment.sale.lines[0]?.description }} · {{ money(assessment.sale.grand_total) }}</p>
                                <p class="mt-1 text-slate-500">Invoice {{ assessment.sale.invoice_number }}<span v-if="assessment.sale.paid_at"> · Dibayar {{ assessmentDate(assessment.sale.paid_at) }}</span></p>
                            </div>

                            <div v-if="completedStatuses.includes(assessment.status)" class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Keluhan utama</h3>
                                    <p class="mt-1 whitespace-pre-line text-xs leading-5 text-slate-700">{{ assessment.chief_complaint || 'Belum ada catatan.' }}</p>
                                </div>
                                <div>
                                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Ringkasan hasil</h3>
                                    <p class="mt-1 whitespace-pre-line text-xs leading-5 text-slate-700">{{ assessment.summary || 'Belum ada ringkasan.' }}</p>
                                </div>
                            </div>

                            <div v-if="completedStatuses.includes(assessment.status)" class="mt-4">
                                <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Rekomendasi terapis</h3>
                                <div v-if="assessment.patient.recommended_therapists.length" class="mt-2 flex flex-wrap gap-2">
                                    <span v-for="recommendation in assessment.patient.recommended_therapists" :key="recommendation.therapy_name" class="rounded-full bg-clinic-50 px-3 py-1.5 text-[11px] font-semibold text-clinic-800">
                                        {{ recommendation.therapy_name }} · {{ recommendation.therapist_name || 'Belum ditentukan' }}
                                    </span>
                                </div>
                                <p v-else class="mt-1 text-xs text-slate-500">Belum ada terapis yang ditetapkan pada data pasien.</p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 sm:w-48">
                            <Link v-if="assessment.status === 'awaiting_payment' && canCollectPayment && assessment.sale" :href="`/sales/${assessment.sale.id}/receipt`" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-amber-200 px-3 text-xs font-semibold text-amber-800 hover:bg-amber-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                <Banknote :size="15" /> Catat pembayaran
                            </Link>
                            <span v-else-if="assessment.status === 'awaiting_payment'" class="rounded-lg bg-amber-50 px-3 py-2 text-center text-[11px] font-semibold text-amber-800">Menunggu kasir mencatat pembayaran</span>
                            <Link v-if="assessment.status === 'paid'" :href="`/assessments/${assessment.id}/complete`" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-sky-200 px-3 text-xs font-semibold text-sky-800 hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                <ClipboardCheck :size="15" /> Isi hasil assessment
                            </Link>
                            <a v-if="completedStatuses.includes(assessment.status)" :href="`/assessments/${assessment.id}/pdf`" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-clinic-200 px-3 text-xs font-semibold text-clinic-800 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                <FileText :size="15" /> Lihat hasil lengkap
                            </a>
                            <Link v-if="canSchedule && completedStatuses.includes(assessment.status)" :href="`/scheduling?patient_id=${assessment.patient.id}&assessment_id=${assessment.id}`" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                <ClipboardCheck :size="15" /> Jadwalkan dari assessment
                            </Link>
                            <p class="text-center text-[10px] text-slate-500">{{ assessment.appointments_count }} jadwal terhubung</p>
                        </div>
                    </div>
                </article>
            </section>

            <section v-else class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                <ClipboardCheck :size="26" class="mx-auto text-slate-400" />
                <h2 class="mt-3 text-sm font-bold text-slate-800">Belum ada assessment</h2>
                <p class="mt-1 text-xs text-slate-600">Assessment yang disimpan akan tampil di sini beserta rekomendasi terapis dan jadwal terkait.</p>
                <Link href="/assessments/create" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white hover:bg-clinic-900">Buat assessment</Link>
            </section>

            <nav v-if="assessments.links.length > 3" aria-label="Halaman riwayat assessment" class="flex flex-wrap justify-center gap-1">
                <template v-for="link in assessments.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll :aria-current="link.active ? 'page' : undefined" class="inline-flex min-h-11 items-center rounded-md border px-3 py-2 text-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" v-html="link.label" />
                    <span v-else class="inline-flex min-h-11 items-center rounded-md border border-slate-100 px-3 py-2 text-xs text-slate-400" v-html="link.label"></span>
                </template>
            </nav>
        </div>
    </MainLayout>
</template>
