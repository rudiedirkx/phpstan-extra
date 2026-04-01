<?php

namespace rdx\PhpstanExtra\Laravel;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
class TransCallRule implements Rule
{
	public function __construct(
		private TranslationValidator $validator,
		private int $strictness
	) {}

	public function getNodeType(): string
	{
		return FuncCall::class;
	}

	/**
	 * @param FuncCall $node
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		if ($this->strictness === 0) {
			return [];
		}

		if (!($node->name instanceof Node\Name)) {
			return [];
		}

		$funcName = (string) $node->name;
		if (!in_array($funcName, ['trans', 'trans_choice'], true)) {
			return [];
		}

		$args = $node->getArgs();
		if (count($args) === 0) {
			return [];
		}

		$firstArg = $args[0]->value;
		$keys = $this->getStrings($firstArg);

		$errors = [];
		foreach ($keys as $key) {
			if (!$this->validator->isValidKey($key)) {
				$errors[] = RuleErrorBuilder::message($this->validator->getMessage('key', $key))
					->identifier('rudie.TransCallRule')
					->build();
			}
		}

		return $errors;
	}

	/**
	 * @return list<string>
	 */
	protected function getStrings(Expr $node) : array {
		if ($node instanceof String_) {
			return [$node->value];
		}

		if ($node instanceof Ternary) {
			$a = $this->getStrings($node->if);
			$b = $this->getStrings($node->else);
			return array_values(array_filter([...$a, ...$b]));
		}

		if ($node instanceof Concat && $node->left instanceof String_) {
			return [$node->left->value];
		}

// dump($node);

		return [];
	}

}
