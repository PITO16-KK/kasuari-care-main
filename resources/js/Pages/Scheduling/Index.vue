<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, Clock3, Sparkles, UserRound } from '@lucide/vue';
import MainLayout from '../../Layouts/MainLayout.vue';

const props = defineProps({ appointments: Array, scheduledAssessments: Array, patients: Array, assessments: Array, initialPatientId: Number, initialAssessmentId: Number, patientPackages: Array, patientEvaluationSessionCounts: Array, therapyTypes: Array, therapists: Array, patientTherapists: Array, rooms: Array });
const form = useForm({ patient_id: props.initialPatientId ?? '', assessment_id: props.initialAssessmentId ?? '', therapy_type_id: '', therapist_profile_id: '', room_id: '', starts_at: '' });
const cancelForm = useForm({});
const assessmentRescheduleForm = useForm({ scheduled_at: '', therapist_profile_id: '' });
const agendaEntries = computed(() => [
    ...props.appointments.map((appointment) => ({ ...appointment, agendaType: 'therapy' })),
    ...props.scheduledAssessments.map((assessment) => ({ ...assessment, agendaType: 'assessment', starts_at: assessment.scheduled_at })),
].sort((firstEntry, secondEntry) => new Date(firstEntry.starts_at) - new Date(secondEntry.starts_at)));
const editingAppointmentId = ref(null);
const editingAssessmentId = ref(null);
const scheduleDate = ref('');
const scheduleTime = ref('');
const assessmentScheduleDate = ref('');
const assessmentScheduleTime = ref('');
const agendaView = ref('calendar');
const whatsappNotice = ref('');
const selectedWeekDate = ref(jakartaDateOf(new Date().toISOString()));
const selectedType = computed(() => props.therapyTypes.find((type) => type.id === Number(form.therapy_type_id)));
const selectedPatientTherapists = computed(() => props.patientTherapists.filter((assignment) => {
    return Number(assignment.patient_id) === Number(form.patient_id)
        && Number(assignment.therapy_type_id) === Number(form.therapy_type_id);
}));
const scheduleTherapists = computed(() => {
    if (['TO', 'TW'].includes(selectedType.value?.code)) {
        return selectedPatientTherapists.value.map((assignment) => ({
            id: assignment.therapist_profile_id,
            name: assignment.therapist_name,
            specialty: assignment.specialty,
        }));
    }

    return props.therapists.map((therapist) => ({
        id: therapist.id,
        name: therapist.user.name,
        specialty: therapist.specialty,
    }));
});
const selectedPatientAssessments = computed(() => props.assessments.filter((assessment) => Number(assessment.patient_id) === Number(form.patient_id)));
const selectedAssessment = computed(() => selectedPatientAssessments.value.find((assessment) => Number(assessment.id) === Number(form.assessment_id)));
const selectedPatientPackages = computed(() => props.patientPackages.filter((item) => Number(item.patient_id) === Number(form.patient_id)));
const appointmentBeingEdited = computed(() => props.appointments.find((item) => item.id === editingAppointmentId.value));
const assessmentBeingEdited = computed(() => props.scheduledAssessments.find((item) => Number(item.id) === Number(editingAssessmentId.value)));
const packageCheckTime = computed(() => form.starts_at ? Date.parse(form.starts_at) : Date.now());
const isPackageUsableForSchedule = (item) => item.status === 'active'
    && packageTimestamp(item.starts_at) <= packageCheckTime.value
    && packageTimestamp(item.expires_at) >= packageCheckTime.value;
const selectedPatientEvaluationSessions = computed(() => Number(props.patientEvaluationSessionCounts.find((item) => {
    return Number(item.patient_id) === Number(form.patient_id);
})?.sessions_since_evaluation ?? 0));
const patientEvaluationIsDue = computed(() => selectedPatientEvaluationSessions.value >= 25);
const selectedPatientCurrentPackages = computed(() => selectedPatientPackages.value.filter(isPackageUsableForSchedule));
const currentPackageRemainingSessions = computed(() => selectedPatientCurrentPackages.value.reduce((total, item) => {
    return total + packageRemainingSessions(item);
}, 0));
const currentPackageGrantedSessions = computed(() => selectedPatientCurrentPackages.value.reduce((total, item) => {
    return total + packageGrantedSessions(item);
}, 0));
const usableDurationPackage = computed(() => selectedPatientPackages.value.find((item) => {
    return isPackageUsableForSchedule(item)
        && item.duration_entitlements?.some((entitlement) => durationEntitlementHasSchedulingSlot(item, entitlement));
}));
const usableDurationEntitlement = computed(() => usableDurationPackage.value?.duration_entitlements
    ?.find((entitlement) => durationEntitlementHasSchedulingSlot(usableDurationPackage.value, entitlement)));
const usableLegacyPackage = computed(() => selectedPatientPackages.value.find((item) => {
    return isPackageUsableForSchedule(item)
        && item.legacy_entitlements?.some((entitlement) => legacyPackageHasSchedulingSlot(item, entitlement, Number(form.therapy_type_id)));
}));
const selectedPatientHasAnyUsablePackage = computed(() => selectedPatientPackages.value.some((item) => {
    return isPackageUsableForSchedule(item)
        && (durationPackageHasSchedulingSlot(item)
            || item.legacy_entitlements?.some((entitlement) => legacyPackageHasSchedulingSlot(item, entitlement)));
}));
const selectedPatientHasUsablePackage = computed(() => Boolean(
    Number(form.patient_id)
    && Number(form.therapy_type_id)
    && (usableDurationPackage.value || usableLegacyPackage.value),
));
const packageAlertMessage = computed(() => {
    if (!Number(form.patient_id)) {
        return '';
    }

    if (!Number(form.therapy_type_id)) {
        return selectedPatientHasAnyUsablePackage.value
            ? ''
            : 'Jadwal tetap dapat dibuat tanpa paket aktif. Pembayaran atau kuota paket diperlukan sebelum laporan sesi dikirim.';
    }

    if (!selectedPatientHasUsablePackage.value) {
        return `Kuota paket untuk ${selectedType.value?.name ?? 'terapi ini'} tidak tersedia pada tanggal yang dipilih. Jadwal tetap dapat dibuat, tetapi pembayaran atau kuota paket diperlukan sebelum laporan sesi dikirim.`;
    }

    return '';
});
const effectiveDuration = computed(() => Number(usableDurationEntitlement.value?.duration_minutes ?? selectedType.value?.default_duration_minutes ?? 60));
const durationDescription = computed(() => {
    if (usableDurationPackage.value) {
        return `Mengikuti paket ${usableDurationPackage.value.name}`;
    }

    if (usableLegacyPackage.value) {
        return `Mengikuti paket ${usableLegacyPackage.value.name} · durasi standar ${selectedType.value.name}`;
    }

    return selectedType.value ? `Durasi standar ${selectedType.value.name}` : 'Pilih terapi untuk menentukan durasi';
});
const busyTherapistIds = computed(() => {
    if (!form.starts_at) {
        return new Set();
    }

    return therapistConflictsForInterval(form.starts_at, effectiveDuration.value, editingAppointmentId.value);
});
const selectedTherapistHasConflict = computed(() => busyTherapistIds.value.has(Number(form.therapist_profile_id)));
const busyAssessmentTherapistIds = computed(() => {
    if (!assessmentRescheduleForm.scheduled_at) {
        return new Set();
    }

    return therapistConflictsForInterval(
        assessmentRescheduleForm.scheduled_at,
        60,
        null,
        editingAssessmentId.value,
    );
});
const endTime = computed(() => {
    if (!form.starts_at) {
        return 'Pilih waktu mulai';
    }

    const startsAt = new Date(form.starts_at);
    const endsAt = new Date(startsAt.getTime() + effectiveDuration.value * 60000);

    return new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        timeZoneName: 'short',
        hourCycle: 'h23',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(endsAt);
});

