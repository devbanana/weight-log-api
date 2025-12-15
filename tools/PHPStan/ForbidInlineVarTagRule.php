<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule that forbids inline @var annotations for type narrowing.
 *
 * Example of what this rule flags:
 *
 *     /** @var array<string, mixed> $data * /
 *     $data = $serializer->normalize($event);  // <-- Flagged!
 *
 * Why this matters:
 * - Inline @var can lie to the type system without runtime validation
 * - Using assert() provides both type narrowing AND runtime safety
 * - Throwing exceptions is even more explicit about failure cases
 *
 * Preferred alternatives:
 *
 *     $data = $serializer->normalize($event);
 *     assert(is_array($data));  // Runtime check + type narrowing
 *
 *     // Or for object types:
 *     if (!$event instanceof DomainEventInterface) {
 *         throw new \InvalidArgumentException('Expected DomainEventInterface');
 *     }
 *
 * @implements Rule<Expression>
 */
final class ForbidInlineVarTagRule implements Rule
{
    #[\Override]
    public function getNodeType(): string
    {
        return Expression::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return [];
        }

        if (preg_match('/@var\s/', $docComment->getText()) === 1) {
            return [
                RuleErrorBuilder::message(
                    'Inline @var is forbidden. Use assert() or throw an exception for type narrowing instead.',
                )
                    ->identifier('var.inlineForbidden')
                    ->tip('Example: $data = $foo->bar(); assert(is_array($data));')
                    ->build(),
            ];
        }

        return [];
    }
}
