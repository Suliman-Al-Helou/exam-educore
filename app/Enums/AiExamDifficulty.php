<?php

namespace App\Enums;

enum AiExamDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';
    case Mixed = 'mixed';
}