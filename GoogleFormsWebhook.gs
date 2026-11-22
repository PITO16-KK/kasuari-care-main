/**
 * Bridge a form-bound Google Apps Script project to Kasuari Care.
 * Run configureGoogleFormsWebhook() once after adding this file in the form's
 * Apps Script editor. The URL and signing secret are kept in Script Properties.
 */

const KASUARI_WEBHOOK_PATH = '/api/integrations/google-form/intakes';
const KASUARI_HANDLER_NAME = 'sendIntakeToKasuari';

function configureGoogleFormsWebhook() {
  const ui = FormApp.getUi();
  const urlResult = ui.prompt(
    'Sambungkan ke Kasuari Care',
    'Masukkan URL HTTPS publik aplikasi Kasuari Care (contoh: https://klinik.example.com).',
    ui.ButtonSet.OK_CANCEL,
  );

  if (urlResult.getSelectedButton() !== ui.Button.OK) {
    return;
  }

  const baseUrl = urlResult.getResponseText().trim().replace(/\/+$/, '');

  if (!/^https:\/\//i.test(baseUrl) || /localhost|127\.0\.0\.1/i.test(baseUrl)) {
    ui.alert('Gunakan URL HTTPS publik. Google Form tidak dapat mengirim data ke localhost.');
    return;
  }

  const secretResult = ui.prompt(
    'Rahasia webhook',
    'Masukkan rahasia yang sama dengan GOOGLE_FORMS_WEBHOOK_SECRET pada server. Minimal 32 karakter.',
    ui.ButtonSet.OK_CANCEL,
  );

  if (secretResult.getSelectedButton() !== ui.Button.OK) {
    return;
  }

  const secret = secretResult.getResponseText().trim();

  if (secret.length < 32) {
    ui.alert('Rahasia webhook harus berisi minimal 32 karakter.');
    return;
  }

  const endpoint = baseUrl.endsWith(KASUARI_WEBHOOK_PATH)
    ? baseUrl
    : baseUrl + KASUARI_WEBHOOK_PATH;

  PropertiesService.getScriptProperties().setProperties({
    KASUARI_WEBHOOK_URL: endpoint,
    KASUARI_WEBHOOK_SECRET: secret,
  });

  ScriptApp.getProjectTriggers()
    .filter((trigger) => trigger.getHandlerFunction() === KASUARI_HANDLER_NAME)
    .forEach((trigger) => ScriptApp.deleteTrigger(trigger));

  ScriptApp.newTrigger(KASUARI_HANDLER_NAME)
    .forForm(FormApp.getActiveForm())
    .onFormSubmit()
    .create();

  ui.alert('Sambungan aktif. Respons baru akan dikirim ke antrean Form Rekam Medis.');
}

function sendIntakeToKasuari(event) {
  if (!event || !event.response) {
    throw new Error('Pemicu harus dijalankan saat ada respons Google Form baru.');
  }

  const properties = PropertiesService.getScriptProperties();
  const webhookUrl = properties.getProperty('KASUARI_WEBHOOK_URL');
  const secret = properties.getProperty('KASUARI_WEBHOOK_SECRET');

  if (!webhookUrl || !secret) {
    throw new Error('Jalankan configureGoogleFormsWebhook() untuk mengatur URL dan rahasia webhook.');
  }

  const response = event.response;
  const payload = {
    source: 'google_forms',
    response_id: response.getId(),
    submitted_at: response.getTimestamp().toISOString(),
  };

  response.getItemResponses().forEach((itemResponse) => {
    const answer = itemResponse.getResponse();

    if (answer === null || answer === '') {
      return;
    }

    const title = itemResponse.getItem().getTitle();
    const destinations = mapQuestionTitle(title);

    if (destinations.length === 0) {
      payload.other_answers = payload.other_answers || [];
      payload.other_answers.push({ question: title, answer: formatAnswer(answer) });
      return;
    }

    const answerText = formatAnswer(answer);

    destinations.forEach((destination) => {
      const value = destination.key === 'contact_details'
        ? title + ': ' + answerText
        : destination.key === 'birth_date'
          ? formatBirthDate(answerText)
          : answerText;

      addAnswer(payload, destination.group, destination.key, value);
    });
  });

  const body = JSON.stringify(payload);
  const timestamp = String(Math.floor(Date.now() / 1000));
  const signature = toHex(Utilities.computeHmacSha256Signature(
    timestamp + '.' + body,
    secret,
    Utilities.Charset.UTF_8,
  ));

  const result = UrlFetchApp.fetch(webhookUrl, {
    method: 'post',
    contentType: 'application/json',
    payload: body,
    headers: {
      'X-Kasuari-Timestamp': timestamp,
      'X-Kasuari-Signature': signature,
    },
    muteHttpExceptions: true,
  });

  const statusCode = result.getResponseCode();

  if (statusCode < 200 || statusCode >= 300) {
    throw new Error('Webhook Kasuari Care menolak respons (' + statusCode + '): ' + result.getContentText());
  }
}

