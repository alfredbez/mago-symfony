<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\Console\Helper\InputAwareHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class Wave6UninitializedValidatorNegative extends ConstraintValidator
{
    private string $uninitializedExtra;

    public function validate(mixed $value, Constraint $constraint): void
    {
        $this->context->buildViolation($this->uninitializedExtra)->addViolation();
    }
}

final class Wave6UninitializedHelperNegative extends InputAwareHelper
{
    private string $uninitializedLabel;

    public function getName(): string
    {
        return $this->uninitializedLabel;
    }
}
