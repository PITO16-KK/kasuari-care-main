<script setup>
import { router } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Clock3, Phone, Search } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

defineProps({ registrations: Object });
function convert(item) {
    if (window.confirm(`Konversi pendaftaran ${item.child_name} menjadi data pasien?`)) {
        router.post(`/registrations/${item.id}/convert`);
    }
}
const date = (value) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
</script>

<template>
    <MainLayout>
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase text-clinic-600">Front office</p><h1 class="mt-1 text-2xl font-bold text-clinic-900">Pendaftaran masuk</h1><p class="mt-1 text-sm text-slate-500">Tindak lanjuti keluarga dan buat profil pasien dari pendaftaran publik.</p></div><a href="/daftar" target="_blank" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">Buka form publik <ArrowUpRight :size="15" /></a></div>
        <div class="mt-6 grid gap-3 sm:grid-cols-3"><div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-[11px] font-semibold text-slate-500">Total pendaftaran</div><div class="mt-2 text-2xl font-bold text-clinic-900">{{ registrations.total }}</div></div><div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-[11px] font-semibold text-slate-500">Halaman ini</div><div class="mt-2 text-2xl font-bold text-clinic-900">{{ registrations.data.length }}</div></div><div class="rounded-lg border border-coral-100 bg-coral-50 p-4"><div class="text-[11px] font-semibold text-coral-700">Menunggu follow-up</div><div class="mt-2 text-2xl font-bold text-coral-700">{{ registrations.data.filter((r) => r.status !== 'converted').length }}</div></div></div>
        <section class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-bold text-clinic-900">Antrean keluarga</h2><p class="mt-1 text-[11px] text-slate-500">Data masuk dari formulir pendaftaran publik</p></div><Search :size="17" class="text-slate-400" /></div>
            <div v-if="registrations.data.length" class="divide-y divide-slate-100">
                <article v-for="item in registrations.data" :key="item.id" class="grid gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_180px_190px] md:items-center">
                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-bold text-slate-800">{{ item.child_name }}</h3><span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold capitalize text-slate-600">{{ item.status.replace('_', ' ') }}</span></div><p class="mt-1 text-xs text-slate-500">{{ item.parent_name }} · {{ item.parent_relation }}</p><p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-600">{{ item.complaint }}</p></div>
                    <div class="space-y-2 text-xs text-slate-500"><div class="flex items-center gap-2"><Phone :size="14" /> {{ item.whatsapp }}</div><div class="flex items-center gap-2"><Clock3 :size="14" /> {{ date(item.submitted_at) }}</div></div>
                    <button v-if="!item.patient_id" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-clinic-700 px-3 text-xs font-bold text-white hover:bg-clinic-900" @click="convert(item)"><Check :size="15" /> Buat profil pasien</button><a v-else :href="`/patients?q=${item.patient.patient_code}`" class="text-xs font-bold text-clinic-700">{{ item.patient.patient_code }} · {{ item.patient.name }}</a>
                </article>
            </div><div v-else class="grid min-h-[220px] place-items-center text-center"><div><ClipboardList :size="25" class="mx-auto text-slate-300" /><p class="mt-3 text-sm font-semibold text-slate-700">Belum ada pendaftaran</p><p class="mt-1 text-xs text-slate-500">Pendaftaran publik yang masuk akan muncul di sini.</p></div></div>
            <div v-if="registrations.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4"><a v-for="link in registrations.links" :key="link.label" :href="link.url ?? '# '" class="rounded border px-3 py-2 text-xs" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600'" v-html="link.label"></a></div>
        </section>
    </MainLayout>
</template>