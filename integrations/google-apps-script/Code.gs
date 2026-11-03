const QUESTION_MAPPING = {
    'Nama anak': ['child_data', 'child_name'],
    'Saudara (Kaka/Adik), nama dan usia (1, ajeng, 2 tahun)': ['family_context', 'siblings'],
    'Tanggal lahir': ['child_data', 'birth_date'],
    'Alamat/tempat tinggal saat ini': ['child_data', 'current_address'],
    'Apakah anak tinggal dengan orang tuanya?': ['family_context', 'lives_with_parents'],
    'Nama ibu, umur, pendidikan terakhir dan no Wa': ['mother_data', 'contact_details'],
    'Nama ayah, umur, pendidikan terakhir dan no Wa': ['father_data', 'contact_details'],
    'Apakah anak pernah diperiksa oleh dokter atau psikolog?': ['health_history', 'previous_exam'],
    'Dimana dan siapa Dokter atau Psikolog anak yang memeriksa?': ['health_history', 'previous_exam_provider'],
    'Bahasa yang digunakan sehari hari?': ['development_history', 'daily_language'],
    'Dengan siapa anak menghabiskan waktunya?': ['family_context', 'primary_companions'],
    'Jelaskan kemampuan kemandirian anak (lepas-pasang baju/celana, kancing baju, sendal/sepatu, toilet training)': ['development_history', 'independence_skills'],
    'Bagaimana cara anak menyampaikan keinginannya?': ['development_history', 'communicates_needs'],
    'Menurut orang tua apa penyebab masalah perilaku, makan, fokus, bahasa bicara anak?': ['development_history', 'parent_perceived_causes'],
    'Kapan pertama orang tua sadar anak memiliki masalah-masalah tersebut?': ['development_history', 'concerns_first_noticed'],
    'Pernahkah anak diperiksa terapis wicara atau terapis okupasi atau terapi perilaku? siapa dan kapan?': ['development_history', 'previous_therapies'],
    'Adakah anggota keluarga yang memiliki masalah-masalah tersebut juga?': ['family_context', 'family_history'],
    'Dari mana anda mendapat informasi pemeriksaan dan konsultasi terapi wicara atau terapi okupasi atau terapi perilaku ?': ['family_context', 'referral_source'],
    'Jelaskan Masalah Makan pada anak (mengunyah, menelan, GTM, pilih-pilih makanan)': ['development_history', 'feeding'],
    'Jelaskan masalah komunikasi atau kemampuan bercerita pada anak': ['development_history', 'communication_and_storytelling'],
    'Jelaskan tingkat kejelasan Bicara pada anak': ['development_history', 'speech_clarity'],
    'Jelaskan seberapa lama anak bisa fokus': ['development_history', 'attention_duration'],
    'Jelaskan masalah membaca dan menulis pada anak': ['development_history', 'reading_and_writing'],
    'Apakah anak sudah paham perintah sederhana? Sebutkan intruksi apa saja yang sudah anak pahami': ['development_history', 'understands_simple_instructions'],
    'Apakah anak masih sering tantrum atau marah-marah atau menangis? Menurut orang tua penyebabnya apa? Jelaskan': ['development_history', 'tantrums_and_triggers'],
    'Pekerjaan Ayah': ['father_data', 'occupation'],
    'Pekerjaan Ibu': ['mother_data', 'occupation'],
    'Diagnosis Dokter dan Psikolog (isi jikalau sudah ke Dokter atau Psikolog)': ['health_history', 'doctor_or_psychologist_diagnosis'],
    'Riwayat Penyakit (Epilepsi/Kejang, BBLR/Prematur, Cidera)': ['health_history', 'medical_conditions'],
    'Apakah anak sudah bisa Jalan? Usia berapa anak bisa jalan? Apakah ada masalah di kemampuan Jalan anak?': ['development_history', 'walking'],
    'Apakah anak sudah bisa atau melewati Fase merangkak? Usia berapa anak bisa merangkak? Apakah ada masalah di kemampuan merangkak?': ['development_history', 'crawling'],
};

