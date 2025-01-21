<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Stmt\Static_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Static_>
 */
final class StaticFunctionVariablesRule implements Rule {

	public function __construct(
		protected bool $enabled,
	) {}

	public function getNodeType() : string {
		return Static_::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (!$this->enabled) {
			return [];
		}
// dump($node);

		return [
			RuleErrorBuilder::message("Static function variables are bad.")
				->identifier('rudie.StaticFunctionVariablesRule')
				->build(),
		];
	}

}
