<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * PHPStan rule that detects when arguments are passed with their default values.
 *
 * Example of what this rule flags:
 *
 *     // Given: function foo(string $bar = 'default', int $baz = 10) {}
 *     foo('default');      // <-- Flagged! Passing the default value explicitly
 *     foo();               // OK - uses defaults implicitly
 *     foo('other');        // OK - passing a different value
 *     foo(baz: 20);        // OK - named parameter skips $bar's default
 *     foo('default', 20);  // <-- Flagged! Use foo(baz: 20) instead
 *
 * Why this matters:
 * - Reduces noise in function calls
 * - Makes intent clearer (only non-default values are visible)
 * - Encourages use of named parameters when skipping defaults
 *
 * @implements Rule<Node\Expr>
 */
final class ForbidRedundantDefaultValueArgumentRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    /**
     * Tell PHPStan which AST node type we want to analyze.
     *
     * We use Node\Expr (expression) as a broad filter, then narrow down
     * to specific call types in processNode(). This is more efficient than
     * registering multiple rules for each call type.
     */
    #[\Override]
    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    /**
     * Called by PHPStan for every expression node in the codebase.
     *
     * @return list<IdentifierRuleError>
     */
    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        // Only process call-like expressions (not assignments, arithmetic, etc.)
        if (!$node instanceof FuncCall && !$node instanceof MethodCall && !$node instanceof StaticCall && !$node instanceof New_) {
            return [];
        }

        // No arguments passed = nothing to check
        $args = $node->getArgs();
        if ($args === []) {
            return [];
        }

        // Get reflection info about the called function/method.
        // This gives us access to parameter names, types, and default values.
        $parametersAcceptor = $this->resolveParametersAcceptor($node, $scope);
        if ($parametersAcceptor === null) {
            return [];
        }

        $parameters = $parametersAcceptor->getParameters();

        return self::checkArguments(array_values($args), $parameters, $scope);
    }

    /**
     * Routes to the appropriate resolver based on call type.
     *
     * Each call type (function, method, static, constructor) has different
     * ways to obtain reflection information.
     */
    private function resolveParametersAcceptor(Node\Expr $node, Scope $scope): ?ParametersAcceptor
    {
        return match (true) {
            $node instanceof FuncCall => $this->resolveFunctionCall($node, $scope),
            $node instanceof MethodCall => self::resolveMethodCall($node, $scope),
            $node instanceof StaticCall => $this->resolveStaticCall($node, $scope),
            $node instanceof New_ => $this->resolveConstructorCall($node, $scope),
            default => null,
        };
    }

    /**
     * Resolves reflection for a function call like: array_map(...), my_function(...).
     */
    private function resolveFunctionCall(FuncCall $node, Scope $scope): ?ParametersAcceptor
    {
        // Dynamic function calls like $fn() can't be resolved statically
        if (!$node->name instanceof Name) {
            return null;
        }

        if (!$this->reflectionProvider->hasFunction($node->name, $scope)) {
            return null;
        }

        $functionReflection = $this->reflectionProvider->getFunction($node->name, $scope);

        // ParametersAcceptorSelector handles functions with multiple signatures
        // (e.g., implode() has different parameter orders for BC compatibility)
        return ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $node->getArgs(),
            $functionReflection->getVariants(),
            $functionReflection->getNamedArgumentsVariants(),
        );
    }

    /**
     * Resolves reflection for a method call like: $obj->method(...).
     */
    private static function resolveMethodCall(MethodCall $node, Scope $scope): ?ParametersAcceptor
    {
        // Dynamic method names like $obj->$method() can't be resolved
        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        // Get the type of the object we're calling the method on
        $callerType = $scope->getType($node->var);
        $methodName = $node->name->toString();

        // Check if this type actually has this method
        if (!$callerType->hasMethod($methodName)->yes()) {
            return null;
        }

        $methodReflection = $callerType->getMethod($methodName, $scope);

        return ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $node->getArgs(),
            $methodReflection->getVariants(),
            $methodReflection->getNamedArgumentsVariants(),
        );
    }

    /**
     * Resolves reflection for a static call like: Foo::bar(...).
     */
    private function resolveStaticCall(StaticCall $node, Scope $scope): ?ParametersAcceptor
    {
        // Dynamic class names like $class::method() can't be resolved
        if (!$node->class instanceof Name) {
            return null;
        }

        // Dynamic method names like Foo::$method() can't be resolved
        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        // Resolve the class name (handles self, static, parent)
        $className = $scope->resolveName($node->class);
        $methodName = $node->name->toString();

        if (!$this->reflectionProvider->hasClass($className)) {
            return null;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if (!$classReflection->hasMethod($methodName)) {
            return null;
        }

        $methodReflection = $classReflection->getMethod($methodName, $scope);

        return ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $node->getArgs(),
            $methodReflection->getVariants(),
            $methodReflection->getNamedArgumentsVariants(),
        );
    }

    /**
     * Resolves reflection for a constructor call like: new Foo(...).
     */
    private function resolveConstructorCall(New_ $node, Scope $scope): ?ParametersAcceptor
    {
        // Dynamic class names like new $class() can't be resolved
        if (!$node->class instanceof Name) {
            return null;
        }

        $className = $scope->resolveName($node->class);

        if (!$this->reflectionProvider->hasClass($className)) {
            return null;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        // No constructor = no parameters to check
        if (!$classReflection->hasConstructor()) {
            return null;
        }

        $constructorReflection = $classReflection->getConstructor();

        return ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $node->getArgs(),
            $constructorReflection->getVariants(),
            $constructorReflection->getNamedArgumentsVariants(),
        );
    }

    /**
     * Compares each argument against its parameter's default value.
     *
     * @param list<Arg>                 $args       The arguments passed in the call
     * @param list<ParameterReflection> $parameters The parameter definitions
     *
     * @return list<IdentifierRuleError>
     */
    private static function checkArguments(array $args, array $parameters, Scope $scope): array
    {
        $errors = [];

        foreach ($args as $i => $arg) {
            // Named arguments (e.g., foo(bar: 'value')) are already explicit about intent.
            // If someone writes `foo(bar: 'default')`, they probably want to be explicit.
            if ($arg->name !== null) {
                continue;
            }

            // Argument unpacking (e.g., foo(...$args)) can't be checked statically
            if ($arg->unpack) {
                continue;
            }

            // Variadic parameters or extra arguments beyond defined params
            if (!isset($parameters[$i])) {
                continue;
            }

            $parameter = $parameters[$i];

            // Required parameters have no default value - nothing to compare
            $defaultValue = $parameter->getDefaultValue();
            if ($defaultValue === null) {
                continue;
            }

            // Get the type of the argument expression.
            // For literals, this will be a constant type (e.g., ConstantStringType('foo')).
            // For variables, this will be a broader type (e.g., StringType).
            $argType = $scope->getType($arg->value);

            // Only flag if types match exactly.
            // This means `foo('default')` is flagged, but `foo($var)` is not
            // (even if $var happens to equal 'default' at runtime).
            if (self::typesAreIdentical($argType, $defaultValue)) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'Argument #%d ($%s) passes the default value. Consider omitting it or using named arguments.',
                        $i + 1,
                        $parameter->getName(),
                    ),
                )
                    ->identifier('argument.redundantDefault')
                    ->tip('Use named parameters to skip intermediate defaults, e.g., foo(bar: $value)')
                    ->build()
                ;
            }
        }

        return $errors;
    }

    /**
     * Checks if two types are exactly equal.
     *
     * PHPStan's type system represents literal values as "constant types":
     * - true/false → ConstantBooleanType
     * - 'hello' → ConstantStringType
     * - 42 → ConstantIntegerType
     * - null → NullType
     *
     * The equals() method checks for exact type equality, so:
     * - ConstantStringType('foo')->equals(ConstantStringType('foo')) → true
     * - ConstantStringType('foo')->equals(ConstantStringType('bar')) → false
     * - ConstantStringType('foo')->equals(StringType) → false
     */
    private static function typesAreIdentical(Type $argType, Type $defaultType): bool
    {
        return $argType->equals($defaultType);
    }
}
