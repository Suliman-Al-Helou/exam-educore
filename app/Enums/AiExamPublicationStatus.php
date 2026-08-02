<?php

namespace App\Enums;

enum AiExamPublicationStatus: string
{
    /**
     * The teacher can review and edit the exam.
     */
    case Draft = 'draft';

    /**
     * The exam is approved and available for assignment.
     */
    case Published = 'published';

    /**
     * The exam is no longer active but remains stored.
     */
    case Archived = 'archived';
}


// وظيفة الكود
// draft

// الاختبار مسودة، ويمكن للمعلم مراجعته وتعديله.

// published

// الاختبار تم اعتماده، وأصبح جاهزًا للإسناد للطلاب.

// archived

// الاختبار مؤرشف وغير نشط، لكنه غير محذوف.