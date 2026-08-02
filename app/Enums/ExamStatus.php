<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';
    case Published = 'published';
    case Archived = 'archived';
}