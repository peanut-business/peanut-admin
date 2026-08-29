<?php
declare(strict_types=1);

namespace app\common\validate;

use app\common\execution\CurrentExecutionContext;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\App;
use think\Validate;

/** Creates validators through the container and binds trusted execution state. */
final readonly class InputValidator
{
    public function __construct(
        private App $app,
        private CurrentExecutionContext $execution,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @param class-string<Validate>|array<string,mixed> $specification
     * @param array<string,string> $messages
     */
    public function validate(
        array $data,
        string|array $specification,
        array $messages = [],
        bool $batch = false,
    ): ValidatedInput {
        [$validator, $scene] = $this->createValidator($specification);
        if ($validator instanceof TenantContextValidate) {
            $scope = $this->execution->current()?->scope;
            if (!$scope instanceof TenantContext) {
                throw new \DomainException('VALIDATION_TENANT_CONTEXT_REQUIRED');
            }
            $validator->forTenant($scope);
        }
        if ($scene !== null) {
            $validator->scene($scene);
        }
        $validator->message($messages);
        if ($batch) {
            $validator->batch(true);
        }
        $validator->failException(true)->check($data);

        return new ValidatedInput($data);
    }

    /**
     * @param class-string<Validate>|array<string,mixed> $specification
     * @return array{Validate,?string}
     */
    private function createValidator(string|array $specification): array
    {
        if (is_array($specification)) {
            $validator = $this->app->make(Validate::class);
            $validator->rule($specification);
            return [$validator, null];
        }

        $scene = null;
        if (str_contains($specification, '.')) {
            [$specification, $scene] = explode('.', $specification, 2);
        }
        $class = str_contains($specification, '\\')
            ? $specification
            : $this->app->parseClass('validate', $specification);
        $validator = $this->app->make($class);
        if (!$validator instanceof Validate) {
            throw new \LogicException(sprintf('%s must extend %s.', $class, Validate::class));
        }

        return [$validator, $scene];
    }
}
