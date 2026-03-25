<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Transformer;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use PhpParser\Node;

final class TransformerPipeline
{
    /** @var TransformerInterface[] */
    private array $transformers = [];

    public function addTransformer(TransformerInterface $transformer): self
    {
        $this->transformers[] = $transformer;
        return $this;
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function apply(array $nodes, ObfuscationContext $context): array
    {
        foreach ($this->transformers as $transformer) {
            $nodes = $transformer->transform($nodes, $context);
        }

        return $nodes;
    }
}
