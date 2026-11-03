<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowUpRight, Bell, CalendarDays, Check, ChevronLeft, ChevronRight, ClipboardCheck, CreditCard, Plus, UsersRound, X } from '@lucide/vue';
import MainLayout from '../Layouts/MainLayout.vue';

const props = defineProps({
    stats: Object,
    todaySchedule: Array,
    therapySchedule: Array,
    otherAgenda: Array,
    scheduleFilters: Object,
    scheduleTherapists: Array,
    scheduleTherapyTypes: Array,
    canFilterTherapist: Boolean,
    evaluationAlerts: Array,
    evaluationAlertCount: Number,
    unpaidSessionAlerts: Array,
    unpaidSessionAlertCount: Number,
    expiringPackageAlerts: Array,
    expiringPackageAlertCount: Number,
    packageExpiryAlertDays: Number,
    therapistCount: Number,
    isTherapistDashboard: Boolean,
    canSeeFinance: Boolean,
});

const notificationsOpen = ref(false);
const notificationCount = computed(() => (props.unpaidSessionAlertCount ?? 0) + (props.expiringPackageAlertCount ?? 0));
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);
const time = (value) => new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(value));
const dateTime = (value) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
const date = (value) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
const fullDate = (value) => new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date(value));
const selectedDateLabel = computed(() => fullDate(`${props.scheduleFilters.date}T12:00:00+07:00`));
const scheduleStatusLabels = { waiting: 'Menunggu', present: 'Hadir', absent: 'Tidak Hadir', completed: 'Selesai' };
const scheduleStatusClasses = {
    waiting: 'bg-amber-50 text-amber-800 ring-amber-200',
    present: 'bg-sky-50 text-sky-800 ring-sky-200',
    absent: 'bg-rose-50 text-rose-800 ring-rose-200',
    completed: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
};
const therapyBadgeClasses = {
    TW: 'bg-sky-50 text-sky-800 ring-sky-200',
    OT: 'bg-violet-50 text-violet-800 ring-violet-200',
    SI: 'bg-amber-50 text-amber-800 ring-amber-200',
    FT: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    ABA: 'bg-rose-50 text-rose-800 ring-rose-200',
};
const hour = new Date().getHours();
const greeting = hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 18 ? 'Selamat sore' : 'Selamat malam';
const statCards = (stats) => props.isTherapistDashboard
    ? [
        { label: 'Jadwal hari ini', value: stats.todayAppointments, note: 'Sesi yang ditugaskan kepada Anda', icon: CalendarDays, tint: 'bg-coral-50 text-coral-700' },
        { label: 'Pasien hari ini', value: stats.todayPatients, note: 'Pasien yang dijadwalkan untuk Anda', icon: UsersRound, tint: 'bg-clinic-50 text-clinic-700' },
        { label: 'Sesi selesai hari ini', value: stats.completedSessions, note: 'Sesi dengan status selesai', icon: Check, tint: 'bg-emerald-50 text-emerald-700' },
        { label: 'Laporan bulan ini', value: stats.monthlyReports, note: 'Laporan sesi yang sudah dikirim', icon: ClipboardCheck, tint: 'bg-clinic-50 text-clinic-700' },
    ]
    : [
        { label: 'Pasien aktif', value: stats.activePatients, note: 'Terdaftar di klinik', icon: UsersRound, tint: 'bg-clinic-50 text-clinic-700' },
        { label: 'Jadwal hari ini', value: stats.todayAppointments, note: stats.completedSessions + ' sesi selesai', icon: CalendarDays, tint: 'bg-coral-50 text-coral-700' },
        ...(props.canSeeFinance ? [{ label: 'Pendapatan hari ini', value: money(stats.todayRevenue), note: 'Transaksi lunas', icon: CreditCard, tint: 'bg-emerald-50 text-emerald-700' }] : []),
        { label: 'Sesi selesai bulan ini', value: stats.monthlyCompletedSessions, note: 'Sesi dengan status selesai', icon: Check, tint: 'bg-emerald-50 text-emerald-700' },
    ];