function installFormSubmitTrigger() {
    const form = FormApp.openById(requiredProperty_('FORM_ID'));
    validateQuestionTitles_(form.getItems().map((item) => item.getTitle()));

    ScriptApp.getProjectTriggers()
        .filter((trigger) => trigger.getHandlerFunction() === 'onFormSubmit')
        .forEach((trigger) => ScriptApp.deleteTrigger(trigger));

    ScriptApp.newTrigger('onFormSubmit').forForm(form).onFormSubmit().create();
}

function onFormSubmit(event) {
    if (!event || !event.response) {
        throw new Error('Install this handler as a Google Forms submit trigger.');
    }

    validateQuestionTitles_(event.source.getItems().map((item) => item.getTitle()));

    const responseId = event.response.getId();
    if (!responseId) {
        throw new Error('Google Forms did not provide a response ID.');
    }

    const payload = emptyPayload_();
    payload.response_id = responseId;
    payload.submitted_at = event.response.getTimestamp().toISOString();

    event.response.getItemResponses().forEach((itemResponse) => {
        const questionTitle = itemResponse.getItem().getTitle().trim();
        const target = QUESTION_MAPPING[questionTitle];

        if (!target) {
            throw new Error(`Unmapped Google Form item: ${questionTitle}`);
        }

        payload[target[0]][target[1]] = itemResponse.getResponse();
    });

    sendToLaravel_(payload);
}

function emptyPayload_() {
    const payload = {
        source: 'google_forms',
        response_id: '',
        submitted_at: null,
        child_data: {},
        father_data: {},
        mother_data: {},
        family_context: {},
        development_history: {},
        health_history: {},
    };

    Object.values(QUESTION_MAPPING).forEach(([group, key]) => {
        payload[group][key] = null;
    });

    return payload;
}

function sendToLaravel_(payload) {
    const endpoint = requiredProperty_('LARAVEL_ENDPOINT');
    const secret = requiredProperty_('WEBHOOK_SECRET');

    if (!endpoint.startsWith('https://')) {
        throw new Error('LARAVEL_ENDPOINT must use HTTPS.');
    }

    if (secret.length < 32) {
        throw new Error('WEBHOOK_SECRET must contain at least 32 characters.');
    }

    const body = JSON.stringify(payload);

    for (let attempt = 0; attempt < 4; attempt += 1) {
        const timestamp = String(Math.floor(Date.now() / 1000));
        const signatureBytes = Utilities.computeHmacSha256Signature(
            `${timestamp}.${body}`,
            secret,
            Utilities.Charset.UTF_8,
        );
        const signature = signatureBytes
            .map((byte) => ((byte + 256) % 256).toString(16).padStart(2, '0'))
            .join('');

        let response;
        try {
            response = UrlFetchApp.fetch(endpoint, {
                method: 'post',
                contentType: 'application/json',
                payload: body,
                headers: {
                    'X-Kasuari-Timestamp': timestamp,
                    'X-Kasuari-Signature': signature,
                },
                muteHttpExceptions: true,
            });
        } catch (error) {
            if (attempt === 3) {
                throw new Error('Could not reach the Kasuari Care intake endpoint.');
            }

            Utilities.sleep(500 * (2 ** attempt));
            continue;
        }

        const status = response.getResponseCode();
        if (status >= 200 && status < 300) {
            return;
        }

        if ((status === 429 || status >= 500) && attempt < 3) {
            Utilities.sleep(500 * (2 ** attempt));
            continue;
        }

        throw new Error(`Kasuari Care rejected the intake request (${status}).`);
    }
}

function validateQuestionTitles_(availableTitles) {
    const available = new Set(availableTitles.map((title) => title.trim()));
    const missing = Object.keys(QUESTION_MAPPING).filter((title) => !available.has(title));

    if (missing.length > 0) {
        throw new Error(`Google Form questions need mapping: ${missing.join(' | ')}`);
    }
}

function requiredProperty_(key) {
    const value = PropertiesService.getScriptProperties().getProperty(key);

    if (!value) {
        throw new Error(`Set the ${key} script property before running this integration.`);
    }

    return value.trim();
}
