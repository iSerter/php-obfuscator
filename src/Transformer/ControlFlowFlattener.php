<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Transformer;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Transforms structured control flow into a state machine (flattening).
 */
final class ControlFlowFlattener extends NodeVisitorAbstract implements TransformerInterface
{
    private ObfuscationContext $context;

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function transform(array $nodes, ObfuscationContext $context): array
    {
        if (!$context->config->flattenControlFlow) {
            return $nodes;
        }

        $this->context = $context;

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        return $traverser->traverse($nodes);
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Function_ || $node instanceof ClassMethod || $node instanceof Closure) {
            if ($node->stmts !== null && count($node->stmts) > 1) {
                $node->stmts = $this->flatten($node->stmts);
            }
        }
        return null;
    }

    /**
     * @param Node\Stmt[] $stmts
     * @return Node\Stmt[]
     */
    private function flatten(array $stmts): array
    {
        $stateVarName = $this->context->scrambler->scramble('v');
        $stateVar = new Variable($stateVarName);

        $blocks = [];
        $stateBase = 1000;

        for ($i = 0; $i < count($stmts); $i++) {
            $blocks[] = [
                'stmt' => $stmts[$i],
                'current' => $stateBase + ($i * 10),
                'next' => ($i < count($stmts) - 1) ? $stateBase + (($i + 1) * 10) : 0
            ];
        }

        $shuffled = $blocks;
        shuffle($shuffled);

        $cases = [];
        foreach ($shuffled as $block) {
            $caseStmts = [$block['stmt']];

            if (!($block['stmt'] instanceof Return_)) {
                $caseStmts[] = new Expression(new Assign($stateVar, new Int_($block['next'])));
            }
            $caseStmts[] = new Break_();

            $cases[] = new Case_(new Int_($block['current']), $caseStmts);
        }

        // Add dummy cases
        for ($j = 0; $j < 2; $j++) {
            $dummyState = 5000 + $j * 10;
            $cases[] = new Case_(new Int_($dummyState), [
                new Expression(new Assign($stateVar, new Int_(0))),
                new Break_()
            ]);
        }

        $switch = new Switch_($stateVar, $cases);

        // Simple opaque predicate: if( (42*1337)%2 == 0 )
        $opaqueCond = new Identical(
            new Node\Expr\BinaryOp\Mod(
                new Node\Expr\BinaryOp\Mul(new Int_(42), new Int_(1337)),
                new Int_(2)
            ),
            new Int_(0)
        );

        $while = new While_(new Node\Expr\ConstFetch(new Node\Name('true')), [
            $switch,
            new If_(new Node\Expr\BinaryOp\NotIdentical($stateVar, new Int_(0)), [
                'stmts' => [
                    new If_(new Node\Expr\BooleanNot($opaqueCond), [
                        'stmts' => [new Node\Stmt\Expression(new Node\Expr\FuncCall(new Node\Name('exit')))] // This will never be executed
                    ])
                ],
                'else' => new Node\Stmt\Else_([new Break_()])
            ])
        ]);

        return [
            new Expression(new Assign($stateVar, new Int_(1000))),
            $while
        ];
    }
}
