<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Transformer;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Goto_;
use PhpParser\Node\Stmt\Label;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Reorders statements and inserts goto jumps to preserve execution order.
 */
final class StatementShuffler extends NodeVisitorAbstract implements TransformerInterface
{
    private ObfuscationContext $context;

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function transform(array $nodes, ObfuscationContext $context): array
    {
        if (!$context->config->shuffleStatements) {
            return $nodes;
        }

        $this->context = $context;

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        return $traverser->traverse($nodes);
    }

    public function enterNode(Node $node): ?Node
    {
        // Don't shuffle if it's already flattened?
        // Actually, we can shuffle any statement list.
        if ($node instanceof Function_ || $node instanceof ClassMethod || $node instanceof Closure) {
            if ($node->stmts !== null && count($node->stmts) > 1) {
                $node->stmts = $this->shuffle($node->stmts);
            }
        }
        return null;
    }

    /**
     * @param Node\Stmt[] $stmts
     * @return Node\Stmt[]
     */
    private function shuffle(array $stmts): array
    {
        if (count($stmts) <= 1) {
            return $stmts;
        }

        $blocks = [];
        $labels = [];

        for ($i = 0; $i < count($stmts); $i++) {
            $labels[$i] = $this->context->scrambler->scramble('L' . $i);
        }
        $endLabel = $this->context->scrambler->scramble('L_end');

        for ($i = 0; $i < count($stmts); $i++) {
            $currentLabel = $labels[$i];
            $nextLabel = ($i < count($stmts) - 1) ? $labels[$i + 1] : $endLabel;

            $blockStmts = [new Label($currentLabel), $stmts[$i]];

            if (!($stmts[$i] instanceof Return_)) {
                $blockStmts[] = new Goto_($nextLabel);
            }

            $blocks[] = $blockStmts;
        }

        // Shuffle the blocks
        shuffle($blocks);

        $finalStmts = [];
        // First, jump to the first label
        $finalStmts[] = new Goto_($labels[0]);

        foreach ($blocks as $block) {
            foreach ($block as $s) {
                $finalStmts[] = $s;
            }
        }

        $finalStmts[] = new Label($endLabel);

        return $finalStmts;
    }
}
