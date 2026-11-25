<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum AccountClassification: string
{
    case CLASS_ROOT = 'class_root';
    case MAIN_GROUP = 'main_group';
    case SUB_GROUP = 'sub_group';
    case POSTING = 'posting';

    public function allowsPosting(): bool
    {
        return $this === self::POSTING;
    }
}
