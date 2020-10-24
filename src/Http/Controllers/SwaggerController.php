<?php namespace FreedomCore\Swagger\Http\Controllers;

use FreedomCore\Swagger\Generator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use FreedomCore\Swagger\Formatter;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Controller as BaseController;
use FreedomCore\Swagger\Exceptions\ExtensionNotLoaded;
use Illuminate\Support\Facades\Response as ResponseFacade;
use FreedomCore\Swagger\Exceptions\InvalidFormatException;
use FreedomCore\Swagger\Exceptions\InvalidAuthenticationFlow;

/**
 * Class SwaggerController
 * @package FreedomCore\Swagger\Http\Controllers
 */
class SwaggerController extends BaseController {

    /**
     * Configuration repository
     * @var Repository
     */
    protected Repository $configuration;

    /**
     * SwaggerController constructor.
     * @param Repository $configuration
     */
    public function __construct(Repository $configuration) {
        $this->configuration = $configuration;
    }

    /**
     * Return documentation content
     * @param Request $request
     * @return Response
     * @throws ExtensionNotLoaded|InvalidFormatException|InvalidAuthenticationFlow
     */
    public function documentation(Request $request): Response {
        $documentation = swagger_resolve_documentation_file_path();
        if (strlen($documentation) === 0) {
            abort(404, sprintf('Please generate documentation first, then access this page'));
        }
        if (config('swagger.generated', false)) {
            $documentation = (new Generator($this->configuration))->generate();
            return ResponseFacade::make((new Formatter($documentation))->setFormat('json')->format(), 200, [
                'Content-Type' => 'application/json',
            ]);
        }
        $content = File::get($documentation);
        $yaml = Str::endsWith('yaml', pathinfo($documentation, PATHINFO_EXTENSION));
        if ($yaml) {
            return ResponseFacade::make($content, 200, [
                'Content-Type' => 'application/yaml',
                'Content-Disposition' => 'inline',
            ]);
        }
        return ResponseFacade::make($content, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Render Swagger UI page
     * @param Request $request
     * @return Response
     */
    public function api(Request $request): Response {
        $url = env('APP_URL');
        if (!Str::startsWith($url, 'http://') || !Str::startsWith($url, 'https://')) {
            $schema = swagger_is_connection_secure() ? 'https://' : 'http://';
            $url = $schema . $url;
        }
        return ResponseFacade::make(view('swagger::index', [
            'secure'            =>  swagger_is_connection_secure(),
            'urlToDocs'         =>  $url . config('swagger.path', '/documentation') . '/content'
        ]), 200);
    }

}
