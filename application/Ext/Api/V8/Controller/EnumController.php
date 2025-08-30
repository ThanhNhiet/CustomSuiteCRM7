<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EnumController
{
    public function getEnumOptions(Request $request, Response $response, array $args)
    {
        $optionName = $args['options'];
        $queryParams = $request->getQueryParams();
        $lang = $queryParams['lang'] ?? 'en_us'; // default en_us nếu không có lang

        global $app_list_strings;

        // Load ngôn ngữ theo lang
        $GLOBALS['current_language'] = $lang;
        $app_list_strings = return_app_list_strings_language($lang);

        // Kiểm tra option
        if (!isset($app_list_strings[$optionName])) {
            $result = [
                'success' => false,
                'message' => "Enum option '{$optionName}' not found in language '{$lang}'"
            ];
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $result = [
            'success' => true,
            'option' => $optionName,
            'lang' => $lang,
            'values' => $app_list_strings[$optionName],
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
