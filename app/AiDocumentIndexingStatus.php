<?php

namespace App\Enums;

enum AiDocumentIndexingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Indexed = 'indexed';
    case Failed = 'failed';
}
// وظيفته: يمنع استخدام حالة غير معروفة. أي حالة يجب أن تكون واحدة من الأربع فقط.