function jakartaDateTimeParts(value) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta',
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).formatToParts(new Date(value));

    return Object.fromEntries(parts.map(({ type, value: partValue }) => [type, partValue]));
}
function jakartaDateOf(value) {
    const parts = jakartaDateTimeParts(value);

    return `${parts.year}-${parts.month}-${parts.day}`;
}
function appointmentTimeOfDay(value) {
    const parts = jakartaDateTimeParts(value);

    return `${parts.hour}:${parts.minute}`;
}
function therapistConflictsForInterval(startsAt, durationMinutes, ignoreAppointmentId = null, ignoreAssessmentId = null) {
    const intervalStart = Date.parse(startsAt);
    const intervalEnd = intervalStart + durationMinutes * 60000;

    if (!Number.isFinite(intervalStart)) {
        return new Set();
    }

    const conflictingTherapistIds = new Set();

    props.appointments.forEach((appointment) => {
        if (appointment.id === ignoreAppointmentId || appointment.status === 'cancelled' || !appointment.therapist_profile_id) {
            return;
        }

        const appointmentStart = Date.parse(appointment.starts_at);
        const appointmentEnd = Date.parse(appointment.ends_at);

        if (appointmentStart < intervalEnd && appointmentEnd > intervalStart) {
            conflictingTherapistIds.add(Number(appointment.therapist_profile_id));
        }
    });

    props.scheduledAssessments.forEach((assessment) => {
        if (Number(assessment.id) === Number(ignoreAssessmentId) || !assessment.therapist_profile_id) {
            return;
        }

        const assessmentStart = Date.parse(assessment.scheduled_at);
        const assessmentEnd = assessmentStart + 60 * 60000;

        if (assessmentStart < intervalEnd && assessmentEnd > intervalStart) {
            conflictingTherapistIds.add(Number(assessment.therapist_profile_id));
        }
    });

    return conflictingTherapistIds;
}
function jakartaDateTimeAsUtcIso(date, time) {
    return new Date(`${date}T${time}:00+07:00`).toISOString();
}
function isWibTime(value) {
    return /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
}
function formatWibTimeInput(value) {
    const digits = String(value).replace(/\D/g, '').slice(0, 4);

    return digits.length > 2 ? `${digits.slice(0, 2)}:${digits.slice(2)}` : digits;
}
function updateScheduleTime(event) {
    scheduleTime.value = formatWibTimeInput(event.target.value);
}
function updateAssessmentScheduleTime(event) {
    assessmentScheduleTime.value = formatWibTimeInput(event.target.value);
}
function isTherapistBusy(therapistProfileId) {
    return busyTherapistIds.value.has(Number(therapistProfileId));
}
function isEditingAppointmentCountedForPackage(item) {
    const appointment = appointmentBeingEdited.value;

    return appointment
        && Number(appointment.patient_id) === Number(item.patient_id)
        && packageTimestamp(appointment.starts_at) >= packageTimestamp(item.starts_at)
        && packageTimestamp(appointment.starts_at) <= packageTimestamp(item.expires_at);
}
function editingAppointmentDuration() {
    const appointment = appointmentBeingEdited.value;

    return appointment ? Math.round((new Date(appointment.ends_at) - new Date(appointment.starts_at)) / 60000) : 0;
}
function durationPackageHasSchedulingSlot(item) {
    return item.duration_entitlements?.some((entitlement) => durationEntitlementHasSchedulingSlot(item, entitlement)) ?? false;
}
function durationEntitlementHasSchedulingSlot(item, entitlement) {
    if (Number(entitlement.available_to_schedule) > 0) {
        return true;
    }

    return isEditingAppointmentCountedForPackage(item)
        && Number(entitlement.remaining_sessions) > 0
        && Number(entitlement.duration_minutes) === editingAppointmentDuration();
}
function legacyPackageHasSchedulingSlot(item, entitlement, therapyTypeId = null) {
    if (therapyTypeId !== null && Number(entitlement.therapy_type_id) !== therapyTypeId) {
        return false;
    }

    if (Number(entitlement.available_to_schedule) > 0) {
        return true;
    }

    const appointment = appointmentBeingEdited.value;

    return isEditingAppointmentCountedForPackage(item)
        && Number(entitlement.remaining_sessions) > 0
        && Number(appointment?.therapy_type_id) === Number(entitlement.therapy_type_id);
}
watch([scheduleDate, scheduleTime], ([date, time]) => {
    form.starts_at = date && isWibTime(time) ? jakartaDateTimeAsUtcIso(date, time) : '';
    if (form.starts_at) {
        form.clearErrors('starts_at');
    }
});
watch([() => form.patient_id, () => form.therapy_type_id], () => {
    if (form.therapist_profile_id && !scheduleTherapists.value.some((therapist) => {
        return Number(therapist.id) === Number(form.therapist_profile_id);
    })) {
        form.therapist_profile_id = '';
    }
});
watch([assessmentScheduleDate, assessmentScheduleTime], ([date, time]) => {
    assessmentRescheduleForm.scheduled_at = date && isWibTime(time) ? jakartaDateTimeAsUtcIso(date, time) : '';
    if (assessmentRescheduleForm.scheduled_at) {
        assessmentRescheduleForm.clearErrors('scheduled_at');
    }
});

