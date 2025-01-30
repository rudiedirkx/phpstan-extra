<?php

namespace rdx\PhpstanExtra\Laravel;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<FuncCall, array<string, string>>
 */
final class ControllerViewParamsCollector implements Collector {

	use AnalyzesViewParams;

	public function getNodeType() : string {
		return FuncCall::class;
	}

	public function processNode(Node $node, Scope $scope) : ?array {
		if (!($node instanceof FuncCall) || !($node->name instanceof Name) || $node->name->getParts() !== ['view']) {
			return null;
		}

		if (count($node->args) < 2) {
			return null;
		}

		$paramsArg = $node->args[1]->value;
		$paramsItems = $this->getViewParamsArray($paramsArg);
		if (!$paramsItems) {
			return null;
		}

		$paramsValuesGenerator = $this->getViewParamsValues($paramsItems, $scope, true);
		foreach ($paramsValuesGenerator as $message) {
			// Ignore messages
		}
		$vars = $paramsValuesGenerator->getReturn();

		return $vars;
	}

}
