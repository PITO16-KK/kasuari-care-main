<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

const form = useForm({ email: '', password: '', remember: true });
function submit() { form.post('/login'); }
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-[#F9F7F5] px-4 py-8 sm:px-8">
        <div class="grid w-full max-w-[980px] overflow-hidden rounded-2xl border border-[#e9e6de] bg-white shadow-sm lg:grid-cols-[0.9fr_1.1fr]">
            <section class="flex flex-col justify-between bg-clinic-50 p-7 sm:p-10 lg:p-12">
                <div class="flex items-center gap-3">
                    <img :src="'/logo-klinik.png'" alt="Logo Kasuari Care" class="size-11 shrink-0 rounded-xl object-contain">
                    <div><b class="block text-base text-clinic-900">Kasuari Care</b><span class="mt-0.5 block text-sm text-slate-600">Rumah Terapi Anak</span></div>
                </div>
                <div class="py-10 lg:py-16">
                    <p class="text-sm font-semibold text-clinic-700">Untuk tim klinik</p>
                    <h1 class="mt-3 max-w-sm text-3xl font-semibold leading-tight tracking-tight text-clinic-900 sm:text-4xl">Dampingi setiap langkah tumbuh kembang.</h1>
                    <p class="mt-4 max-w-sm text-base leading-7 text-slate-600">Kelola jadwal, catatan sesi, dan komunikasi keluarga dalam satu tempat.</p>
                </div>
                <p class="text-sm text-slate-500">Ruang kerja tim Kasuari Care</p>
            </section>

            <section class="flex items-center justify-center px-6 py-10 sm:px-12 lg:px-14">
                <div class="w-full max-w-[390px]">
                    <p class="text-sm font-semibold text-clinic-700">Senang bertemu lagi</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-clinic-900">Masuk ke akun klinik</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Gunakan email dan kata sandi yang diberikan oleh admin.</p>
                    <form class="mt-8 space-y-5" @submit.prevent="submit">
                        <label class="block text-sm font-medium text-slate-700">Email
                            <input v-model="form.email" type="email" autocomplete="username" class="mt-2 h-12 w-full rounded-lg border border-[#d9d8d0] px-3.5 text-sm outline-none transition focus:border-clinic-600 focus:ring-4 focus:ring-clinic-100" placeholder="nama@klinik.id" required>
                            <span v-if="form.errors.email" class="mt-1.5 block text-sm text-rose-600">{{ form.errors.email }}</span>
                        </label>
                        <label class="block text-sm font-medium text-slate-700">Kata sandi
                            <input v-model="form.password" type="password" autocomplete="current-password" class="mt-2 h-12 w-full rounded-lg border border-[#d9d8d0] px-3.5 text-sm outline-none transition focus:border-clinic-600 focus:ring-4 focus:ring-clinic-100" placeholder="Masukkan kata sandi" required>
                            <span v-if="form.errors.password" class="mt-1.5 block text-sm text-rose-600">{{ form.errors.password }}</span>
                        </label>
                        <label class="flex items-center gap-2.5 text-sm text-slate-600"><input v-model="form.remember" type="checkbox" class="size-4 rounded border-slate-300 accent-clinic-700"> Ingat saya</label>
                        <button :disabled="form.processing" class="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-clinic-700 text-sm font-semibold text-white transition-colors hover:bg-clinic-900 disabled:opacity-60">{{ form.processing ? 'Memeriksa...' : 'Masuk' }} <ArrowRight :size="16" /></button>
                    </form>
                    <div class="mt-8 border-t border-[#efede6] pt-5 text-center"><Link href="/daftar" class="text-sm font-semibold text-clinic-700 hover:text-clinic-900">Pendaftaran pasien baru <span aria-hidden="true">→</span></Link></div>
                </div>
            </section>
        </div>
    </main>
</template>
