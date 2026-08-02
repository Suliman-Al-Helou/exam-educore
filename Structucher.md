المكتبة
/
BACK
/
AI_EXAM_GENERATION_PROGRESS_2026-07-21.md


# توثيق تقدم ميزة توليد الاختبارات بالذكاء الاصطناعي

**المشروع:** EduCore Backend  
**التاريخ:** 2026-07-21  
**الحالة الحالية:** مولّد Gemini يعمل فعليًا ويعيد اختبارًا منظمًا من الكتاب المفهرس  
**نسبة إنجاز Back-End للميزة:** 78%

---

## 1. الهدف من الميزة

تمكين المعلم من إنشاء اختبار اعتمادًا على كتاب المنهاج المفهرس في Gemini File Search، مع دعم:

- اختيار مستوى الصعوبة.
- اختيار أنواع الأسئلة.
- تحديد عدد الأسئلة.
- تحديد العلامة الكلية والمدة.
- إضافة تعليمات خاصة من المعلم.
- إنشاء أسئلة مرتبطة بمحتوى الكتاب فقط.
- حفظ الأسئلة والخيارات في قاعدة البيانات.
- تنفيذ التوليد في Queue دون تعطيل طلب الـ API.

---

## 2. ما تم إنجازه

### 2.1 تجهيز قاعدة البيانات والموديلات

1. تجهيز الجداول الأساسية:
   - `ai_curriculum_documents`
   - `ai_exams`
   - `ai_exam_questions`
   - `ai_exam_options`
2. إضافة `difficulty` و`question_types` إلى `ai_exams`.
3. تحديث `AiExam` وإضافة Casts للحقول والحالات.
4. تحديث `AiExamQuestion` لتحويل نوع السؤال إلى Enum.

### 2.2 إنشاء Enums

1. `App\Enums\AiExamDifficulty`
   - `easy`
   - `medium`
   - `hard`
   - `mixed`
2. `App\Enums\AiExamQuestionType`
   - `multiple_choice`
   - `true_false`
3. استخدام `App\Enums\AiExamGenerationStatus`
   - `pending`
   - `generating`
   - `ready`
   - `failed`

### 2.3 التحقق من طلب إنشاء الاختبار

تم تحديث:

`app/Http/Requests/GenerateAiExamRequest.php`

ويتحقق من معرف المعلم، عنوان الاختبار، عنوان الدرس، مستوى الصعوبة، أنواع الأسئلة، العدد، العلامة، المدة، وتعليمات المعلم.

### 2.4 إنشاء سجل الاختبار بحالة Pending

تم إنشاء:

`app/Actions/AiExams/CreatePendingAiExam.php`

وظيفته:

1. التحقق من ملكية المعلم للكتاب.
2. التحقق من أن الكتاب مفهرس.
3. نسخ بيانات الصف والمادة والفصل والسنة من الكتاب.
4. إنشاء سجل الاختبار.
5. ضبط الحالة على `pending`.
6. منع استخدام كتاب يعود لمعلم آخر.

### 2.5 إنشاء API لطلب التوليد

تم إنشاء أو تحديث:

1. `app/Http/Controllers/Api/V1/AiExamController.php`
2. `app/Http/Resources/AiExamResource.php`
3. `routes/api.php`

المسار:

`POST /api/v1/curriculum-documents/{document}/exams`

النتيجة:

- يرجع `202 Accepted`.
- ينشئ سجل الاختبار.
- يرجع حالة `pending`.
- لا يكشف معرف المعلم أو المفاتيح السرية.

### 2.6 حماية الـ API

يستخدم المسار Middleware:

`app/Http/Middleware/EnsureAiServiceKey.php`

ويتحقق من `X-Service-Key`.

تم التأكد من رفض المفتاح غير الصحيح بحالة `401` وقبول المفتاح الصحيح.

### 2.7 تجهيز Queue Job

تم إنشاء:

