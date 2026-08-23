<?php
namespace App\Enums;
enum TaskType: string {
    case Checkbox = 'checkbox';
    case Duration = 'duration';
    case  Count = 'count';
}
