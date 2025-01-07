<?php

namespace rdx\PhpstanExtra\Laravel;

use App\Models\User;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class AuthExtension implements DynamicStaticMethodReturnTypeExtension {

	public function getClass() : string {
		return 'Auth';
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection) : bool {
		return $methodReflection->getName() === 'user';
	}

	public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope) : Type {
		return TypeCombinator::addNull(new ObjectType(User::class));
	}

}
