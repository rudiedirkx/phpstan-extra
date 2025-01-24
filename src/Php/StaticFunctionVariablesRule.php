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
		// If not enabled (= allowed), do check for @var doc.
		if (!$this->enabled) {
			$doc = $node->getDocComment();
			if ($doc && str_contains($doc->getText(), ' @var ')) {
				return [];
			}

			return [
				RuleErrorBuilder::message("Static function variables must have a @var doc.")
					->identifier('rudie.StaticFunctionVariablesRule.vardoc')
					->build(),
			];
		}

		// Or not allowed at all
		return [
			RuleErrorBuilder::message("Static function variables are bad.")
				->identifier('rudie.StaticFunctionVariablesRule.allowed')
				->build(),
		];
	}

}