function mapQuestionTitle(title) {
  const question = normalizeText(title);
  const parent = question.includes('ayah') || question.includes('bapak')
    ? 'father_data'
    : question.includes('ibu') || question.includes('bunda')
      ? 'mother_data'
      : null;

  if (parent && includesAny(question, ['pekerjaan', 'profesi', 'bekerja sebagai'])) {
    return [{ group: parent, key: 'occupation' }];
  }

  if (parent && includesAny(question, ['nama', 'umur', 'usia', 'pendidikan', 'whatsapp', 'nomor wa', 'no wa', 'nomor hp', 'no hp', 'nomor telepon', 'kontak'])) {
    return [{ group: parent, key: 'contact_details' }];
  }

  const mappings = [
    ['child_data', 'child_name', ['nama lengkap anak', 'nama anak', 'nama pasien']],
    ['child_data', 'birth_date', ['tanggal lahir', 'tgl lahir', 'tanggal/bulan/tahun lahir']],
    ['child_data', 'current_address', ['alamat', 'tempat tinggal', 'domisili']],
    ['family_context', 'siblings', ['jumlah saudara', 'saudara kandung', 'kakak atau adik', 'kakak/adik']],
    ['family_context', 'lives_with_parents', ['tinggal dengan orang tua', 'tinggal bersama orang tua', 'tinggal dengan ayah', 'tinggal dengan ibu']],
    ['family_context', 'primary_companions', ['aktivitas sehari-hari', 'pendamping sehari-hari', 'teman sehari-hari', 'siapa yang mendampingi']],
    ['family_context', 'family_history', ['riwayat keluarga', 'riwayat penyakit keluarga', 'keluarga yang memiliki']],
    ['family_context', 'referral_source', ['sumber informasi', 'sumber rujukan', 'mengetahui layanan', 'tahu layanan']],
    ['health_history', 'previous_exam_provider', ['tempat pemeriksaan', 'nama pemeriksa', 'diperiksa oleh', 'rumah sakit atau klinik']],
    ['health_history', 'doctor_or_psychologist_diagnosis', ['diagnosis', 'hasil pemeriksaan dokter', 'hasil pemeriksaan psikolog']],
    ['health_history', 'previous_exam', ['pernah diperiksa', 'pemeriksaan dokter', 'pemeriksaan psikolog', 'pernah konsultasi']],
    ['health_history', 'medical_conditions', ['riwayat penyakit', 'kondisi medis', 'penyakit yang pernah', 'masalah kesehatan']],
    ['development_history', 'daily_language', ['bahasa sehari-hari', 'bahasa yang digunakan']],
    ['development_history', 'independence_skills', ['kemandirian', 'keterampilan mandiri', 'aktivitas mandiri']],
    ['development_history', 'communicates_needs', ['menyampaikan keinginan', 'mengungkapkan keinginan', 'meminta sesuatu']],
    ['development_history', 'parent_perceived_causes', ['dugaan orang tua', 'penyebab masalah', 'menurut orang tua penyebab']],
    ['development_history', 'concerns_first_noticed', ['pertama kali disadari', 'pertama kali terlihat', 'mulai menyadari', 'kapan masalah muncul']],
    ['development_history', 'previous_therapies', ['terapi sebelumnya', 'riwayat terapi', 'pernah mengikuti terapi']],
    ['development_history', 'communication_and_storytelling', ['komunikasi dan bercerita', 'bercerita', 'menceritakan kembali']],
    ['development_history', 'speech_clarity', ['kejelasan bicara', 'artikulasi', 'pelafalan']],
    ['development_history', 'attention_duration', ['lama fokus', 'rentang perhatian', 'durasi fokus', 'konsentrasi']],
    ['development_history', 'reading_and_writing', ['membaca dan menulis', 'kemampuan membaca', 'kemampuan menulis']],
    ['development_history', 'understands_simple_instructions', ['perintah sederhana', 'instruksi sederhana', 'memahami perintah']],
    ['development_history', 'tantrums_and_triggers', ['tantrum', 'pemicu emosi', 'ledakan emosi', 'mudah marah']],
    ['development_history', 'walking', ['riwayat berjalan', 'mulai berjalan', 'usia berjalan']],
    ['development_history', 'crawling', ['riwayat merangkak', 'mulai merangkak', 'usia merangkak']],
    ['development_history', 'feeding', ['masalah makan', 'pola makan', 'kesulitan makan', 'memilih makanan']],
  ];

  for (const [group, key, aliases] of mappings) {
    if (includesAny(question, aliases)) {
      return [{ group: group, key: key }];
    }
  }

  return [];
}

function addAnswer(payload, group, key, value) {
  payload[group] = payload[group] || {};

  if (Object.prototype.hasOwnProperty.call(payload[group], key)) {
    const existing = payload[group][key];
    payload[group][key] = Array.isArray(existing) ? existing.concat(value) : [existing, value];
    return;
  }

  payload[group][key] = value;
}

function formatAnswer(answer) {
  if (answer instanceof Date) {
    return answer.toISOString().slice(0, 10);
  }

  return Array.isArray(answer) ? answer.join(', ') : String(answer);
}

function formatBirthDate(value) {
  const isoDate = value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);

  if (isoDate) {
    return isoDate[1] + '-' + isoDate[2].padStart(2, '0') + '-' + isoDate[3].padStart(2, '0');
  }

  const localDate = value.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);

  if (localDate) {
    return localDate[3] + '-' + localDate[2].padStart(2, '0') + '-' + localDate[1].padStart(2, '0');
  }

  return value;
}

function normalizeText(value) {
  return String(value)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

function includesAny(value, phrases) {
  return phrases.some((phrase) => value.includes(normalizeText(phrase)));
}

function toHex(bytes) {
  return bytes.map((byte) => ((byte + 256) % 256).toString(16).padStart(2, '0')).join('');
}