function resetForm() {
    form.reset();
    form.patient_id = '';
    form.assessment_id = '';
    form.clearErrors();
    scheduleDate.value = '';
    scheduleTime.value = '';
    editingAppointmentId.value = null;
}
function clearAssessmentSelection() {
    form.assessment_id = '';
}
function handlePatientSelectionChange() {
    clearAssessmentSelection();
    form.therapist_profile_id = '';
    form.clearErrors('patient_id', 'therapist_profile_id');
}
function useRecommendation(recommendation) {
    form.therapy_type_id = recommendation.therapy_type_id;
}
function editAppointment(appointment) {
    resetAssessmentRescheduleForm();
    const parts = jakartaDateTimeParts(appointment.starts_at);
    editingAppointmentId.value = appointment.id;
    form.clearErrors();
    form.patient_id = appointment.patient_id;
    form.assessment_id = appointment.assessment_id ?? '';
    form.therapy_type_id = appointment.therapy_type_id;
    form.therapist_profile_id = appointment.therapist_profile_id;
    form.room_id = appointment.room_id;
    scheduleDate.value = `${parts.year}-${parts.month}-${parts.day}`;
    scheduleTime.value = `${parts.hour}:${parts.minute}`;
}
function resetAssessmentRescheduleForm() {
    assessmentRescheduleForm.reset();
    assessmentRescheduleForm.clearErrors();
    assessmentScheduleDate.value = '';
    assessmentScheduleTime.value = '';
    editingAssessmentId.value = null;
}
function editAssessmentSchedule(assessment) {
    resetForm();
    const parts = jakartaDateTimeParts(assessment.starts_at);
    editingAssessmentId.value = assessment.id;
    assessmentRescheduleForm.clearErrors();
    assessmentRescheduleForm.therapist_profile_id = assessment.therapist_profile_id ?? '';
    assessmentScheduleDate.value = `${parts.year}-${parts.month}-${parts.day}`;
    assessmentScheduleTime.value = `${parts.hour}:${parts.minute}`;
    nextTick(() => document.getElementById('assessment-reschedule-form')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
}
function submitAssessmentReschedule() {
    if (!assessmentScheduleDate.value || !isWibTime(assessmentScheduleTime.value)) {
        assessmentRescheduleForm.setError('scheduled_at', 'Isi tanggal dan jam format 24 jam, contoh 14:05 WIB.');
        return;
    }

    assessmentRescheduleForm.scheduled_at = jakartaDateTimeAsUtcIso(assessmentScheduleDate.value, assessmentScheduleTime.value);
    assessmentRescheduleForm.patch(`/assessments/${editingAssessmentId.value}/schedule`, {
        onSuccess: resetAssessmentRescheduleForm,
    });
}
function submit() {
    if (!scheduleDate.value || !isWibTime(scheduleTime.value)) {
        form.setError('starts_at', 'Isi tanggal dan jam format 24 jam, contoh 14:05 WIB.');
        return;
    }

    if (patientEvaluationIsDue.value) {
        form.setError('patient_id', 'Evaluasi wajib diselesaikan sebelum jadwal terapi berikutnya.');
        return;
    }

    if (selectedTherapistHasConflict.value) {
        return;
    }

    form.starts_at = scheduleDate.value && scheduleTime.value
        ? jakartaDateTimeAsUtcIso(scheduleDate.value, scheduleTime.value)
        : '';

    const options = { onSuccess: resetForm };

    if (editingAppointmentId.value) {
        form.put(`/scheduling/${editingAppointmentId.value}`, options);

        return;
    }

    form.post('/scheduling', options);
}
function cancelAppointment(appointment) {
    const shouldCancel = window.confirm('Batalkan jadwal ini? Jika kuota paket sudah terpakai, kuota akan dikembalikan.');

    if (!shouldCancel) {
        return;
    }

    cancelForm.clearErrors();
    cancelForm.patch(`/scheduling/${appointment.id}/cancel`, {
        onSuccess: () => {
            if (editingAppointmentId.value === appointment.id) {
                resetForm();
            }
        },
    });
}

const dateTime = (value) => new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    timeZoneName: 'short',
    hourCycle: 'h23',
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date(value));
const sessionDuration = (entry) => {
    const duration = Math.round((new Date(entry.ends_at) - new Date(entry.starts_at)) / 60000);

    return duration === 60 ? '1 jam' : `${duration} menit`;
};
const todayDate = computed(() => jakartaDateOf(new Date().toISOString()));
const calendarWeek = computed(() => {
    const monday = new Date(`${selectedWeekDate.value}T00:00:00Z`);
    const mondayOffset = (monday.getUTCDay() + 6) % 7;
    monday.setUTCDate(monday.getUTCDate() - mondayOffset);

    return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(monday);
        date.setUTCDate(monday.getUTCDate() + index);
        const dateKey = date.toISOString().slice(0, 10);

        return {
            date: dateKey,
            weekday: new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', weekday: 'short' }).format(date),
            dateLabel: new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', day: 'numeric', month: 'short' }).format(date),
            monthLabel: new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', month: 'short' }).format(date),
            dayNumber: date.getUTCDate(),
            isToday: dateKey === todayDate.value,
            entries: agendaEntries.value.filter((entry) => jakartaDateOf(entry.starts_at) === dateKey),
        };
    });
});
const calendarWeekEntries = computed(() => calendarWeek.value.flatMap((day) => day.entries));
const calendarWeekLabel = computed(() => {
    const formatDate = (date) => new Intl.DateTimeFormat('id-ID', {
        timeZone: 'UTC',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00Z`));

    return `${formatDate(calendarWeek.value[0].date)} – ${formatDate(calendarWeek.value[6].date)}`;
});
function changeCalendarWeek(offset) {
    const date = new Date(`${selectedWeekDate.value}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + offset * 7);
    selectedWeekDate.value = date.toISOString().slice(0, 10);
}
function showCurrentWeek() {
    selectedWeekDate.value = todayDate.value;
}
function packageRemainingSessions(item) {
    return Number(item.duration_remaining_sessions ?? 0) + Number(item.legacy_remaining_sessions ?? 0);
}
function packageTimestamp(value) {
    const normalizedValue = String(value).replace(' ', 'T');
    const hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(normalizedValue);

    return Date.parse(hasTimezone ? normalizedValue : `${normalizedValue}Z`);
}
function packageGrantedSessions(item) {
    return Number(item.duration_granted_sessions ?? 0) + Number(item.legacy_granted_sessions ?? 0);
}
function packageStatus(item) {
    if (item.status === 'active' && packageTimestamp(item.expires_at) < Date.now()) {
        return 'Kedaluwarsa';
    }

    if (item.status === 'active' && Number(item.duration_available_sessions) + Number(item.legacy_available_sessions) === 0 && packageRemainingSessions(item) > 0) {
        return 'Semua sesi terjadwal';
    }

    if (item.status === 'active' && packageRemainingSessions(item) < 1) {
        return 'Kuota habis';
    }

    return item.status === 'active' ? 'Aktif' : item.status === 'completed' ? 'Selesai' : item.status === 'expired' ? 'Kedaluwarsa' : item.status;
}
function expiryDate(value) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value));
}
function normalizeWhatsAppNumber(value) {
    const originalNumber = String(value ?? '').trim();

    if (!originalNumber) {
        return { number: null, error: 'Nomor WhatsApp utama pasien belum diisi.' };
    }

    if (!/^[+\d\s().-]+$/.test(originalNumber)) {
        return { number: null, error: 'Nomor WhatsApp utama tidak valid. Periksa kembali data pasien.' };
    }

    let digits = originalNumber.replace(/\D/g, '');

    if (digits.startsWith('00')) {
        digits = digits.slice(2);
    }

    if (digits.startsWith('0')) {
        digits = `62${digits.slice(1)}`;
    } else if (digits.startsWith('8')) {
        digits = `62${digits}`;
    }

    if (!/^628[1-9]\d{7,10}$/.test(digits)) {
        return { number: null, error: 'Nomor WhatsApp utama tidak valid. Gunakan nomor ponsel Indonesia yang aktif.' };
    }

    return { number: digits, error: null };
}
function sendWhatsAppReminder(appointment) {
    const { number, error } = normalizeWhatsAppNumber(appointment.patient?.primary_whatsapp);

    if (error) {
        whatsappNotice.value = `${error} Pasien: ${appointment.patient?.name || 'Tidak diketahui'}.`;

        return;
    }

    whatsappNotice.value = '';

    const date = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(appointment.starts_at));
    const therapistName = appointment.therapist?.user?.name || 'belum ditentukan';
    const message = `Halo, kami dari Kasuari Care ingin mengingatkan jadwal terapi ${appointment.patient.name} pada ${date}, pukul ${appointmentTimeOfDay(appointment.starts_at)} WIB. Jenis terapi: ${appointment.therapy_type.name}. Terapis: ${therapistName}.`;

    window.open(`https://wa.me/${number}?text=${encodeURIComponent(message)}`, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <MainLayout>
        <div class="space-y-6">
            <header>
                <p class="text-xs font-bold uppercase text-clinic-600">Operasional klinik</p>
                <h1 class="mt-1 text-2xl font-bold text-clinic-900">Jadwal terapi</h1>
                <p class="mt-1 text-sm text-slate-500">Jadwal ditolak bila terapis atau ruangan sedang digunakan.</p>
            </header>

            <section v-if="editingAssessmentId && assessmentBeingEdited" id="assessment-reschedule-form" class="rounded-xl border border-clinic-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-clinic-600">Assessment · {{ assessmentBeingEdited.patient.name }}</p>
                        <h2 class="mt-1 text-sm font-bold text-clinic-900">Ubah jadwal assessment</h2>
                        <p v-if="assessmentBeingEdited.status === 'awaiting_payment'" class="mt-1 text-xs text-amber-700">Pembayaran assessment masih tertunda. Jadwal dapat diubah sebelum assessment diselesaikan.</p>
                    </div>
                    <button type="button" class="min-h-11 rounded-md px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="resetAssessmentRescheduleForm">Tutup</button>
                </div>
                <form class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_auto] xl:items-end" @submit.prevent="submitAssessmentReschedule">
                    <label class="field-label">Tanggal baru
                        <input v-model="assessmentScheduleDate" type="date" class="field-input" required>
                    </label>
                    <label class="field-label">Jam baru (WIB, 24 jam)
                        <input v-model="assessmentScheduleTime" type="text" inputmode="numeric" autocomplete="off" maxlength="5" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" placeholder="14:05" title="Gunakan format 24 jam, contoh 14:05" class="field-input" required @input="updateAssessmentScheduleTime">
                        <span class="mt-1 block text-[10px] font-normal text-slate-500">Format HH:MM, contoh 14:05.</span>
                    </label>
                    <label class="field-label">Terapis sesi assessment *
                        <select v-model="assessmentRescheduleForm.therapist_profile_id" class="field-input" required>
                            <option value="">Pilih terapis</option>
                            <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id" :disabled="busyAssessmentTherapistIds.has(Number(therapist.id))">{{ therapist.user.name }} Â· {{ therapist.specialty || 'Terapis' }}{{ busyAssessmentTherapistIds.has(Number(therapist.id)) ? ' (sudah terjadwal)' : '' }}</option>
                        </select>
                        <span v-if="assessmentRescheduleForm.errors.therapist_profile_id" class="field-error">{{ assessmentRescheduleForm.errors.therapist_profile_id }}</span>
                    </label>
                    <div class="flex gap-2">
                        <button :disabled="assessmentRescheduleForm.processing" class="min-h-11 flex-1 rounded-md bg-clinic-700 px-4 text-xs font-bold text-white hover:bg-clinic-900 disabled:opacity-60">{{ assessmentRescheduleForm.processing ? 'Menyimpan...' : 'Simpan jadwal' }}</button>
                        <button type="button" class="min-h-11 rounded-md border border-slate-300 px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="resetAssessmentRescheduleForm">Batal</button>
                    </div>
                    <p v-if="assessmentRescheduleForm.errors.scheduled_at" role="alert" class="text-xs text-rose-700 sm:col-span-2 xl:col-span-4">{{ assessmentRescheduleForm.errors.scheduled_at }}</p>
                </form>
            </section>

            <div class="grid gap-4" :class="editingAssessmentId ? 'xl:grid-cols-1' : 'xl:grid-cols-[minmax(330px,0.8fr)_minmax(0,1.4fr)]'">
                <form v-if="!editingAssessmentId" class="h-fit rounded-lg border border-slate-200 bg-white p-5 sm:p-6" @submit.prevent="submit">
                    <div class="flex items-center gap-2">
                        <CalendarDays :size="18" class="text-clinic-700" />
                        <h2 class="text-sm font-bold text-clinic-900">{{ editingAppointmentId ? 'Ubah jadwal' : 'Buat jadwal baru' }}</h2>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-slate-500">Jadwal ditolak jika terapis atau ruangan memiliki sesi yang waktunya bertumpang tindih.</p>

                    <div class="mt-5 space-y-4">
                        <label class="field-label">
                            Pasien *
                            <select v-model="form.patient_id" class="field-input" required @change="handlePatientSelectionChange">
                                <option value="">Pilih pasien</option>
                                <option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.patient_code }} · {{ patient.name }}</option>
                            </select>
                            <span v-if="form.errors.patient_id" class="field-error">{{ form.errors.patient_id }}</span>
                        </label>

                        <label class="field-label">
                            Assessment selesai *
                            <select v-model="form.assessment_id" class="field-input" :disabled="!form.patient_id" required>
                                <option value="">Pilih assessment yang sudah selesai</option>
                                <option v-for="assessment in selectedPatientAssessments" :key="assessment.id" :value="assessment.id">{{ assessment.scheduled_at ? dateTime(assessment.scheduled_at) : dateTime(assessment.created_at) }} · {{ assessment.chief_complaint?.slice(0, 70) || 'Assessment' }}</option>
                            </select>
                            <span v-if="form.errors.assessment_id" class="field-error">{{ form.errors.assessment_id }}</span>
                        </label>

                        <div v-if="form.patient_id && !selectedPatientAssessments.length" role="alert" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-900">
                            Selesaikan assessment pasien sebelum membuat jadwal terapi.
                            <Link :href="`/assessments/create?patient_id=${form.patient_id}`" class="ml-1 inline-flex min-h-11 items-center font-bold text-amber-950 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-800">Jadwalkan assessment</Link>
                        </div>

                        <section v-if="selectedAssessment" class="rounded-md border border-clinic-100 bg-clinic-50 p-3" aria-live="polite">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-xs font-bold text-clinic-900">Rekomendasi dari assessment</h3>
                                <a :href="`/assessments/${selectedAssessment.id}/pdf`" target="_blank" rel="noopener" class="min-h-11 inline-flex items-center rounded-md px-2 text-[11px] font-semibold text-clinic-700 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700">Lihat hasil lengkap</a>
                            </div>
                            <p v-if="selectedAssessment.summary" class="mt-1 text-[11px] leading-5 text-clinic-800">{{ selectedAssessment.summary }}</p>
                            <div v-if="selectedAssessment.recommendations.length" class="mt-2 flex flex-wrap gap-2">
                                <button v-for="recommendation in selectedAssessment.recommendations" :key="recommendation.id" type="button" class="min-h-11 rounded-full border border-clinic-200 bg-white px-3 text-[11px] font-semibold text-clinic-800 hover:bg-clinic-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="useRecommendation(recommendation)">
                                    Gunakan {{ recommendation.therapy_type.name }} · {{ recommendation.sessions_per_week }} sesi/minggu
                                </button>
                            </div>
                            <p v-else class="mt-1 text-[11px] text-clinic-700">Assessment ini belum memiliki rekomendasi terapi.</p>
                        </section>

                        <section v-if="form.patient_id" class="rounded-md border border-slate-200 bg-slate-50 p-3" aria-live="polite">
                            <h3 class="text-xs font-bold text-slate-800">Paket pasien</h3>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="rounded border border-slate-200 bg-white px-2.5 py-2">
                                    <p class="text-[10px] font-medium text-slate-500">Total sesi sejak evaluasi terakhir</p>
                                    <p class="mt-0.5 text-sm font-bold text-slate-800">{{ selectedPatientEvaluationSessions }} / 25 sesi</p>
                                    <p class="mt-0.5 text-[10px] text-slate-500">{{ patientEvaluationIsDue ? 'Batas evaluasi tercapai' : `${25 - selectedPatientEvaluationSessions} sesi menuju evaluasi` }}</p>
                                </div>
                                <div class="rounded border border-slate-200 bg-white px-2.5 py-2">
                                    <p class="text-[10px] font-medium text-slate-500">Sisa kuota paket berlaku</p>
                                    <p class="mt-0.5 text-sm font-bold text-slate-800">{{ currentPackageRemainingSessions }} sesi</p>
                                    <p class="mt-0.5 text-[10px] text-slate-500">Dari {{ currentPackageGrantedSessions }} sesi total paket</p>
                                </div>
                            </div>
                            <div v-if="patientEvaluationIsDue" role="alert" class="mt-2 flex flex-col gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs leading-relaxed text-amber-900"><span class="font-bold">Evaluasi wajib dilakukan.</span> Pasien sudah mencapai 25 sesi sejak evaluasi terakhir.</p>
                                <Link :href="`/assessments/create?patient_id=${form.patient_id}`" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-md border border-amber-800 px-3 text-xs font-semibold text-amber-950 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-800">Jadwalkan evaluasi</Link>
                            </div>
                            <div v-if="selectedPatientPackages.length" class="mt-2 space-y-2">
                                <article v-for="item in selectedPatientPackages" :key="item.id" class="rounded bg-white p-2.5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs font-semibold text-slate-800">{{ item.name }}</p>
                                        <span class="rounded-full px-2 py-1 text-[10px] font-semibold" :class="packageStatus(item) === 'Aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ packageStatus(item) }}</span>
                                    </div>
                                    <div class="mt-2 rounded border border-slate-100 bg-slate-50 px-2 py-2">
                                        <p class="text-[10px] font-medium text-slate-500">Sisa paket ini</p>
                                        <p class="mt-0.5 text-xs font-bold text-slate-800">{{ packageRemainingSessions(item) }} dari {{ packageGrantedSessions(item) }} sesi</p>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-600">Berlaku sampai {{ expiryDate(item.expires_at) }}</p>
                                    <p v-if="Number(item.duration_granted_sessions) > 0" class="mt-1 text-[11px] text-clinic-700">Sudah terjadwal {{ item.duration_booked_sessions }} sesi · masih dapat dijadwalkan {{ item.duration_available_sessions }} sesi</p>
                                    <p v-if="item.session_duration_minutes" class="mt-1 text-[11px] text-clinic-700">Durasi paket {{ Number(item.session_duration_minutes) === 60 ? '1 jam' : `${item.session_duration_minutes} menit` }} per sesi</p>
                                    <ul v-if="item.legacy_entitlements?.length" class="mt-1 space-y-0.5 text-[11px] text-slate-600">
                                        <li v-for="entitlement in item.legacy_entitlements" :key="entitlement.therapy_name">{{ entitlement.therapy_name }}: terpakai {{ Number(entitlement.granted_sessions) - Number(entitlement.remaining_sessions) }} / {{ entitlement.granted_sessions }} sesi · sisa {{ entitlement.remaining_sessions }} · terjadwal {{ entitlement.booked_sessions }}</li>
                                    </ul>
                                </article>
                            </div>
                            <p v-else class="mt-1 text-[11px] leading-5 text-slate-600">Belum ada paket yang tercatat.</p>
                        </section>

                        <div v-if="packageAlertMessage" role="alert" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800">
                            <span class="font-bold">Perhatian pembayaran.</span> {{ packageAlertMessage }}
                        </div>

                        <label class="field-label">
                            Jenis terapi *
                            <select v-model="form.therapy_type_id" class="field-input" required>
                                <option value="">Pilih terapi</option>
                                <option v-for="type in therapyTypes" :key="type.id" :value="type.id">{{ type.code }} · {{ type.name }}</option>
                            </select>
                            <span v-if="form.errors.therapy_type_id" class="field-error">{{ form.errors.therapy_type_id }}</span>
                        </label>

                        <div class="rounded-md border border-clinic-100 bg-clinic-50 px-3 py-2.5">
                            <p class="text-[11px] font-semibold text-clinic-800">Durasi sesi otomatis</p>
                            <p class="mt-1 text-sm font-bold text-clinic-900">{{ effectiveDuration === 60 ? '1 jam' : `${effectiveDuration} menit` }}</p>
                            <p class="mt-1 text-[11px] text-clinic-700">{{ durationDescription }}</p>
                        </div>

                        <label class="field-label">
                            Terapis *
                            <select v-model="form.therapist_profile_id" class="field-input" required>
                                <option value="">Pilih terapis</option>
                                <option v-for="therapist in scheduleTherapists" :key="therapist.id" :value="therapist.id" :disabled="isTherapistBusy(therapist.id)">{{ therapist.name }} · {{ therapist.specialty || 'Terapis' }}{{ isTherapistBusy(therapist.id) ? ' (sudah terjadwal)' : '' }}</option>
                            </select>
                            <span v-if="form.errors.therapist_profile_id" class="field-error">{{ form.errors.therapist_profile_id }}</span>
                            <span v-if="form.patient_id && ['TO', 'TW'].includes(selectedType?.code) && !selectedPatientTherapists.length" class="mt-1 block text-xs font-normal text-amber-800">Belum ada terapis yang direkomendasikan untuk jenis terapi ini. Atur pada data pasien terlebih dahulu.</span>
                        </label>

                        <label class="field-label">
                            Ruangan *
                            <select v-model="form.room_id" class="field-input" required>
                                <option value="">Pilih ruangan</option>
                                <option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.name }}</option>
                            </select>
                            <span v-if="form.errors.room_id" class="field-error">{{ form.errors.room_id }}</span>
                        </label>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="field-label">
                                Tanggal *
                                <input v-model="scheduleDate" type="date" class="field-input" required>
                            </label>
                            <label class="field-label">
                                Jam mulai (WIB, 24 jam) *
                                <input v-model="scheduleTime" type="text" inputmode="numeric" autocomplete="off" maxlength="5" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" placeholder="14:05" title="Gunakan format 24 jam, contoh 14:05" class="field-input" required @input="updateScheduleTime">
                                <span class="mt-1 block text-[10px] font-normal text-slate-500">Format HH:MM, menit bisa diisi bebas.</span>
                            </label>
                        </div>

                        <label class="field-label">
                            Perkiraan waktu selesai
                            <input :value="endTime" type="text" class="field-input" readonly>
                        </label>

                        <div v-if="selectedTherapistHasConflict" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs leading-relaxed text-rose-700">Terapis ini sudah memiliki jadwal pada waktu tersebut. Pilih waktu atau terapis lain.</div>
                        <div v-if="form.errors.starts_at" role="alert" class="rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ form.errors.starts_at }}</div>
                        <div v-if="cancelForm.errors.starts_at" role="alert" class="rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ cancelForm.errors.starts_at }}</div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button :disabled="form.processing || patientEvaluationIsDue || selectedTherapistHasConflict" class="h-10 flex-1 rounded-md bg-clinic-700 px-3 text-xs font-bold text-white hover:bg-clinic-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700 disabled:cursor-not-allowed disabled:opacity-60">
                                {{ form.processing ? 'Menyimpan...' : editingAppointmentId ? 'Simpan perubahan' : 'Simpan jadwal' }}
                            </button>
                            <button v-if="editingAppointmentId" type="button" class="h-10 rounded-md border border-slate-300 px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="resetForm">Batal edit</button>
                        </div>
                    </div>
                </form>

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-coral-100 bg-coral-50/40 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-coral-700 shadow-sm ring-1 ring-coral-100">
                                <Sparkles :size="18" />
                            </span>
                            <div>
                                <h2 class="text-sm font-bold text-clinic-900">Agenda mendatang</h2>
                                <p class="mt-1 text-[11px] text-slate-600">{{ agendaView === 'calendar' ? `${calendarWeekEntries.length} agenda minggu ini` : `${agendaEntries.length} agenda berikutnya` }}</p>
                            </div>
                        </div>
                        <div role="group" aria-label="Tampilan agenda" class="grid min-h-11 grid-cols-2 rounded-xl border border-coral-100 bg-white/80 p-1 sm:w-fit">
                            <button type="button" :aria-pressed="agendaView === 'calendar'" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-3 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" :class="agendaView === 'calendar' ? 'bg-clinic-700 text-white shadow-sm' : 'text-slate-600 hover:bg-clinic-50 hover:text-clinic-900'" @click="agendaView = 'calendar'">
                                <CalendarDays :size="15" /> Kalender
                            </button>
                            <button type="button" :aria-pressed="agendaView === 'list'" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-3 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" :class="agendaView === 'list' ? 'bg-clinic-700 text-white shadow-sm' : 'text-slate-600 hover:bg-clinic-50 hover:text-clinic-900'" @click="agendaView = 'list'">
                                <Clock3 :size="15" /> Daftar
                            </button>
                        </div>
                    </div>

                    <div v-if="cancelForm.errors.cancel" role="alert" class="mx-5 mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                        {{ cancelForm.errors.cancel }}
                    </div>
                    <div v-if="whatsappNotice" role="alert" class="mx-5 mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                        {{ whatsappNotice }}
                    </div>

                    <div v-if="agendaView === 'calendar'" class="border-b border-coral-100 bg-coral-50/20">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-coral-100 bg-white px-3 py-3 sm:px-4">
                            <button type="button" aria-label="Minggu sebelumnya" class="grid min-h-11 min-w-11 place-items-center rounded-full border border-clinic-100 bg-clinic-50 text-clinic-700 transition-colors hover:bg-clinic-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="changeCalendarWeek(-1)">
                                <ChevronLeft :size="18" />
                            </button>
                            <div class="min-w-0 flex-1 text-center">
                                <p class="inline-flex max-w-full items-center rounded-full bg-clinic-50 px-3 py-1.5 text-xs font-bold text-clinic-900 sm:text-sm">{{ calendarWeekLabel }}</p>
                                <div><button type="button" class="mt-1 min-h-11 rounded-full px-3 text-[11px] font-bold text-coral-700 transition-colors hover:bg-coral-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="showCurrentWeek">Minggu ini</button></div>
                            </div>
                            <button type="button" aria-label="Minggu berikutnya" class="grid min-h-11 min-w-11 place-items-center rounded-full border border-clinic-100 bg-clinic-50 text-clinic-700 transition-colors hover:bg-clinic-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="changeCalendarWeek(1)">
                                <ChevronRight :size="18" />
                            </button>
                        </div>

                        <template v-if="calendarWeekEntries.length">
                            <div class="hidden gap-2 p-3 xl:grid xl:grid-cols-7">
                                <section v-for="day in calendarWeek" :key="day.date" class="min-w-0 overflow-hidden rounded-xl border" :class="day.isToday ? 'border-coral-200 bg-coral-50/40 shadow-sm' : 'border-clinic-100 bg-white'">
                                    <header class="min-h-[84px] px-1.5 py-3 text-center" :class="day.isToday ? 'bg-coral-50' : 'bg-clinic-50/60'">
                                        <p class="text-[10px] font-bold uppercase tracking-wide" :class="day.isToday ? 'text-coral-700' : 'text-clinic-700'">{{ day.weekday }}</p>
                                        <span class="mx-auto mt-1.5 grid h-8 w-8 place-items-center rounded-full text-xs font-bold" :class="day.isToday ? 'bg-coral-500 text-white shadow-sm' : 'bg-white text-clinic-800 ring-1 ring-clinic-100'">{{ day.dayNumber }}</span>
                                        <p class="mt-1 text-[9px] font-medium text-slate-500">{{ day.monthLabel }}</p>
                                    </header>
                                    <div class="min-h-[180px] space-y-2 p-1.5">
                                        <template v-for="entry in day.entries" :key="`${entry.agendaType}-${entry.id}`">
                                            <button v-if="entry.agendaType === 'assessment'" type="button" class="block min-h-16 w-full rounded-lg border border-clinic-100 border-l-[3px] border-l-clinic-600 bg-clinic-50/70 px-1.5 py-2 text-left transition-colors hover:bg-clinic-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-clinic-700" :aria-label="`Ubah jadwal assessment ${entry.patient.name}, pukul ${appointmentTimeOfDay(entry.starts_at)}`" @click="editAssessmentSchedule(entry)">
                                                <span class="inline-flex rounded-full bg-white px-1.5 py-0.5 text-[10px] font-bold text-clinic-700">{{ appointmentTimeOfDay(entry.starts_at) }}</span>
                                                <span class="mt-1 block truncate text-[10px] font-semibold leading-tight text-slate-800">{{ entry.patient.name }}</span>
                                                <span class="mt-1 block truncate text-[9px] leading-tight text-clinic-700">{{ entry.status === 'awaiting_payment' ? 'Assessment · Belum dibayar' : 'Assessment' }} · {{ entry.therapist?.user?.name || 'Terapis belum ditentukan' }}</span>
                                            </button>
                                            <button v-else-if="['scheduled', 'confirmed'].includes(entry.status)" type="button" class="block min-h-16 w-full rounded-lg border border-coral-100 border-l-[3px] border-l-coral-500 bg-white px-1.5 py-2 text-left shadow-sm transition-colors hover:bg-coral-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-clinic-700" :aria-label="`Ubah jadwal ${entry.patient.name}, pukul ${appointmentTimeOfDay(entry.starts_at)}`" @click="editAppointment(entry)">
                                                <span class="inline-flex rounded-full bg-coral-50 px-1.5 py-0.5 text-[10px] font-bold text-coral-700">{{ appointmentTimeOfDay(entry.starts_at) }}</span>
                                                <span class="mt-1 block truncate text-[10px] font-semibold leading-tight text-slate-800">{{ entry.patient.name }}</span>
                                                <span class="mt-1 block truncate text-[9px] leading-tight text-slate-500">{{ entry.therapy_type.name }}</span>
                                                <span v-if="entry.payment_warning" class="mt-1 block truncate text-[9px] font-bold text-amber-700">Belum dibayar</span>
                                                <span v-if="entry.assessment_id" class="mt-1 block truncate text-[9px] font-semibold text-clinic-700">Assessment tertaut</span>
                                            </button>
                                            <article v-else class="min-h-16 rounded-lg border border-slate-200 bg-slate-50 px-1.5 py-2">
                                                <span class="block text-[10px] font-bold text-slate-500">{{ appointmentTimeOfDay(entry.starts_at) }}</span>
                                                <span class="mt-1 block truncate text-[10px] font-semibold leading-tight text-slate-700">{{ entry.patient.name }}</span>
                                                <span class="mt-1 block truncate text-[9px] leading-tight capitalize text-slate-500">{{ entry.status.replace('_', ' ') }}</span>
                                            </article>
                                        </template>
                                    </div>
                                </section>
                            </div>

                            <div class="space-y-2 p-3 xl:hidden">
                                <section v-for="day in calendarWeek" :key="day.date" class="rounded-xl border p-3 shadow-sm" :class="day.isToday ? 'border-coral-200 bg-coral-50/40' : 'border-clinic-100 bg-white'">
                                    <header class="mb-3 flex items-center justify-between gap-3">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-bold" :class="day.isToday ? 'bg-coral-500 text-white shadow-sm' : 'bg-clinic-50 text-clinic-800 ring-1 ring-clinic-100'">{{ day.dayNumber }}</span>
                                            <div class="min-w-0">
                                                <h3 class="text-xs font-bold" :class="day.isToday ? 'text-coral-700' : 'text-clinic-900'">{{ day.weekday }} <span class="font-medium text-slate-500">{{ day.monthLabel }}</span></h3>
                                                <span v-if="day.isToday" class="mt-1 inline-flex rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-coral-700 ring-1 ring-coral-100">Hari ini</span>
                                            </div>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold text-clinic-700 ring-1 ring-clinic-100">{{ day.entries.length }} agenda</span>
                                    </header>
                                    <div v-if="day.entries.length" class="space-y-2">
                                        <article v-for="entry in day.entries" :key="`${entry.agendaType}-${entry.id}`" class="rounded-xl border bg-white p-3 shadow-sm" :class="entry.agendaType === 'assessment' ? 'border-clinic-100' : 'border-coral-100'">
                                            <p class="inline-flex rounded-full bg-clinic-50 px-2.5 py-1 text-xs font-bold text-clinic-700">
                                                {{ appointmentTimeOfDay(entry.starts_at) }}<span v-if="entry.agendaType === 'therapy'">–{{ appointmentTimeOfDay(entry.ends_at) }}</span>
                                            </p>
                                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ entry.patient.name }} <span class="font-mono text-[10px] font-normal text-slate-400">{{ entry.patient.patient_code }}</span></p>
                                            <p v-if="entry.agendaType === 'assessment'" class="mt-1 text-[11px] leading-relaxed text-clinic-700">{{ entry.status === 'awaiting_payment' ? 'Assessment · Belum dibayar' : 'Assessment' }} · {{ entry.therapist?.user?.name || 'Terapis belum ditentukan' }}</p>
                                            <p v-else class="mt-1 text-[11px] leading-relaxed text-slate-500">{{ entry.therapy_type.name }} · {{ entry.therapist?.user?.name || 'Belum ditentukan' }} · {{ entry.room?.name || 'Belum ditentukan' }}</p>
                                            <p v-if="entry.agendaType === 'therapy' && entry.payment_warning" role="status" class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-[11px] font-semibold text-amber-800">Belum ada pembayaran atau kuota paket untuk sesi ini.</p>
                                            <p v-if="entry.agendaType === 'therapy' && entry.assessment_id" class="mt-1 text-[10px] font-semibold text-clinic-700">Assessment tertaut</p>
                                            <div v-if="entry.agendaType === 'therapy' && ['scheduled', 'confirmed'].includes(entry.status)" class="mt-3 flex flex-wrap gap-2">
                                                <button type="button" class="min-h-11 flex-1 rounded-full border border-clinic-100 bg-clinic-50 px-3 text-xs font-semibold text-clinic-800 transition-colors hover:bg-clinic-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="editAppointment(entry)">Ubah jadwal</button>
                                                <button type="button" :disabled="cancelForm.processing" class="min-h-11 flex-1 rounded-full border border-coral-100 bg-coral-50 px-3 text-xs font-semibold text-coral-700 transition-colors hover:bg-coral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 disabled:opacity-60" @click="cancelAppointment(entry)">{{ cancelForm.processing ? 'Memproses...' : 'Batalkan' }}</button>
                                                <button type="button" class="min-h-11 flex-1 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-800 transition-colors hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" @click="sendWhatsAppReminder(entry)">Kirim WhatsApp</button>
                                            </div>
                                            <span v-else-if="entry.agendaType === 'therapy'" class="mt-3 inline-flex rounded bg-slate-100 px-2 py-1 text-[10px] font-semibold capitalize text-slate-600">{{ entry.status.replace('_', ' ') }}</span>
                                            <button v-else-if="entry.agendaType === 'assessment'" type="button" class="mt-3 min-h-11 w-full rounded-full border border-clinic-100 bg-clinic-50 px-3 text-xs font-semibold text-clinic-800 transition-colors hover:bg-clinic-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="editAssessmentSchedule(entry)">Ubah jadwal assessment</button>
                                        </article>
                                    </div>
                                    <p v-else class="rounded-lg border border-dashed border-clinic-100 bg-white/80 px-3 py-3 text-center text-[11px] text-slate-500"><Sparkles :size="13" class="mr-1 inline text-coral-500" />Belum ada agenda</p>
                                </section>
                            </div>
                        </template>

                        <div v-else class="grid min-h-[240px] place-items-center px-5 py-8 text-center">
                            <div>
                                <UserRound :size="24" class="mx-auto text-slate-300" />
                                <p class="mt-3 text-sm font-semibold text-slate-700">{{ agendaEntries.length ? 'Tidak ada jadwal pada minggu ini' : 'Belum ada agenda' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ agendaEntries.length ? 'Gunakan panah untuk melihat minggu lain atau buka daftar semua agenda.' : 'Buat jadwal pertama dari formulir.' }}</p>
                                <button v-if="agendaEntries.length" type="button" class="mt-3 min-h-11 rounded-md border border-slate-300 px-4 text-xs font-semibold text-clinic-700 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="agendaView = 'list'">Lihat semua agenda</button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="agendaEntries.length" class="divide-y divide-slate-100">
                        <article v-for="entry in agendaEntries" :key="`${entry.agendaType}-${entry.id}`" class="grid gap-3 px-5 py-4 sm:grid-cols-[140px_minmax(0,1fr)_auto] sm:items-center">
                            <div class="text-xs font-bold text-clinic-700">{{ dateTime(entry.starts_at) }}</div>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-800">{{ entry.patient.name }} <span class="font-mono text-[10px] font-normal text-slate-400">{{ entry.patient.patient_code }}</span></div>
                                <div v-if="entry.agendaType === 'assessment'" class="mt-1 text-[11px] font-semibold text-clinic-700">Assessment · {{ entry.therapist?.user?.name || 'Terapis belum ditentukan' }}</div>
                                <div v-else class="mt-1 text-[11px] text-slate-500">{{ entry.therapy_type.name }} · {{ sessionDuration(entry) }} · {{ entry.therapist?.user?.name || 'Belum ditentukan' }} · {{ entry.room?.name || 'Belum ditentukan' }}</div>
                                <div v-if="entry.agendaType === 'therapy' && entry.payment_warning" class="mt-1 text-[11px] font-semibold text-amber-700">Belum ada pembayaran atau kuota paket untuk sesi ini.</div>
                                <div v-if="entry.agendaType === 'therapy' && entry.assessment_id" class="mt-1 text-[10px] font-semibold text-clinic-700">Assessment tertaut</div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <span class="rounded px-2 py-1 text-[10px] font-semibold capitalize" :class="entry.agendaType === 'assessment' ? 'bg-clinic-50 text-clinic-700' : 'bg-slate-100 text-slate-600'">{{ entry.agendaType === 'assessment' ? entry.status === 'awaiting_payment' ? 'Assessment · Belum dibayar' : 'Assessment terjadwal' : entry.status.replace('_', ' ') }}</span>
                                <span v-if="entry.agendaType === 'therapy' && entry.payment_warning" class="rounded bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-800">Belum dibayar</span>
                                <button v-if="entry.agendaType === 'assessment'" type="button" class="min-h-9 rounded-md border border-clinic-200 px-3 text-xs font-semibold text-clinic-800 hover:bg-clinic-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="editAssessmentSchedule(entry)">Ubah jadwal</button>
                                <button v-if="entry.agendaType === 'therapy' && ['scheduled', 'confirmed'].includes(entry.status)" type="button" class="min-h-9 rounded-md border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-clinic-700" @click="editAppointment(entry)">Ubah</button>
                                <button v-if="entry.agendaType === 'therapy' && ['scheduled', 'confirmed'].includes(entry.status)" type="button" :disabled="cancelForm.processing" class="min-h-9 rounded-md border border-rose-200 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 disabled:opacity-60" @click="cancelAppointment(entry)">{{ cancelForm.processing ? 'Memproses...' : 'Batalkan' }}</button>
                                <button v-if="entry.agendaType === 'therapy' && ['scheduled', 'confirmed'].includes(entry.status)" type="button" class="min-h-9 rounded-md border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" @click="sendWhatsAppReminder(entry)">Kirim WhatsApp</button>
                            </div>
                        </article>
                    </div>

                    <div v-else class="grid min-h-[240px] place-items-center text-center">
                        <div>
                            <UserRound :size="24" class="mx-auto text-slate-300" />
                            <p class="mt-3 text-sm font-semibold text-slate-700">Belum ada agenda</p>
                            <p class="mt-1 text-xs text-slate-500">Buat jadwal pertama dari formulir.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.field-label { display: block; font-size: 11px; font-weight: 700; color: #475569; }
.field-input { display: block; width: 100%; min-height: 40px; margin-top: 7px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; padding: 8px 10px; font-size: 12px; font-weight: 400; }
.field-input:focus-visible { outline: 2px solid #405f91; outline-offset: 2px; }
.field-error { display: block; margin-top: 5px; color: #b91c1c; font-size: 11px; font-weight: 500; }
</style>
