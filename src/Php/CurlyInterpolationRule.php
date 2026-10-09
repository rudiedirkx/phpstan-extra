<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InterpolatedString>
 */
final class CurlyInterpolationRule implements Rule {

	public function __construct(
		protected ExprPrinter $exprPrinter,
	) {}

	public function getNodeType() : string {
		return InterpolatedString::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$errors = [];
		foreach ($node->parts as $part) {
			if ($part instanceof InterpolatedStringPart || $this->isSimple($part)) {
				continue;
			}

			$source = $this->exprPrinter->printExpr($part);

			$errors[] = RuleErrorBuilder::message(sprintf('String interpolation {%s} is not allowed. Use sprintf().', $source))
				->identifier('rudie.CurlyInterpolationRule')
				->line($part->getStartLine())
				->build();
		}

		return $errors;
	}

	protected function isSimple(Expr $expr) : bool {
		if ($expr instanceof Variable) {
			return true;
		}

		if ($expr instanceof PropertyFetch) {
			return $expr->var instanceof Variable && $expr->name instanceof Identifier;
		}

		return false;
	}

}
