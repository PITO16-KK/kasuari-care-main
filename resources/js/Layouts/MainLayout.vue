<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    Activity, CalendarDays, ChevronDown, ClipboardList, CreditCard, FilePlus2,
    LayoutDashboard, LogOut, Menu, PackageCheck, Receipt, UserRoundPlus, UsersRound, X,
} from '@lucide/vue';

const page = usePage();
const mobileOpen = ref(false);
const user = computed(() => page.props.auth?.user);
const roles = computed(() => user.value?.roles ?? []);
const pageTitle = computed(() => ({
    Dashboard: 'Ringkasan',
    'Registrations/Index': 'Pendaftaran masuk',
    'Patients/Index': 'Data pasien',
    'PatientIntakes/Index': 'Form intake rekam medis',
    'PatientIntakes/Show': 'Jawaban form rekam medis',
    'Therapists/Index': 'Terapis',
    'Packages/Index': 'Paket terapi',
    'Assessments/Create': 'Assessment',
    'Assessments/Index': 'Riwayat assessment',
    'Assessments/Complete': 'Isi hasil assessment',
    'Sales/Bills': 'Tagihan',
    'Sales/Receipt': 'Tagihan',
    'Scheduling/Index': 'Jadwal terapi',
    'Sessions/Index': 'Laporan sesi',
    'Sales/Create': 'Kasir dan pembayaran',
    'Payments/History': 'Riwayat pembayaran',
}[page.component] ?? 'Kasuari Care'));
const navGroups = computed(() => {
    const groups = [
        { label: 'Umum', items: [{ label: 'Ringkasan', href: '/dashboard', icon: LayoutDashboard }] },
    ];

    if (roles.value.some((role) => ['owner', 'front_office'].includes(role))) {
        groups.push({
            label: 'Pasien & layanan',
            items: [
                { label: 'Pendaftaran', href: '/registrations', icon: ClipboardList },
                { label: 'Form rekam medis', href: '/patient-intakes', icon: FilePlus2 },
                { label: 'Data pasien', href: '/patients', icon: UsersRound },
                { label: 'Terapis', href: '/therapists', icon: UserRoundPlus },
                { label: 'Paket terapi', href: '/packages', icon: PackageCheck },
                { label: 'Assessment', href: '/assessments', icon: FilePlus2 },
            ],
        });
    } else if (roles.value.includes('therapist')) {
        groups.push({
            label: 'Rekam medis',
            items: [{ label: 'Form intake', href: '/patient-intakes', icon: FilePlus2 }],
        });
    }

    const operationItems = [];
    if (roles.value.some((role) => ['owner', 'front_office'].includes(role))) {
        operationItems.push({ label: 'Jadwal terapi', href: '/scheduling', icon: CalendarDays });
    }
    if (roles.value.some((role) => ['owner', 'therapist'].includes(role))) {
        operationItems.push({ label: 'Laporan sesi', href: '/sessions', icon: Activity });
    }
    if (operationItems.length) {
        groups.push({ label: 'Operasional', items: operationItems });
    }

    if (roles.value.some((role) => ['owner', 'finance'].includes(role))) {
        groups.push({
            label: 'Keuangan',
            items: [
                { label: 'Tagihan', href: '/bills', icon: ClipboardList },
                { label: 'Kasir & POS', href: '/sales/create', icon: CreditCard },
                { label: 'Riwayat pembayaran', href: '/payment-history', icon: CreditCard },
                { label: 'Pengeluaran & gaji', href: '/finance/records', icon: Receipt },
            ],
        });
    }

    return groups;
});
const expandedNavGroup = ref(null);
const logout = useForm({});
const isActive = (href) => page.url === href || (href !== '/dashboard' && page.url.startsWith(href));

watch(() => page.url, () => {
    const activeGroup = navGroups.value.find((group) => group.items.some((item) => isActive(item.href)));
    expandedNavGroup.value = activeGroup?.label ?? null;
}, { immediate: true });

function toggleNavGroup(label) {
    expandedNavGroup.value = expandedNavGroup.value === label ? null : label;
}

