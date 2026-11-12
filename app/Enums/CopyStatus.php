<?php

namespace App\Enums;

enum CopyStatus: string
{
    case Available = 'available';
    case Borrowed = 'borrowed';
}
