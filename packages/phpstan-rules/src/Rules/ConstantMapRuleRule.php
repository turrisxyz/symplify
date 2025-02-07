<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Symplify\PHPStanRules\NodeAnalyzer\ConditionCounter;
use Symplify\PHPStanRules\NodeAnalyzer\IfReturnAnalyzer;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\ConstantMapRuleRule\ConstantMapRuleRuleTest
 */
final class ConstantMapRuleRule implements Rule, DocumentedRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Static constant map should be extracted from this method';

    public function __construct(
        private NodeFinder $nodeFinder,
        private ConditionCounter $conditionCounter,
        private IfReturnAnalyzer $ifReturnAnalyzer
    ) {
    }

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        /** @var If_[] $ifs */
        $ifs = $this->nodeFinder->findInstanceOf($node, If_::class);

        // at least 2 options so it's worth it to make map
        $conditionScalarAndNonScalarCounter = $this->conditionCounter->resolveScalarConditionTypes($ifs);
        if ($conditionScalarAndNonScalarCounter->getScalarCount() <= 2) {
            return [];
        }

        if ($conditionScalarAndNonScalarCounter->getScalarRelative() <= 0.7) {
            return [];
        }

        $stmtScalarAndNonScalarCounter = $this->ifReturnAnalyzer->resolve($ifs);
        if ($stmtScalarAndNonScalarCounter->getScalarRelative() <= 0.7) {
            return [];
        }

        return [self::ERROR_MESSAGE];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run($value)
    {
        if ($value instanceof SomeType) {
            return 100;
        }

        if ($value instanceof AnotherType) {
            return 1000;
        }

        return 200;
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @var array<string, int>
     */
    private const TYPE_TO_VALUE = [
        SomeType::class => 100,
        AnotherType::class => 1000,
    ];

    public function run($value)
    {
        foreach (self::TYPE_TO_VALUE as $type => $value) {
            if (is_a($value, $type, true)) {
                return $value;
            }
        }

        return 200;
    }
}
CODE_SAMPLE
            ),
        ]);
    }
}