`app/Jobs/GenerateAiExamJob.php`

ويحتوي على:

1. ثلاث محاولات.
2. مهلة تنفيذ.
3. Backoff.
4. منع تكرار Job للاختبار نفسه.
5. انتقال `pending → generating`.
6. انتقال الفشل النهائي إلى `failed`.
7. حفظ `generation_error`.
8. عدم إعادة توليد اختبار حالته `ready`.

تم اختبار جميع انتقالات الحالة يدويًا بنجاح.

> لم يتم ربط الـ Job بالـ Controller حتى الآن؛ لأن حفظ الأسئلة لم يكتمل بعد.

### 2.8 إعداد Gemini

تم تحديث `config/ai.php` وإضافة:

`GEMINI_MODEL=gemini-3.5-flash`

تم اختبار:

1. وجود المفتاح دون طباعته.
2. رابط API.
3. اسم الموديل.
4. استجابة Models API بحالة `200`.
5. دعم `generateContent`.

### 2.9 إنشاء GeminiExamGenerator

تم إنشاء:

`app/Services/Ai/Exams/GeminiExamGenerator.php`

وظيفته:

1. التحقق من الكتاب المفهرس.
2. استخدام Gemini File Search Store.
3. فلترة الوثيقة بواسطة `document_id`.
4. بناء Prompt عربي صارم.
5. استخدام Structured JSON Output.
6. دعم Multiple Choice وTrue/False.
7. التحقق من عدد الأسئلة والأنواع والخيارات.
8. التحقق من إجابة صحيحة واحدة لكل سؤال.
9. منع الخيارات المكررة.
10. إضافة Retry لأخطاء الضغط والشبكة.
11. تجميع كل أجزاء النص.
12. إزالة Markdown fences وBOM.
13. تحسين أخطاء JSON وإظهار Response Preview.

---

## 3. المشاكل التي ظهرت وحلولها

### 3.1 الحقول لا تصل من Postman

**السبب:** Body لم يكن Raw JSON.  
**الحل:** استخدام `Body → raw → JSON` مع `Content-Type: application/json`.

### 3.2 خطأ 401 Unauthorized

**السبب:** استخدام النص التجريبي بدل `AI_SERVICE_KEY`.  
**الحل:** إرسال المفتاح الصحيح من `.env` دون طباعته.

### 3.3 رفض responseFormat

**السبب:** بنية غير صحيحة داخل `generationConfig`.  
**الحل:** استخدام `responseMimeType` و`responseJsonSchema`.

### 3.4 ضغط مؤقت على Gemini

**الخطأ:** High Demand / HTTP 503.  
**الحل:** Retry مع Exponential Backoff وRandom Jitter للأخطاء المؤقتة.

### 3.5 Gemini returned invalid JSON

**السبب المحتمل:** تعدد أجزاء النص أو Markdown wrappers.  
**الحل:** تجميع كل `content.parts`، إزالة الأغلفة، واستخراج كائن JSON الصحيح.

---

## 4. نتائج الاختبارات النهائية لـ GeminiExamGenerator

تم توليد اختبار فعلي من كتاب العلوم والحياة للصف الخامس.

1. عدد الأسئلة: `10`.
2. الأنواع:
   - `multiple_choice`
   - `true_false`
3. خمسة أسئلة اختيار من متعدد، لكل سؤال `4` خيارات.
4. خمسة أسئلة صح وخطأ، لكل سؤال خياران.
5. لكل سؤال إجابة صحيحة واحدة فقط.
6. كل سؤال يحتوي:
   - `type`
   - `question_text`
   - `explanation`
   - `source_reference`
   - `options`
7. الأسئلة مرتبطة بمراجع من الكتاب.
8. لم تُحفظ الأسئلة في قاعدة البيانات بعد.
9. عدد الأسئلة المخزنة للاختبار: `0`.
10. حالة الاختبار بقيت `pending`.
11. لا يوجد `GenerateAiExamJob` معلق في جدول `jobs`.

