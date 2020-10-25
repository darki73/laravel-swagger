<?php namespace FreedomCore\Swagger;

use Exception;
use FreedomCore\Swagger\Exceptions\InvalidDefinitionException;
use ReflectionMethod;
use ReflectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Laravel\Passport\Passport;
use FreedomCore\Swagger\Parameters;
use FreedomCore\Swagger\DataObjects;
use phpDocumentor\Reflection\DocBlock\Tag;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Http\FormRequest;
use phpDocumentor\Reflection\DocBlockFactory;
use Laravel\Passport\Http\Middleware\CheckScopes;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use Laravel\Passport\Http\Middleware\CheckForAnyScope;
use FreedomCore\Swagger\Exceptions\InvalidAuthenticationFlow;

/**
 * Class Generator
 * @package FreedomCore\Swagger
 */
class Generator {

    const OAUTH_TOKEN_PATH = '/oauth/token';

    const OAUTH_AUTHORIZE_PATH = '/oauth/authorize';

    /**
     * Configuration repository instance
     * @var Repository
     */
    protected Repository $configuration;

    /**
     * Route filter value
     * @var string|null
     */
    protected ?string $routeFilter;

    /**
     * Parser instance
     * @var DocBlockFactory
     */
    protected DocBlockFactory $parser;

    /**
     * Indicates whether we have security definitions
     * @var bool
     */
    protected bool $hasSecurityDefinitions;

    /**
     * List of ignored routes and methods
     * @var array
     */
    protected array $ignored;

    /**
     * Items to be appended to documentation
     * @var array
     */
    protected array $append;

    /**
     * Generator constructor.
     * @param Repository $config
     * @param string|null $routeFilter
     */
    public function __construct(Repository $config, ?string $routeFilter = null) {
        $this->configuration = $config;
        $this->routeFilter = $routeFilter;
        $this->parser = DocBlockFactory::createInstance();
        $this->hasSecurityDefinitions = false;
        $this->ignored = $this->fromConfig('ignored', []);
        $this->append = $this->fromConfig('append', []);
    }

