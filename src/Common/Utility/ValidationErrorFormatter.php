<?php

declare(strict_types=1);

namespace App\Common\Utility;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationErrorFormatter
{
    public static function format(ConstraintViolationListInterface $violations): array
    {
        $formattedViolations = [];

        foreach ($violations as $violation) {
            $formattedViolations[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage()
            ];
        }

        return [
            'code' => 400,
            'message' => 'Validation failed',
            'violations' => $formattedViolations
        ];
    }
}