---

## 5. ملاحظة جودة المحتوى

ظهر خطأ لغوي بسيط في إحدى الإجابات المولدة:

`بغلافف نووي`

هذا لا يؤثر على البنية التقنية، لكنه يثبت الحاجة إلى طبقة مراجعة أو تنظيف نصي قبل اعتماد الاختبار للطالب.

---

## 6. نسبة الإنجاز

### GeminiExamGenerator

**100% مكتمل**

### Back-End الكامل لميزة توليد الاختبارات

**78% مكتمل**

### المتبقي

**22%**

---

## 7. ما تبقى

### 7.1 حفظ الأسئلة والخيارات

إنشاء:

`app/Actions/AiExams/SaveGeneratedAiExam.php`

المطلوب:

1. فتح Database Transaction.
2. حذف أي نتائج جزئية قديمة عند إعادة المحاولة.
3. توزيع `total_points` على الأسئلة.
4. إنشاء `ai_exam_questions`.
5. إنشاء `ai_exam_options`.
6. ضبط `position`.
7. تحديث الاختبار إلى `ready`.
8. حفظ `generated_at`.
9. حفظ `ai_model`.

### 7.2 ربط التوليد بالـ Job

تحديث `GenerateAiExamJob` ليقوم بـ:

1. تحويل الحالة إلى `generating`.
2. استدعاء `GeminiExamGenerator`.
3. استدعاء `SaveGeneratedAiExam`.
4. تحويل الحالة إلى `ready`.
5. تسجيل `failed` عند الخطأ النهائي.

### 7.3 إرسال Job بعد إنشاء الاختبار

إضافة:

`GenerateAiExamJob::dispatch($exam->id)`

بعد نجاح إنشاء سجل الاختبار.

### 7.4 تشغيل Queue Worker

تشغيل:

`php artisan queue:work`

واختبار المعالجة الكاملة.

### 7.5 API للاستعلام عن الحالة والنتيجة

إنشاء مسارات مثل:

1. `GET /api/v1/ai-exams/{exam}`
2. `GET /api/v1/ai-exams/{exam}/status`

### 7.6 الاختبارات الآلية

إضافة Unit وFeature Tests لـ:

1. Validation.
2. Ownership.
3. إنشاء Pending Exam.
4. انتقالات الحالة.
5. Gemini HTTP Fake.
6. Retry behavior.
7. حفظ الأسئلة والخيارات.
8. فشل Transaction.
9. End-to-End flow.

### 7.7 جودة وأمان النتيجة

1. تنظيف الأخطاء الإملائية البسيطة.
2. كشف تكرار المعاني.
3. التحقق من مراجع الصفحات.
4. إضافة مراجعة المعلم قبل النشر.
5. منع عرض `is_correct` للطلاب.

---

## 8. خطة الاستكمال

1. إنشاء `SaveGeneratedAiExam`.
2. اختباره باستخدام نتيجة Gemini الحالية.
3. ربطه داخل `GenerateAiExamJob`.
4. إرسال Job من Endpoint الإنشاء.
5. تشغيل `queue:work`.
6. تنفيذ اختبار End-to-End:
   - `pending`
   - `generating`
   - حفظ الأسئلة
   - `ready`
7. إنشاء API لقراءة الحالة والنتيجة.

---

## 9. نقطة الاستئناف

- Exam ID: `01ky1yt7jm5paxefg8geff4rkk`
- Document ID: `01kxzdhrvgyxfwpn05qx1dsakd`
- Teacher ID: `teacher-001`
- الحالة الحالية: `pending`
- عدد الأسئلة المخزنة: `0`
- Jobs معلقة: `0`

أول ملف سيتم إنشاؤه عند الاستكمال:

`app/Actions/AiExams/SaveGeneratedAiExam.php`
