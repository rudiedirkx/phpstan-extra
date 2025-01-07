<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;

final class RoundReturnTypeExtension implements ExpressionTypeResolverExtension {

	public function getType(Expr $expr, Scope $scope) : ?Type {
		if (!($expr instanceof FuncCall)) {
			return null;
		}

		if (!($expr->name instanceof Name)) {
			return null;
		}

		if (!in_array($expr->name->toLowerString(), ['round', 'floor', 'ceil'])) {
			return null;
		}

		if ($this->returnTypeIsInt($expr->getArgs())) {
			return new IntegerType();
		}

		return null;
	}

	/**
	 * @param list<Arg> $args
	 */
	protected function returnTypeIsInt(array $args) : bool {
		if (count($args) == 0) {
			return false;
		}

		if (count($args) == 1) {
			return true;
		}

		$precisionArg = $args[1]->value;
		if ($precisionArg instanceof LNumber && $precisionArg->value === 0) {
			return true;
		}
		return false;
	}

}