function changeScheduleFilter(key, value) {
    const filters = {
        date: props.scheduleFilters.date,
        therapist_id: props.scheduleFilters.therapist_id || '',
        therapy_type_id: props.scheduleFilters.therapy_type_id || '',
    };

    filters[key] = value;

    router.get('/dashboard', filters, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function shiftScheduleDate(days) {
    const selectedDate = new Date(`${props.scheduleFilters.date}T00:00:00Z`);
    selectedDate.setUTCDate(selectedDate.getUTCDate() + days);
    changeScheduleFilter('date', selectedDate.toISOString().slice(0, 10));
}

function todayScheduleDate() {
    const dateParts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const parts = Object.fromEntries(dateParts.map((part) => [part.type, part.value]));

    return `${parts.year}-${parts.month}-${parts.day}`;
}

function scheduleTherapyBadge(code) {
    return therapyBadgeClasses[code] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
}
</script>

<template>
    <MainLayout>
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-clinic-900">{{ greeting }}, {{ isTherapistDashboard ? 'Terapis' : 'tim Kasuari Care' }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ isTherapistDashboard ? 'Pantau jadwal dan laporan sesi Anda hari ini.' : 'Semoga sesi hari ini berjalan lancar.' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <div v-if="!isTherapistDashboard" class="relative" @keydown.esc="notificationsOpen = false">
                    <button
                        type="button"
                        class="relative grid size-11 place-items-center rounded-lg border border-[#e5e2d9] bg-white text-slate-600 transition-colors hover:bg-[#f9f8f4] hover:text-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                        :aria-label="`Notifikasi, ${notificationCount} perlu ditindaklanjuti`"
                        :aria-expanded="notificationsOpen"
                        aria-controls="dashboard-notifications"
                        @click="notificationsOpen = !notificationsOpen"
                    >
                        <Bell :size="19" aria-hidden="true" />
                        <span v-if="notificationCount" class="absolute -right-1 -top-1 grid min-h-5 min-w-5 place-items-center rounded-full bg-coral-700 px-1 text-[10px] font-bold leading-none text-white" aria-hidden="true">
                            {{ notificationCount > 99 ? '99+' : notificationCount }}
                        </span>
                    </button>

                    <div v-show="notificationsOpen" id="dashboard-notifications" role="region" aria-label="Daftar notifikasi dashboard" class="absolute right-0 top-full z-40 mt-2 w-[min(92vw,26rem)] overflow-hidden rounded-xl border border-[#e9e6de] bg-white shadow-xl">
                        <div class="flex items-center justify-between gap-3 border-b border-[#efede6] px-4 py-3.5">
                            <div>
                                <h2 class="text-sm font-bold text-clinic-900">Notifikasi</h2>
                                <p class="mt-0.5 text-xs text-slate-500">{{ notificationCount }} perlu ditindaklanjuti</p>
                            </div>
                            <button type="button" aria-label="Tutup notifikasi" class="grid size-10 shrink-0 place-items-center rounded-lg text-slate-500 transition-colors hover:bg-[#f9f8f4] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="notificationsOpen = false">
                                <X :size="17" aria-hidden="true" />
                            </button>
                        </div>

                        <div v-if="!notificationCount" role="status" class="px-4 py-8 text-center text-sm text-slate-600">
                            Tidak ada notifikasi yang perlu ditindaklanjuti.
                        </div>

                        <div v-else class="max-h-[70vh] overflow-y-auto">
                            <section v-if="unpaidSessionAlerts.length" aria-labelledby="unpaid-session-alerts-title" class="border-b border-[#efede6] p-3">
                                <h3 id="unpaid-session-alerts-title" class="px-2 pb-2 pt-1 text-xs font-bold text-slate-600">
                                    Tagihan sesi belum lunas <span class="ml-1 font-normal text-slate-500">{{ unpaidSessionAlertCount }}</span>
                                </h3>
                                <div class="space-y-1">
                                    <Link v-for="entry in unpaidSessionAlerts" :key="entry.id" :href="entry.href" class="block rounded-lg px-3 py-2.5 transition-colors hover:bg-[#f9f8f4] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="min-w-0 truncate text-sm font-semibold text-slate-800">{{ entry.patient_name }}</p>
                                            <span v-if="entry.grand_total !== null" class="shrink-0 text-xs font-semibold text-coral-700">{{ money(entry.grand_total) }}</span>
                                        </div>
                                        <p class="mt-1 truncate text-xs text-slate-600">{{ entry.patient_code }} · {{ entry.therapy_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Sesi {{ dateTime(entry.starts_at) }} · Invoice {{ entry.invoice_number }}</p>
                                    </Link>
                                </div>
                                <Link v-if="unpaidSessionAlertCount > unpaidSessionAlerts.length" :href="unpaidSessionAlerts[0].grand_total !== null ? '/bills?type=therapy' : '/patients'" class="mt-2 flex min-h-10 items-center justify-center rounded-lg text-xs font-semibold text-clinic-700 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                    {{ unpaidSessionAlerts[0].grand_total !== null ? 'Lihat semua tagihan terapi' : 'Buka daftar pasien' }}
                                </Link>
                            </section>

                            <section v-if="expiringPackageAlerts.length" aria-labelledby="expiring-package-alerts-title" class="p-3">
                                <h3 id="expiring-package-alerts-title" class="px-2 pb-2 pt-1 text-xs font-bold text-slate-600">
                                    Paket kedaluwarsa dalam {{ packageExpiryAlertDays }} hari <span class="ml-1 font-normal text-slate-500">{{ expiringPackageAlertCount }}</span>
                                </h3>
                                <div class="space-y-1">
                                    <Link v-for="entry in expiringPackageAlerts" :key="entry.id" :href="`/patients?q=${encodeURIComponent(entry.patient_code)}`" class="block rounded-lg px-3 py-2.5 transition-colors hover:bg-[#f9f8f4] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                        <p class="truncate text-sm font-semibold text-slate-800">{{ entry.patient_name }} <span class="ml-1 text-xs font-normal text-slate-500">{{ entry.patient_code }}</span></p>
                                        <p class="mt-1 truncate text-xs text-slate-600">{{ entry.package_name }}</p>
                                        <p class="mt-1 text-xs font-medium text-amber-800">Kedaluwarsa {{ date(entry.expires_at) }}</p>
                                    </Link>
                                </div>
                                <Link v-if="expiringPackageAlertCount > expiringPackageAlerts.length" href="/patients" class="mt-2 flex min-h-10 items-center justify-center rounded-lg text-xs font-semibold text-clinic-700 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">
                                    Lihat daftar pasien
                                </Link>
                            </section>
                        </div>
                    </div>
                </div>
                <template v-if="isTherapistDashboard">
                    <Link href="/assessments" class="inline-flex h-11 items-center gap-2 rounded-lg border border-[#e5e2d9] bg-white px-4 text-sm font-semibold text-slate-700 transition-colors hover:bg-[#f9f8f4]"><ClipboardCheck :size="16" /> Assessment</Link>
                    <Link href="/sessions" class="inline-flex h-11 items-center gap-2 rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-clinic-900"><ArrowUpRight :size="17" /> Laporan sesi</Link>
                </template>
                <template v-else>
                    <Link href="/patients" class="inline-flex h-11 items-center gap-2 rounded-lg border border-[#e5e2d9] bg-white px-4 text-sm font-semibold text-slate-700 transition-colors hover:bg-[#f9f8f4]"><UsersRound :size="16" /> Data pasien</Link>
                    <Link href="/scheduling" class="inline-flex h-11 items-center gap-2 rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-clinic-900"><Plus :size="17" /> Buat jadwal</Link>
                </template>
            </div>
        </div>

        <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2" :class="isTherapistDashboard || canSeeFinance ? 'xl:grid-cols-4' : 'xl:grid-cols-3'">
            <article v-for="item in statCards(stats)" :key="item.label" class="flex min-h-36 min-w-0 flex-col justify-between rounded-xl border border-[#e9e6de] bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 text-sm font-medium text-slate-500">{{ item.label }}</div>
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl" :class="item.tint"><component :is="item.icon" :size="19" /></span>
                </div>
                <div class="min-w-0">
                    <div class="break-words text-xl font-semibold leading-tight tracking-tight text-clinic-900 tabular-nums 2xl:text-2xl">{{ item.value }}</div>
                    <div class="mt-2 text-xs text-slate-500">{{ item.note }}</div>
                </div>
            </article>
        </section>

        <section v-if="evaluationAlertCount" aria-labelledby="evaluation-alert-title" class="mt-5 overflow-hidden rounded-xl border border-amber-300 bg-amber-50">
            <div class="flex items-start gap-3 border-b border-amber-200 px-4 py-4 sm:px-5">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-800" aria-hidden="true"><ClipboardCheck :size="19" /></span>
                <div>
                    <h2 id="evaluation-alert-title" class="text-sm font-bold text-amber-950">Evaluasi wajib</h2>
                    <p class="mt-1 text-sm leading-5 text-amber-900">{{ evaluationAlertCount }} pasien sudah menjalani sedikitnya 25 sesi sejak evaluasi terakhir.</p>
                </div>
            </div>
            <div class="divide-y divide-amber-200">
                <div v-for="patient in evaluationAlerts" :key="patient.id" class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-800">{{ patient.name }} <span class="ml-1 font-mono text-xs font-normal text-slate-600">{{ patient.patient_code }}</span></p>
                        <p class="mt-1 text-xs leading-5 text-slate-700">{{ patient.sessions_since_evaluation }} sesi sejak evaluasi terakhir</p>
                    </div>
                    <Link :href="`/assessments/create?patient_id=${patient.id}`" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-amber-800 px-4 text-sm font-semibold text-amber-950 transition-colors hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-800">Jadwalkan evaluasi</Link>
                </div>
            </div>
        </section>

        <section class="mt-5 space-y-5">
            <div class="overflow-hidden rounded-xl border border-[#e9e6de] bg-white shadow-sm">
                <div class="border-b border-[#efede6] px-5 py-4 sm:px-6">
                    <h2 class="text-base font-semibold text-clinic-900">Jadwal terapi</h2>
                </div>

                <div class="grid gap-3 border-b border-[#efede6] p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-[minmax(260px,1.4fr)_auto_minmax(180px,1fr)_minmax(180px,1fr)] xl:items-end">
                    <div>
                        <label for="schedule-date" class="mb-1.5 block text-xs font-semibold text-slate-600">Tanggal jadwal</label>
                        <div class="flex min-h-11 items-center gap-1 rounded-lg border border-[#e5e2d9] bg-white p-1">
                            <button type="button" aria-label="Tanggal sebelumnya" class="grid size-9 shrink-0 place-items-center rounded-md text-slate-600 hover:bg-[#f4f3ed] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="shiftScheduleDate(-1)"><ChevronLeft :size="18" /></button>
                            <input id="schedule-date" type="date" :value="scheduleFilters.date" class="min-w-0 flex-1 border-0 bg-transparent px-1 text-sm text-slate-700 outline-none focus:ring-0" @change="changeScheduleFilter('date', $event.target.value)" />
                            <button type="button" aria-label="Tanggal berikutnya" class="grid size-9 shrink-0 place-items-center rounded-md text-slate-600 hover:bg-[#f4f3ed] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="shiftScheduleDate(1)"><ChevronRight :size="18" /></button>
                        </div>
                    </div>
                    <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-clinic-200 bg-clinic-50 px-4 text-sm font-semibold text-clinic-800 transition-colors hover:bg-clinic-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="changeScheduleFilter('date', todayScheduleDate())">Hari Ini</button>
                    <div v-if="canFilterTherapist">
                        <label for="schedule-therapist" class="mb-1.5 block text-xs font-semibold text-slate-600">Terapis</label>
                        <select id="schedule-therapist" :value="scheduleFilters.therapist_id" class="min-h-11 w-full rounded-lg border border-[#e5e2d9] bg-white px-3 text-sm text-slate-700 focus:border-clinic-500 focus:outline-none focus:ring-2 focus:ring-clinic-100" @change="changeScheduleFilter('therapist_id', $event.target.value)">
                            <option value="">Semua terapis</option>
                            <option v-for="therapist in scheduleTherapists" :key="therapist.id" :value="therapist.id">{{ therapist.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="schedule-therapy" class="mb-1.5 block text-xs font-semibold text-slate-600">Jenis terapi</label>
                        <select id="schedule-therapy" :value="scheduleFilters.therapy_type_id" class="min-h-11 w-full rounded-lg border border-[#e5e2d9] bg-white px-3 text-sm text-slate-700 focus:border-clinic-500 focus:outline-none focus:ring-2 focus:ring-clinic-100" @change="changeScheduleFilter('therapy_type_id', $event.target.value)">
                            <option value="">Semua jenis terapi</option>
                            <option v-for="therapy in scheduleTherapyTypes" :key="therapy.id" :value="therapy.id">{{ therapy.code }} · {{ therapy.name }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="therapySchedule.length" class="hidden overflow-x-auto lg:block">
                    <table class="w-full table-fixed text-left text-xs text-slate-700">
                        <colgroup><col class="w-[5%]"><col class="w-[17%]"><col class="w-[17%]"><col class="w-[11%]"><col class="w-[17%]"><col class="w-[14%]"><col class="w-[12%]"><col class="w-[7%]"></colgroup>
                        <thead class="bg-[#f8f7f2] text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-3 py-3.5">No</th>
                                <th scope="col" class="px-3 py-3.5">Jam</th>
                                <th scope="col" class="px-3 py-3.5">Nama Pasien</th>
                                <th scope="col" class="px-3 py-3.5">Jenis Terapi</th>
                                <th scope="col" class="px-3 py-3.5">Pertemuan Ke-</th>
                                <th scope="col" class="px-3 py-3.5">Nama Terapis</th>
                                <th scope="col" class="px-3 py-3.5">Paket Ke-</th>
                                <th scope="col" class="px-3 py-3.5">Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#efede6]">
                            <tr v-for="(entry, index) in therapySchedule" :key="entry.id" class="align-top transition-colors hover:bg-[#fcfbf8]">
                                <td class="px-3 py-3.5 font-semibold tabular-nums text-slate-500">{{ index + 1 }}</td>
                                <td class="px-3 py-3.5 font-medium tabular-nums text-slate-600">{{ time(entry.starts_at) }}</td>
                                <td class="px-3 py-3.5"><div class="font-semibold text-slate-800">{{ entry.patient.name }}</div><div class="mt-1 font-mono text-[10px] text-slate-500">{{ entry.patient.patient_code || '—' }}</div></td>
                                <td class="px-3 py-3.5"><span class="inline-flex max-w-full items-center gap-1.5 rounded-full px-2.5 py-1.5 font-semibold ring-1 ring-inset" :class="scheduleTherapyBadge(entry.therapy_type.code)"><span class="shrink-0 font-mono">{{ entry.therapy_type.code }}</span><span class="truncate font-normal">{{ entry.therapy_type.name }}</span></span></td>
                                <td class="px-3 py-3.5"><span v-if="entry.package_session" class="font-semibold tabular-nums text-clinic-800">{{ entry.package_session.number }}/{{ entry.package_session.total }}</span><span v-else class="text-slate-400">—</span></td>
                                <td class="px-3 py-3.5">{{ entry.therapist_name || '—' }}</td>
                                <td class="px-3 py-3.5"><template v-if="entry.package_session?.package_number"><div class="font-semibold text-slate-700">Paket ke-{{ entry.package_session.package_number }}</div><div class="mt-1 truncate text-[10px] text-slate-500" :title="entry.package_session.package_name">{{ entry.package_session.package_name }}</div></template><span v-else class="text-slate-400">—</span></td>
                                <td class="px-3 py-3.5"><span class="inline-flex rounded-full px-2.5 py-1.5 text-[11px] font-semibold ring-1 ring-inset" :class="scheduleStatusClasses[entry.attendance_status]">{{ scheduleStatusLabels[entry.attendance_status] }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="therapySchedule.length" class="divide-y divide-[#efede6] lg:hidden">
                    <article v-for="(entry, index) in therapySchedule" :key="entry.id" class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-[#f4f3ed] text-xs font-bold tabular-nums text-slate-600">{{ index + 1 }}</span>
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-900">{{ entry.patient.name }}</h3>
                                    <p class="mt-0.5 font-mono text-[11px] text-slate-500">{{ entry.patient.patient_code || '—' }}</p>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1.5 text-[11px] font-semibold ring-1 ring-inset" :class="scheduleStatusClasses[entry.attendance_status]">{{ scheduleStatusLabels[entry.attendance_status] }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex max-w-full items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-semibold ring-1 ring-inset" :class="scheduleTherapyBadge(entry.therapy_type.code)"><span class="font-mono">{{ entry.therapy_type.code }}</span><span class="truncate font-normal">{{ entry.therapy_type.name }}</span></span>
                            <span v-if="entry.package_session" class="rounded-full bg-clinic-50 px-2.5 py-1.5 text-xs font-semibold tabular-nums text-clinic-800">Pertemuan {{ entry.package_session.number }}/{{ entry.package_session.total }}</span>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                            <div><dt class="text-slate-500">Jam terapi</dt><dd class="mt-0.5 font-medium tabular-nums text-slate-800">{{ time(entry.starts_at) }}</dd></div>
                            <div><dt class="text-slate-500">Nama Terapis</dt><dd class="mt-0.5 font-medium text-slate-800">{{ entry.therapist_name || '—' }}</dd></div>
                            <div class="col-span-2"><dt class="text-slate-500">Paket Ke-</dt><dd class="mt-0.5 font-medium text-slate-800">{{ entry.package_session?.package_number ? `Paket ke-${entry.package_session.package_number} · ${entry.package_session.package_name}` : '—' }}</dd></div>
                        </dl>
                    </article>
                </div>

                <div v-if="!therapySchedule.length" class="flex min-h-52 flex-col items-center justify-center px-5 text-center">
                    <span class="grid size-11 place-items-center rounded-full bg-clinic-50 text-clinic-700"><CalendarDays :size="20" /></span>
                    <p class="mt-3 text-sm font-semibold text-slate-700">Tidak ada sesi terapi pada tanggal ini</p>
                    <p class="mt-1 text-sm text-slate-500">Coba ubah tanggal atau filter jadwal.</p>
                </div>
            </div>

            <div v-if="otherAgenda.length" class="overflow-hidden rounded-xl border border-[#e9e6de] bg-white shadow-sm">
                    <div class="border-b border-[#efede6] px-5 py-4 sm:px-6">
                        <h2 class="text-sm font-semibold text-clinic-900">Assessment dan evaluasi</h2>
                        <p class="mt-1 text-xs text-slate-500">Agenda lain pada {{ selectedDateLabel }}</p>
                    </div>
                    <div class="divide-y divide-[#efede6]">
                        <div v-for="entry in otherAgenda" :key="entry.id" class="flex items-center justify-between gap-3 px-5 py-3.5 sm:px-6">
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ entry.patient_name }} <span class="ml-1 text-xs font-normal text-slate-500">{{ entry.patient_code }}</span></p><p class="mt-1 truncate text-xs text-slate-600">{{ entry.activity_name }} <span class="text-slate-400">· {{ time(entry.starts_at) }}</span></p><p class="mt-1 text-xs text-slate-500">Terapis: {{ entry.therapist_name || '—' }}</p></div>
                            <span class="shrink-0 rounded-full bg-[#f4f3ed] px-2.5 py-1.5 text-[11px] font-medium capitalize text-slate-600">{{ entry.status.replace('_', ' ') }}</span>
                        </div>
                    </div>
            </div>
        </section>

    </MainLayout>
</template>
