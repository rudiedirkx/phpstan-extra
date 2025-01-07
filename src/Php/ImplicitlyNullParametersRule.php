<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Param>
 */
final class ImplicitlyNullParametersRule implements Rule {

	public function getNodeType() : string {
		return Param::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (!$node->type) {
			return [];
		}

		if ($this->isExplicitlyNullable($node->type)) {
			return [];
		}

		if (!$node->default) {
			return [];
		}

		if (!($node->default instanceof ConstFetch) || strtolower(implode('\\', $node->default->name->getParts())) !== 'null') {
			return [];
		}

		return [
			RuleErrorBuilder::message(sprintf("Param '%s' is implicitly nullable.", $node->var->name))
				->identifier('rudie.ImplicitlyNullParametersRule')
				->build(),
		];
	}

	protected function isExplicitlyNullable(Node $type) : bool {
		if ($type instanceof NullableType) {
			return true;
		}

		if ($type instanceof Identifier && strtolower($type->name) === 'mixed') {
			return true;
		}

		if ($type instanceof UnionType) {
			foreach ($type->types as $subtype) {
				if ($subtype instanceof Identifier && $subtype->name === 'null') {
					return true;
				}
			}
		}

		return false;
	}

}
