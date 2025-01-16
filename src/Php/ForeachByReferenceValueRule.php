<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Foreach_>
 */
final class ForeachByReferenceValueRule implements Rule {

	public function __construct(
		protected bool $enabled,
	) {}

	public function getNodeType() : string {
		return Foreach_::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (!$this->enabled) {
			return [];
		}

		if (!$node->byRef) {
			return [];
		}

		return [
			RuleErrorBuilder::message("Foreach value should not be by-reference.")
				->identifier('rudie.ForeachByReferenceValueRule')
				->build(),
		];
	}

}
