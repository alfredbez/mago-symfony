<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\Console\Helper\InputAwareHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class Wave6NonBlankConstraint extends Constraint
{
    public string $message = 'Value must not be blank.';
}

final class Wave6ConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Wave6NonBlankConstraint) {
            return;
        }
        if ($value === '') {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}

final class Wave6InjectedConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly string $prefix) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Wave6NonBlankConstraint) {
            return;
        }
        if ($value === '') {
            $this->context->buildViolation($this->prefix . $constraint->message)->addViolation();
        }
    }
}

final class Wave6InputAwareHelper extends InputAwareHelper
{
    public function getName(): string
    {
        return 'wave6_input_helper';
    }

    public function isInteractiveInput(): bool
    {
        return $this->input->isInteractive();
    }
}
