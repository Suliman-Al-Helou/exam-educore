<?php

namespace App\Enums;

enum AiExamGenerationStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';
}