<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Chart of Accounts Structural Hierarchy Level.
 */
enum AccountClassification: string
{
    case CLASS_ROOT = 'class';
    case MAIN_GROUP = 'main_group';
    case SUB_GROUP = 'sub_group';
    case POSTING = 'posting';

    /**
     * Check if this classification accepts direct journal entry lines.
     */
    public function allowsPostings(): bool
    {
        return $this === self::POSTING;
    }
}
