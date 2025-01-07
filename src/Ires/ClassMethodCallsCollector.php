<?php

namespace rdx\PhpstanExtra\Ires;

use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * @implements Collector<CallLike, string>
 */
final class ClassMethodCallsCollector implements Collector {

	use CaresAboutClassMethods;

	public function getNodeType() : string {
		return CallLike::class;
	}

	public function processNode(Node $node, Scope $scope) : string {
		// if (!$this->acceptCall($node)) {
		// 	return '';
		// }

		$methodName = $this->findMethodName($node, $scope);
		if (!$methodName) {
			return '';
		}

		return $methodName;

		// if ($node->name instanceof Identifier) {
		// 	return $this->maybeIgnoreName($node->name->name, 'call');
		// }
		// elseif ($node->name instanceof Variable) {
		// 	$varName = $node->name->name;
		// 	$var = $scope->getVariableType($varName);
		// 	// dd($var);
		// 	return '';
		// }

		// dd($node);
		// return $node->name->name;
	}

	private function findMethodName(CallLike $node, Scope $scope) : ?string {
		if ($node instanceof MethodCall || $node instanceof StaticCall) {
			if ($node->name instanceof Identifier) {
				return $this->maybeIgnoreName($node->name->name, 'call');
			}
			elseif ($node->name instanceof Variable) {
				$varName = $node->name->name;
				$var = $scope->getVariableType($varName);
				// dd($var);
				return null;
			}

			return null;
		}

		if ($node instanceof FuncCall) {
			if ($node->name instanceof Name) {
				if ($node->name->toCodeString() == 'call_user_func') {
					// dd($node);
				}
			}
			else {
				// dd($node->name);
			}
		}

		return null;
	}

	// private function acceptCall(CallLike $node) : bool {
	// 	return $node instanceof MethodCall || $node instanceof StaticCall || $node instanceof FuncCall;
	// }

}
