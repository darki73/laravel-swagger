<?php namespace FreedomCore\Swagger\Parameters;

use Illuminate\Support\Arr;
use FreedomCore\Swagger\Parameters\Traits\GeneratesFromRules;
use FreedomCore\Swagger\Parameters\Interfaces\ParametersGenerator;

/**
 * Class QueryParametersGenerator
 * @package FreedomCore\Swagger\Parameters
 */
class QueryParametersGenerator implements ParametersGenerator {
    use GeneratesFromRules;

    /**
     * Rules array
     * @var array
     */
    protected array $rules;

    /**
     * Parameters location
     * @var string
     */
    protected string $location = 'query';

    /**
     * QueryParametersGenerator constructor.
     * @param array $rules
     */
    public function __construct(array $rules) {
        $this->rules = $rules;
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function getParameters(): array {
        $parameters = [];
        $arrayTypes = [];

        foreach ($this->rules as $parameter => $rule) {
            $parameterRules = $this->splitRules($rule);
            $enums = $this->getEnumValues($parameterRules);
            $type = $this->getParameterType($parameterRules);

            if ($this->isArrayParameter($parameter)) {
                $key = $this->getArrayKey($parameter);
                $arrayTypes[$key] = $type;
                continue;
            }

            $parameterObject = [
                'name'          =>  $parameter,
                'in'            =>  $this->getParameterLocation(),
                'description'   =>  '',
                'type'          =>  $type,
                'required'      =>  $this->isParameterRequired($parameterRules)
            ];

            if (\count($enums) > 0) {
                Arr::set($parameterObject, 'enum', $enums);
            }

            if ($type === 'array') {
                Arr::set($parameterObject, 'items', [
                    'type'  =>  'string'
                ]);
            }
            Arr::set($parameters, $parameter, $parameterObject);
        }

        $parameters = $this->addArrayTypes($parameters, $arrayTypes);
        return array_values($parameters);
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getParameterLocation(): string {
        return $this->location;
    }

    /**
     * Add array types
     * @param array $parameters
     * @param array $arrayTypes
     * @return array
     */
    protected function addArrayTypes(array $parameters, array $arrayTypes): array {
        foreach ($arrayTypes as $key => $type) {
            if (!isset($parameters[$key])) {
                $parameters[$key] = [
                    'name'          =>  $key,
                    'in'            =>  $this->getParameterLocation(),
                    'type'          =>  'array',
                    'required'      =>  false,
                    'description'   =>  '',
                    'items'         =>  [
                        'type'      =>  $type
                    ]
                ];
            } else {
                $parameters[$key]['type'] = 'array';
                $parameters[$key]['items']['type'] = $type;
            }
        }
        return $parameters;
    }

}
