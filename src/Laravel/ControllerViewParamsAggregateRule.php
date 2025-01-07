<?php

namespace rdx\PhpstanExtra\Laravel;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<CollectedDataNode>
 */
final class ControllerViewParamsAggregateRule implements Rule {

	public function getNodeType() : string {
		return CollectedDataNode::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$allVars = $node->get(ControllerViewParamsCollector::class);

		$types = [];
		foreach ($allVars as $file => $paramsArrays) {
			foreach ($paramsArrays as $vars) {
				foreach ($vars as $name => $type) {
					$types[] = $type;
				}
			}
		}

		$countedTypes = array_count_values($types);
		ksort($countedTypes, SORT_STRING);
// dump($countedTypes);

		return [];
	}

}
