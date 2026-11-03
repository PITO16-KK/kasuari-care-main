<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Activity, ArrowLeft, Check } from '@lucide/vue';

const page = usePage();
const form = useForm({ child_name: '', birth_date: '', gender: '', parent_name: '', parent_relation: 'mother', whatsapp: '', email: '', address: '', complaint: '' });
function submit() { form.post('/daftar'); }
</script>

<template>
    <main class="min-h-screen bg-[#F9F7F5] px-4 py-8 sm:px-8 sm:py-12">
        <div class="mx-auto max-w-[760px]">
            <Link href="/login" class="inline-flex items-center gap-2 text-sm font-semibold text-clinic-700 transition-colors hover:text-clinic-900"><ArrowLeft :size="16" /> Kembali ke halaman masuk</Link>
            <div class="mt-6 overflow-hidden rounded-2xl border border-[#e9e6de] bg-white shadow-sm">
                <div class="flex items-center gap-3 border-b border-[#efede6] px-5 py-5 sm:px-8"><span class="grid size-11 place-items-center rounded-xl bg-coral-500 text-clinic-900"><Activity :size="20" /></span><div><div class="text-xs font-semibold tracking-wide text-clinic-700">KASUARI CARE</div><h1 class="mt-1 text-xl font-semibold text-clinic-900">Pendaftaran pasien baru</h1></div></div>
                <div v-if="page.props.flash?.success" role="status" aria-live="polite" class="m-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 sm:m-8"><Check :size="19" aria-hidden="true" class="shrink-0" /><p class="font-semibold">{{ page.props.flash.success }}</p></div>
                <form v-else class="space-y-7 px-5 py-6 sm:px-8 sm:py-8" @submit.prevent="submit">
                    <section><h2 class="text-base font-semibold text-clinic-900">Informasi anak</h2><div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium text-slate-700 sm:col-span-2">Nama lengkap anak *<input v-model="form.child_name" class="form-input" required maxlength="160"><span v-if="form.errors.child_name" class="form-error">{{ form.errors.child_name }}</span></label>
                        <label class="text-sm font-medium text-slate-700">Tanggal lahir<input v-model="form.birth_date" type="date" class="form-input"></label>
                        <label class="text-sm font-medium text-slate-700">Jenis kelamin<select v-model="form.gender" class="form-input"><option value="">Pilih</option><option value="male">Laki-laki</option><option value="female">Perempuan</option></select></label>
                        <label class="text-sm font-medium text-slate-700 sm:col-span-2">Alamat<input v-model="form.address" class="form-input" maxlength="2000"></label>
                        <label class="text-sm font-medium text-slate-700 sm:col-span-2">Keluhan atau kebutuhan utama *<textarea v-model="form.complaint" rows="3" class="form-input resize-y" required maxlength="3000" placeholder="Ceritakan singkat hal yang ingin dikonsultasikan"></textarea><span v-if="form.errors.complaint" class="form-error">{{ form.errors.complaint }}</span></label>
                    </div></section>
                    <section class="border-t border-[#efede6] pt-6"><h2 class="text-base font-semibold text-clinic-900">Orang tua atau wali</h2><div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium text-slate-700">Nama orang tua atau wali *<input v-model="form.parent_name" class="form-input" required maxlength="160"></label>
                        <label class="text-sm font-medium text-slate-700">Hubungan<select v-model="form.parent_relation" class="form-input"><option value="mother">Ibu</option><option value="father">Ayah</option><option value="guardian">Wali</option></select></label>
                        <label class="text-sm font-medium text-slate-700">Nomor WhatsApp *<input v-model="form.whatsapp" type="tel" class="form-input" required maxlength="30" placeholder="08xx xxxx xxxx"><span v-if="form.errors.whatsapp" class="form-error">{{ form.errors.whatsapp }}</span></label>
                        <label class="text-sm font-medium text-slate-700">Email<input v-model="form.email" type="email" class="form-input" maxlength="180"></label>
                    </div></section>
                    <div class="flex flex-col-reverse justify-between gap-4 border-t border-[#efede6] pt-5 sm:flex-row sm:items-center"><p class="max-w-sm text-xs leading-5 text-slate-500">Dengan mengirim formulir ini, Anda mengizinkan tim klinik menghubungi nomor yang dicantumkan.</p><button :disabled="form.processing" class="h-12 rounded-lg bg-clinic-700 px-5 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 disabled:opacity-60">{{ form.processing ? 'Mengirim...' : 'Kirim pendaftaran' }}</button></div>
                </form>
            </div>
            <p class="mt-5 text-center text-xs text-slate-500">Informasi anak dijaga sebagai data kesehatan yang bersifat pribadi.</p>
        </div>
    </main>
</template>

<style scoped>
.form-input { display: block; width: 100%; min-height: 46px; margin-top: 8px; border: 1px solid #c6cdd5; border-radius: 8px; background: #fff; padding: 10px 12px; color: #334155; font-size: 14px; font-weight: 400; outline: none; transition: border-color 150ms, box-shadow 150ms; }
.form-input:focus { border-color: #405f91; box-shadow: 0 0 0 4px #dce8f5; }
.form-error { display: block; margin-top: 5px; color: #be123c; font-size: 12px; font-weight: 500; }
</style>
