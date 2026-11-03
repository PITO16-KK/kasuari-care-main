<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Stethoscope, UserRoundPlus } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

defineProps({ therapists: Object });

const form = useForm({
    name: '',
    email: '',
    phone: '',
    specialty: '',
    license_number: '',
    password: '',
    password_confirmation: '',
});
const editingProfile = ref(null);
const editForm = useForm({
    name: '',
    email: '',
    phone: '',
    specialty: '',
    license_number: '',
    is_active: true,
});

function submit() {
    form.post('/therapists', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function startEditing(profile) {
    editingProfile.value = profile;
    editForm.clearErrors();
    editForm.name = profile.user.name;
    editForm.email = profile.user.email;
    editForm.phone = profile.user.phone ?? '';
    editForm.specialty = profile.specialty ?? '';
    editForm.license_number = profile.license_number ?? '';
    editForm.is_active = Boolean(profile.user.is_active);
}

function cancelEditing() {
    editingProfile.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function update() {
    if (!editingProfile.value) {
        return;
    }

    editForm.put(`/therapists/${editingProfile.value.id}`, {
        preserveScroll: true,
        onSuccess: cancelEditing,
    });
}
</script>

<template>
    <MainLayout>
        <header class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-clinic-700">Data klinik</p>
                <h1 class="mt-1 text-2xl font-semibold text-clinic-900">Terapis</h1>
                <p class="mt-2 text-sm text-slate-600">Kelola informasi akun dan profil terapis klinik.</p>
            </div>
            <Link href="/patients" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-[#F9F7F5]">Kembali ke pasien</Link>
        </header>

        <div class="mt-6 grid items-start gap-5 xl:grid-cols-[minmax(320px,0.85fr)_minmax(0,1.4fr)]">
            <section v-if="editingProfile" class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="therapist-form-heading">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-clinic-50 text-clinic-700"><Stethoscope :size="18" aria-hidden="true" /></span>
                    <div>
                        <h2 id="therapist-form-heading" class="text-base font-semibold text-clinic-900">Edit data terapis</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-600">Perbarui informasi akun dan profil {{ editingProfile.user.name }}.</p>
                    </div>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="update">
                    <label class="field-label">Nama lengkap *<input v-model="editForm.name" class="field-input" maxlength="160" autocomplete="name" required><span v-if="editForm.errors.name" class="field-error">{{ editForm.errors.name }}</span></label>
                    <label class="field-label">Email untuk login *<input v-model="editForm.email" type="email" class="field-input" maxlength="255" autocomplete="email" required><span v-if="editForm.errors.email" class="field-error">{{ editForm.errors.email }}</span></label>
                    <label class="field-label">Nomor telepon<input v-model="editForm.phone" type="tel" class="field-input" maxlength="30" autocomplete="tel"><span v-if="editForm.errors.phone" class="field-error">{{ editForm.errors.phone }}</span></label>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <label class="field-label">Spesialisasi<input v-model="editForm.specialty" class="field-input" maxlength="160"><span v-if="editForm.errors.specialty" class="field-error">{{ editForm.errors.specialty }}</span></label>
                        <label class="field-label">Nomor izin praktik<input v-model="editForm.license_number" class="field-input" maxlength="100"><span v-if="editForm.errors.license_number" class="field-error">{{ editForm.errors.license_number }}</span></label>
                    </div>
                    <label class="field-label">
                        Status akun
                        <select v-model="editForm.is_active" class="field-input">
                            <option :value="true">Aktif</option>
                            <option :value="false">Nonaktif</option>
                        </select>
                        <span v-if="editForm.errors.is_active" class="field-error">{{ editForm.errors.is_active }}</span>
                    </label>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="cancelEditing">Batal</button>
                        <button :disabled="editForm.processing" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-not-allowed disabled:opacity-60">
                            {{ editForm.processing ? 'Menyimpan...' : 'Simpan perubahan' }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-else class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="therapist-form-heading">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-clinic-50 text-clinic-700"><UserRoundPlus :size="18" /></span>
                    <div>
                        <h2 id="therapist-form-heading" class="text-base font-semibold text-clinic-900">Tambah terapis</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Akun yang dibuat bisa langsung digunakan untuk masuk ke aplikasi.</p>
                    </div>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <label class="field-label">Nama lengkap *<input v-model="form.name" class="field-input" maxlength="160" autocomplete="name" required><span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span></label>
                    <label class="field-label">Email untuk login *<input v-model="form.email" type="email" class="field-input" maxlength="255" autocomplete="email" required><span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span></label>
                    <label class="field-label">Nomor telepon<input v-model="form.phone" type="tel" class="field-input" maxlength="30" autocomplete="tel"><span v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</span></label>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <label class="field-label">Spesialisasi<input v-model="form.specialty" class="field-input" maxlength="160" placeholder="Contoh: Terapi wicara"><span v-if="form.errors.specialty" class="field-error">{{ form.errors.specialty }}</span></label>
                        <label class="field-label">Nomor izin praktik<input v-model="form.license_number" class="field-input" maxlength="100"><span v-if="form.errors.license_number" class="field-error">{{ form.errors.license_number }}</span></label>
                    </div>
                    <label class="field-label">Kata sandi awal *<input v-model="form.password" type="password" class="field-input" minlength="8" autocomplete="new-password" required><span class="mt-1 block text-[11px] font-normal text-slate-500">Minimal 8 karakter. Sampaikan kepada terapis secara aman.</span><span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span></label>
                    <label class="field-label">Ulangi kata sandi *<input v-model="form.password_confirmation" type="password" class="field-input" minlength="8" autocomplete="new-password" required><span v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</span></label>
                    <button :disabled="form.processing" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-clinic-700 px-4 text-sm font-semibold text-white hover:bg-clinic-900 disabled:cursor-not-allowed disabled:opacity-60">
                        <Stethoscope :size="17" />{{ form.processing ? 'Menyimpan...' : 'Buat akun terapis' }}
                    </button>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <header class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-clinic-900">Daftar terapis</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ therapists.total }} akun terapis terdaftar</p>
                </header>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead><tr class="bg-[#F9F7F5] text-[11px] font-semibold text-slate-500"><th class="px-5 py-3">Terapis</th><th class="px-4 py-3">Spesialisasi</th><th class="px-4 py-3">No. izin</th><th class="px-4 py-3">Status akun</th><th class="px-4 py-3"><span class="sr-only">Aksi</span></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="profile in therapists.data" :key="profile.id" class="hover:bg-slate-50/70">
                                <td class="px-5 py-4"><p class="text-sm font-medium text-slate-800">{{ profile.user.name }}</p><p class="mt-1 text-xs text-slate-500">{{ profile.user.email }}</p><p v-if="profile.user.phone" class="mt-1 text-xs text-slate-500">{{ profile.user.phone }}</p></td>
                                <td class="px-4 py-4 text-sm text-slate-600">{{ profile.specialty || 'Belum diisi' }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600">{{ profile.license_number || 'Belum diisi' }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="profile.user.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ profile.user.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="px-4 py-4 text-right"><button type="button" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" :aria-label="`Edit data ${profile.user.name}`" @click="startEditing(profile)">Edit</button></td>
                            </tr>
                            <tr v-if="!therapists.data.length"><td colspan="5" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-700">Belum ada terapis</p><p class="mt-1 text-xs text-slate-500">Isi formulir untuk menambahkan akun pertama.</p></td></tr>
                        </tbody>
                    </table>
                </div>

                <footer v-if="therapists.links?.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                    <p class="text-xs text-slate-500">Menampilkan {{ therapists.from ?? 0 }}–{{ therapists.to ?? 0 }} dari {{ therapists.total }}</p>
                    <nav class="flex flex-wrap gap-1" aria-label="Halaman terapis">
                        <template v-for="link in therapists.links" :key="link.label">
                            <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-lg border px-3 py-2 text-xs" :class="link.active ? 'border-clinic-700 bg-clinic-700 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" v-html="link.label" />
                            <span v-else class="rounded-lg border border-slate-100 px-3 py-2 text-xs text-slate-300" v-html="link.label"></span>
                        </template>
                    </nav>
                </footer>
            </section>
        </div>
    </MainLayout>
</template>

<style scoped>
.field-label { display: block; color: #475569; font-size: 12px; font-weight: 600; }
.field-input { display: block; width: 100%; min-height: 42px; margin-top: 7px; border: 1px solid #d8dde5; border-radius: 8px; background: white; padding: 9px 11px; color: #334155; font-size: 13px; font-weight: 400; outline: none; }
.field-input:focus { border-color: #6A8EC4; box-shadow: 0 0 0 3px rgb(106 142 196 / 16%); }
.field-error { display: block; margin-top: 5px; color: #be123c; font-size: 11px; font-weight: 400; }
</style>
