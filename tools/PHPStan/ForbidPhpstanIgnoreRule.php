<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule that forbids phpstan-ignore comments (all variants).
 *
 * Why this matters:
 * - Ignoring errors hides potential bugs
 * - Forces developers to fix the root cause or improve type definitions
 * - Use stub files or proper type narrowing instead
 *
 * @implements Rule<Node>
 */
final class ForbidPhpstanIgnoreRule implements Rule
{
    #[\Override]
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check doc comments (/** ... */)
        $docComment = $node->getDocComment();
        if ($docComment !== null && self::containsPhpstanIgnore($docComment->getText())) {
            $errors[] = self::buildError();
        }

        // Check regular comments (// ... or /* ... */)
        foreach ($node->getComments() as $comment) {
            // Skip doc comments as they're handled above
            if ($comment instanceof Doc) {
                continue;
            }

            if (self::containsPhpstanIgnore($comment->getText())) {
                $errors[] = self::buildError();
            }
        }

        return $errors;
    }

    private static function containsPhpstanIgnore(string $text): bool
    {
        return preg_match('/@phpstan-ignore/', $text) === 1;
    }

    private static function buildError(): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            '@phpstan-ignore is forbidden. Fix the error, use assert(), or create a stub file instead.',
        )
            ->identifier('phpstanIgnore.forbidden')
            ->build()
        ;
    }
}