    /**
     * Generate documentation
     * @return array
     * @throws InvalidAuthenticationFlow
     */
    public function generate(): array {
        $documentation = $this->generateBaseInformation();
        $applicationRoutes = $this->getApplicationRoutes();

        if ($this->fromConfig('parse.security', false) && $this->hasOAuthRoutes($applicationRoutes)) {
            Arr::set($documentation, 'components.securitySchemes', $this->generateSecurityDefinitions());
            $this->hasSecurityDefinitions = true;
        }

        foreach ($applicationRoutes as $route) {
            if ($this->isFilteredRoute($route)) {
                continue;
            }
            $pathKey = 'paths.' . $route->uri();

            if (!Arr::has($documentation, $pathKey)) {
                Arr::set($documentation, $pathKey, []);
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, Arr::get($this->ignored, 'methods'))) {
                    continue;
                }
                $methodKey = $pathKey . '.' . $method;
                Arr::set($documentation, $methodKey, $this->generatePath($route, $method));
            }
        }
        return $documentation;
    }

    /**
     * Generate base information
     * @return array
     */
    private function generateBaseInformation(): array {
        return [
            'openapi'               =>  '3.0.0',
            'info'                  =>  [
                'title'             =>  $this->fromConfig('title'),
                'description'       =>  $this->fromConfig('description'),
                'version'           =>  $this->fromConfig('version')
            ],
            'servers'               =>  $this->generateServersList(),
            'paths'                 =>  [],
            'tags'                  =>  $this->fromConfig('tags'),
        ];
    }

    /**
     * Get list of application routes
     * @return array|DataObjects\Route[]
     */
    private function getApplicationRoutes(): array {
        return array_map(function (Route $route): DataObjects\Route {
            return new DataObjects\Route($route);
        }, app('router')->getRoutes()->getRoutes());
    }

    /**
     * Get value from configuration
     * @param string $key
     * @param mixed $default
     * @return array|mixed
     */
    private function fromConfig(string $key, $default = null) {
        return $this->configuration->get('swagger.' . $key, $default);
    }

    /**
     * Generate servers list from configuration
     * @return array
     */
    private function generateServersList(): array {
        $rawServers = $this->fromConfig('servers');
        $servers = [];

        foreach ($rawServers as $index => $server) {
            if (is_array($server)) {
                $url = Arr::get($server, 'url', null);
                $description = Arr::get($server, 'description', null);
                if ($url) {
                    array_push($servers, [
                        'url'           =>  $url,
                        'description'   =>  $description ?: sprintf('%s Server #%d', $this->fromConfig('title'), $index + 1)
                    ]);
                }
            } else {
                array_push($servers, [
                    'url'           =>  $server,
                    'description'   =>  sprintf('%s Server #%d', $this->fromConfig('title'), $index + 1)
                ]);
            }
        }

        if (\count($servers) === 0) {
            array_push($servers, [
                'url'           =>  env('APP_URL'),
                'description'   =>  env('APP_NAME') . ' Main Server'
            ]);
        }

        return $servers;
    }

    /**
     * @param array|DataObjects\Route[] $applicationRoutes
     * @return bool
     */
    private function hasOAuthRoutes(array $applicationRoutes): bool {
        foreach ($applicationRoutes as $route) {
            $uri = $route->uri();
            if (
                $uri === self::OAUTH_TOKEN_PATH ||
                $uri === self::OAUTH_AUTHORIZE_PATH
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check whether this is filtered route
     * @param DataObjects\Route $route
     * @return bool
     */
    private function isFilteredRoute(DataObjects\Route $route): bool  {
        $ignoredRoutes = Arr::get($this->ignored, 'routes');
        $routeName = $route->name();
        $routeUri = $route->uri();
        if ($routeName) {
            if (in_array($routeName, $ignoredRoutes)) {
                return true;
            }
        }

        if (in_array($routeUri, $ignoredRoutes)) {
            return true;
        }
        if ($this->routeFilter) {
            return !preg_match('/^' . preg_quote($this->routeFilter, '/') . '/', $route->uri());
        }
        return false;
    }

    /**
     * Generate Path information
     * @param DataObjects\Route $route
     * @param string $method
     * @return array
     */
    private function generatePath(DataObjects\Route $route, string $method): array {
        $actionClassInstance = $this->getActionClassInstance($route);
        $documentationBlock = $actionClassInstance ? ($actionClassInstance->getDocComment() ?: ''): '';

        $documentation = $this->parseActionDocumentationBlock($documentationBlock);

        if (\count(Arr::get($documentation, 'responses')) === 0) {
            Arr::set($documentation, 'responses', [
                '200'       =>  [
                    'description'   =>  'OK',
                ]
            ]);
        }

        foreach ($this->append['responses'] as $code => $response) {
            if (!Arr::has($documentation, 'responses.' . $code)) {
                Arr::set($documentation, 'responses.' . $code, $response);
            }
        }

        $this->addActionsParameters($documentation, $route, $method, $actionClassInstance);

        if ($this->hasSecurityDefinitions) {
            $this->addActionScopes($documentation, $route);
        }
        return $documentation;
    }

    /**
     * Get action class instance
     * @param DataObjects\Route $route
     * @return ReflectionMethod|null
     */
    private function getActionClassInstance(DataObjects\Route $route): ?ReflectionMethod {
        [$class, $method] = Str::parseCallback($route->action());
        if (!$class || !$method) {
            return null;
        }
        try {
            return new ReflectionMethod($class, $method);
        } catch (ReflectionException $exception) {
            return null;
        }
    }

    /**
     * Parse action documentation block
     * @param string $documentationBlock
     * @return array
     */
    private function parseActionDocumentationBlock(string $documentationBlock): array {
        $documentation = [
            'summary'       =>  '',
            'description'   =>  '',
            'deprecated'    =>  false,
            'responses'     =>  [],
        ];
        if (empty($documentationBlock) || !$this->fromConfig('parse.docBlock', false)) {
            return $documentation;
        }

        try {
            $parsedComment = $this->parser->create($documentationBlock);
            Arr::set($documentation, 'deprecated', $parsedComment->hasTag('deprecated'));
            Arr::set($documentation, 'summary', $parsedComment->getSummary());
            Arr::set($documentation, 'description', (string) $parsedComment->getDescription());

            $hasRequest = $parsedComment->hasTag('Request');
            $hasResponse = $parsedComment->hasTag('Response');

            if ($hasRequest) {
                $firstTag = Arr::first($parsedComment->getTagsByName('Request'));
                $tagData = $this->parseRawDocumentationTag($firstTag);
                foreach ($tagData as $row) {
                    [$key, $value] = array_map(fn(string $value) => trim($value), explode(':', $row));
                    if ($key === 'tags') {
                        $value = array_map(fn(string $string) => trim($string), explode(',', $value));
                    }
                    Arr::set($documentation, $key, $value);
                }
            }

            if ($hasResponse) {
                $responseTags = $parsedComment->getTagsByName('Response');
                foreach ($responseTags as $rawTag) {
                    $tagData = $this->parseRawDocumentationTag($rawTag);
                    $responseCode = '';
                    foreach ($tagData as $value) {
                        [$key, $value] = array_map(fn(string $value) => trim($value), explode(':', $value));
                        if ($key === 'code') {
                            $responseCode = $value;
                            $documentation['responses'][$value] = [
                                'description'   =>  '',
                            ];
                        } else if ($key === 'description') {
                            $documentation['responses'][$responseCode]['description'] = $value;
                        }
                    }
                }
            }
            return $documentation;
        } catch (Exception $exception) {
            return $documentation;
        }
    }

    /**
     * Append action parameters
     * @param array $information
     * @param DataObjects\Route $route
     * @param string $method
     * @param ReflectionMethod|null $actionInstance
     */
    private function addActionsParameters(array & $information, DataObjects\Route $route, string $method, ?ReflectionMethod $actionInstance): void {
        $rules = $this->retrieveFormRules($actionInstance) ?: [];
        $parameters = (new Parameters\PathParametersGenerator($route->originalUri()))->getParameters();

        $key = 'parameters';
        if (\count($rules) > 0) {
            $parametersGenerator = $this->getParametersGenerator($rules, $method);
            switch ($parametersGenerator->getParameterLocation()) {
                case 'body':
                    $key = 'requestBody';
                    break;
            }
            $parameters = array_merge($parameters, $parametersGenerator->getParameters());
        }
        if (\count($parameters) > 0) {
            Arr::set($information, $key, $parameters);
        }
    }

    /**
     * Add action scopes
     * @param array $information
     * @param DataObjects\Route $route
     */
    private function addActionScopes(array & $information, DataObjects\Route $route) {
        foreach ($route->middleware() as $middleware) {
            if ($this->isPassportScopeMiddleware($middleware)) {
                $security = [
                    '_temp'     =>  $middleware->parameters(),
                ];
                foreach ($this->fromConfig('authentication_flow') as $definition => $value) {
                    $parameters = ($definition === 'OAuth2') ? $middleware->parameters() : [];
                    $security[$definition] = $parameters;
                }
                if (\count(Arr::flatten($security)) > 0) {
                    unset($security['_temp']);
                    Arr::set($information, 'security', [$security]);
                }
            }
        }
    }

    /**
     * Retrieve form rules
     * @param ReflectionMethod|null $actionInstance
     * @return array
     */
    private function retrieveFormRules(?ReflectionMethod $actionInstance): array {
        if (!$actionInstance) {
            return [];
        }
        $parameters = $actionInstance->getParameters();

        foreach ($parameters as $parameter) {
            $class = $parameter->getClass();
            if (!$class) {
                continue;
            }
            $className = $class->getName();
            if (is_subclass_of($className, FormRequest::class)) {
                return (new $className)->rules();
            }
        }
        return [];
    }

    /**
     * Get appropriate parameters generator
     * @param array $rules
     * @param string $method
     * @return Parameters\Interfaces\ParametersGenerator
     */
    private function getParametersGenerator(array $rules, string $method): Parameters\Interfaces\ParametersGenerator {
        switch ($method) {
            case 'post':
            case 'put':
            case 'patch':
                return new Parameters\BodyParametersGenerator($rules);
            default:
                return new Parameters\QueryParametersGenerator($rules);
        }
    }

    /**
     * Check whether specified middleware belongs to Laravel Passport
     * @param DataObjects\Middleware $middleware
     * @return bool
     */
    private function isPassportScopeMiddleware(DataObjects\Middleware $middleware) {
        $resolver = $this->getMiddlewareResolver($middleware->name());
        return $resolver === CheckScopes::class || CheckForAnyScope::class;
    }

    /**
     * Get middleware resolver class
     * @param string $middleware
     * @return string|null
     */
    private function getMiddlewareResolver(string $middleware): ?string {
        $middlewareMap = app('router')->getMiddleware();
        return $middlewareMap[$middleware] ?? null;
    }

    /**
     * Parse raw documentation tag
     * @param Generic|Tag $rawTag
     * @return array
     */
    private function parseRawDocumentationTag($rawTag): array {
        return Str::of((string) $rawTag)
            ->replace('({', '')
            ->replace('})', '')
            ->explode(PHP_EOL)
            ->filter(fn(string $value) => strlen($value) > 1)
            ->map(fn(string $value) => rtrim(trim($value), ','))
            ->toArray();
    }

    /**
     * Generate security definitions
     * @return array[]
     * @throws InvalidAuthenticationFlow|InvalidDefinitionException
     */
    private function generateSecurityDefinitions(): array {
        $authenticationFlows = $this->fromConfig('authentication_flow');

        $definitions = [];

        foreach ($authenticationFlows as $definition => $flow) {
            $this->validateAuthenticationFlow($definition, $flow);
            $definitions[$definition] = $this->createSecurityDefinition($definition, $flow);
        }

        return $definitions;
    }

    /**
     * Create security definition
     * @param string $definition
     * @param string $flow
     * @return array|string[]
     */
    private function createSecurityDefinition(string $definition, string $flow): array {
        switch ($definition) {
            case 'OAuth2':
                $definitionBody = [
                    'type'      =>  'oauth2',
                    'flows'      =>  [
                        $flow => []
                    ],
                ];
                $flowKey = 'flows.' . $flow . '.';
                if (in_array($flow, ['implicit', 'authorizationCode'])) {
                    Arr::set($definitionBody, $flowKey . 'authorizationUrl', $this->getEndpoint(self::OAUTH_AUTHORIZE_PATH));
                }

                if (in_array($flow, ['password', 'application', 'authorizationCode'])) {
                    Arr::set($definitionBody, $flowKey . 'tokenUrl', $this->getEndpoint(self::OAUTH_TOKEN_PATH));
                }
                Arr::set($definitionBody, $flowKey . 'scopes', $this->generateOAuthScopes());
                return $definitionBody;
            case 'bearerAuth':
                return [
                    'type'          =>  $flow,
                    'scheme'        =>  'bearer',
                    'bearerFormat'  =>  'JWT'
                ];
        }
        return [];
    }

    /**
     * Validate selected authentication flow
     * @param string $definition
     * @param string $flow
     * @throws InvalidAuthenticationFlow|InvalidDefinitionException
     */
    private function validateAuthenticationFlow(string $definition, string $flow): void {
        $definitions = [
            'OAuth2'            =>  ['password', 'application', 'implicit', 'authorizationCode'],
            'bearerAuth'        =>  ['http']
        ];

        if (!Arr::has($definitions, $definition)) {
            throw new InvalidDefinitionException('Invalid Definition, please select from the following: ' . implode(', ', array_keys($definitions)));
        }

        $allowed = $definitions[$definition];
        if (!in_array($flow, $allowed)) {
            throw new InvalidAuthenticationFlow('Invalid Authentication Flow, please select one from the following: ' . implode(', ', $allowed));
        }
    }

    /**
     * Get endpoint
     * @param string $path
     * @return string
     */
    private function getEndpoint(string $path): string {
        $host = $this->fromConfig('host');
        if (!Str::startsWith($host,'http://') || !Str::startsWith($host,'https://')) {
            $schema = swagger_is_connection_secure() ? 'https://' : 'http://';
            $host = $schema . $host;
        }
        return rtrim($host, '/') . $path;
    }

    /**
     * Generate OAuth scopes
     * @return array
     */
    private function generateOAuthScopes(): array {
        if (!class_exists(Passport::class)) {
            return [];
        }

        $scopes = Passport::scopes()->toArray();
        return array_combine(array_column($scopes, 'id'), array_column($scopes, 'description'));
    }

}