function navGroupId(prefix, label) {
    const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

    return `${prefix}-nav-${slug}`;
}

function signOut() {
    logout.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-[#F9F7F5] text-slate-800">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-[264px] flex-col border-r border-[#e9e6de] bg-white lg:flex">
            <Link href="/dashboard" class="flex h-[84px] items-center gap-3 border-b border-[#efede6] px-6">
                <img :src="'/logo-klinik.png'" alt="Logo Kasuari Care" class="size-11 shrink-0 rounded-xl object-contain">
                <span class="leading-tight"><b class="block text-base text-clinic-900">Kasuari Care</b><small class="mt-1 block text-xs text-slate-500">Rumah Terapi Anak</small></span>
            </Link>
            <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-4" aria-label="Menu utama">
                <section v-for="group in navGroups" :key="group.label" class="mb-1 last:mb-0">
                    <h2>
                        <button
                            type="button"
                            class="flex min-h-11 w-full items-center justify-between rounded-lg px-3.5 text-left text-[11px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                            :class="expandedNavGroup === group.label ? 'text-clinic-900' : 'text-slate-500 hover:bg-[#f6f5ef] hover:text-clinic-900'"
                            :aria-expanded="expandedNavGroup === group.label"
                            :aria-controls="navGroupId('desktop', group.label)"
                            @click="toggleNavGroup(group.label)"
                        >
                            {{ group.label }}
                            <ChevronDown :size="15" class="shrink-0 transition-transform duration-150" :class="expandedNavGroup === group.label ? 'rotate-180' : ''" aria-hidden="true" />
                        </button>
                    </h2>
                    <div v-show="expandedNavGroup === group.label" :id="navGroupId('desktop', group.label)" class="space-y-1 pt-1">
                        <Link v-for="item in group.items" :key="item.href" :href="item.href"
                            class="relative flex h-11 items-center gap-3 rounded-lg px-3.5 text-sm font-medium transition-colors focus-visible:outline-offset-1"
                            :aria-current="isActive(item.href) ? 'page' : undefined"
                            :class="isActive(item.href) ? 'bg-clinic-50 text-clinic-900' : 'text-slate-600 hover:bg-[#f6f5ef] hover:text-clinic-900'">
                            <span v-if="isActive(item.href)" class="absolute inset-y-2.5 left-0 w-0.5 rounded-full bg-clinic-600"></span>
                            <component :is="item.icon" :size="18" :stroke-width="1.8" />
                            {{ item.label }}
                        </Link>
                    </div>
                </section>
            </nav>
            <div class="border-t border-[#efede6] p-4">
                <div class="flex items-center gap-3 rounded-xl bg-[#F9F7F5] p-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-coral-100 text-sm font-semibold text-coral-700">{{ user?.name?.charAt(0) ?? 'K' }}</span>
                    <span class="min-w-0 flex-1"><b class="block truncate text-sm">{{ user?.name }}</b><small class="mt-0.5 block text-xs capitalize text-slate-500">{{ roles[0]?.replace('_', ' ') }}</small></span>
                    <button title="Keluar" aria-label="Keluar" class="grid size-9 place-items-center rounded-lg text-slate-400 transition-colors hover:bg-white hover:text-coral-700" @click="signOut"><LogOut :size="17" /></button>
                </div>
            </div>
        </aside>

        <div class="lg:pl-[264px]">
            <header class="sticky top-0 z-20 flex h-[68px] items-center justify-between border-b border-[#e9e6de] bg-white/95 px-4 backdrop-blur sm:px-7">
                <div class="flex items-center gap-3">
                    <button class="grid size-10 place-items-center rounded-lg border border-[#d3d8df] text-slate-600 transition-colors hover:bg-[#F9F7F5] lg:hidden" aria-label="Buka menu" :aria-expanded="mobileOpen" @click="mobileOpen = true"><Menu :size="19" /></button>
                    <div class="hidden text-sm text-slate-500 sm:block">Kasuari Care <span class="mx-2 text-[#c5c3bb]">/</span><span class="font-semibold text-clinic-900">{{ pageTitle }}</span></div>
                    <span class="text-sm font-semibold text-clinic-900 sm:hidden">{{ pageTitle }}</span>
                </div>
                <div class="flex items-center gap-2.5 text-xs text-slate-500 sm:gap-3">
                    <span class="hidden sm:block">{{ new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date()) }}</span>
                    <span class="grid size-8 place-items-center rounded-full bg-clinic-100 text-xs font-semibold text-clinic-900">{{ user?.name?.charAt(0) ?? 'K' }}</span><span class="hidden font-medium text-slate-600 sm:inline">{{ user?.name }}</span>
                </div>
            </header>
            <main class="mx-auto max-w-[1480px] px-4 py-7 sm:px-7 sm:py-9">
                <div v-if="page.props.flash?.success" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-800">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-800">{{ page.props.flash.error }}</div>
                <slot />
            </main>
        </div>

        <div v-if="mobileOpen" class="fixed inset-0 z-40 lg:hidden">
            <button aria-label="Tutup menu" class="absolute inset-0 bg-slate-950/35" @click="mobileOpen = false"></button>
            <aside class="absolute inset-y-0 left-0 flex w-[min(84vw,320px)] flex-col bg-white shadow-xl" aria-label="Menu utama">
                <div class="flex h-[76px] items-center justify-between border-b border-[#efede6] px-5">
                    <div class="flex items-center gap-3"><img :src="'/logo-klinik.png'" alt="Logo Kasuari Care" class="size-10 shrink-0 rounded-xl object-contain"><b class="text-base text-clinic-900">Kasuari Care</b></div>
                    <button aria-label="Tutup menu" class="grid size-10 place-items-center rounded-lg text-slate-500 hover:bg-[#F9F7F5]" @click="mobileOpen = false"><X :size="19" /></button>
                </div>
                <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-4 pt-3" aria-label="Menu utama">
                    <section v-for="group in navGroups" :key="group.label">
                        <h2>
                            <button
                                type="button"
                                class="flex min-h-11 w-full items-center justify-between rounded-lg px-3.5 text-left text-[11px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700"
                                :class="expandedNavGroup === group.label ? 'text-clinic-900' : 'text-slate-500 hover:bg-[#F9F7F5]'"
                                :aria-expanded="expandedNavGroup === group.label"
                                :aria-controls="navGroupId('mobile', group.label)"
                                @click="toggleNavGroup(group.label)"
                            >
                                {{ group.label }}
                                <ChevronDown :size="15" class="shrink-0 transition-transform duration-150" :class="expandedNavGroup === group.label ? 'rotate-180' : ''" aria-hidden="true" />
                            </button>
                        </h2>
                        <div v-show="expandedNavGroup === group.label" :id="navGroupId('mobile', group.label)" class="space-y-1 pt-1">
                            <Link v-for="item in group.items" :key="item.href" :href="item.href" @click="mobileOpen = false"
                                class="flex h-11 items-center gap-3 rounded-lg px-3.5 text-sm font-medium focus-visible:outline-offset-1"
                                :aria-current="isActive(item.href) ? 'page' : undefined"
                                :class="isActive(item.href) ? 'bg-clinic-50 text-clinic-900' : 'text-slate-600 hover:bg-[#F9F7F5]'">
                                <component :is="item.icon" :size="18" />{{ item.label }}
                            </Link>
                        </div>
                    </section>
                </nav>
                <div class="mt-auto border-t border-[#efede6] p-4">
                    <div class="mb-3 flex items-center gap-3 rounded-xl bg-[#F9F7F5] p-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-coral-100 text-sm font-semibold text-coral-700">{{ user?.name?.charAt(0) ?? 'K' }}</span>
                        <span class="min-w-0"><b class="block truncate text-sm">{{ user?.name }}</b><small class="mt-0.5 block text-xs capitalize text-slate-500">{{ roles[0]?.replace('_', ' ') }}</small></span>
                    </div>
                    <button class="flex h-11 w-full items-center gap-2 rounded-lg px-3 text-sm text-slate-600 hover:bg-[#F9F7F5]" @click="signOut"><LogOut :size="17" /> Keluar dari akun</button>
                </div>
            </aside>
        </div>
    </div>
</template>
