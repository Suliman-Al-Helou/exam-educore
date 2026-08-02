<?php

namespace App\Enums;

enum DifficultyLevel: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';
    case Mixed = 'mixed';
}