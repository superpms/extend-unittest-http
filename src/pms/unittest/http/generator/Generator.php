<?php
declare(strict_types=1);
namespace pms\unittest\http\generator;

use pms\unittest\http\Endpoint;

/** 根据宿主提供的 PHP 参数定义生成独立请求类，不读取业务校验规则。 */
final class Generator
{
    /** 生成关联实际接口类的源码，宿主负责发现接口并转换参数定义。 */
    public static function generate(string $className, array $definition, string $endpointClass): string
    {
        $className = ltrim($className, '\\');
        if (!is_subclass_of($endpointClass, Endpoint::class)) {
            throw new \InvalidArgumentException('生成类必须继承宿主 Endpoint 适配类');
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\\\\[a-zA-Z_][a-zA-Z0-9_]*)*$/D', $className)) {
            throw new \InvalidArgumentException('生成类名不合法');
        }
        $parts = explode('\\', $className);
        $short = array_pop($parts);
        $namespace = implode('\\', $parts);
        $methods = $definition['methods'];
        if ($methods === [] || count(array_unique($methods)) !== count($methods)) {
            throw new \InvalidArgumentException('接口方法定义为空或重复');
        }
        foreach ($methods as $method) {
            if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
                throw new \InvalidArgumentException('接口方法不支持: ' . $method);
            }
        }
        $multipleMethods = count($methods) > 1;
        $required = $multipleMethods ? ['string $requestMethod'] : [];
        $optional = $arguments = [];
        foreach ($definition['parameters'] as $parameter) {
            $field = $parameter['name'];
            if (!is_string($field) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $field)
                || in_array($field, ['requestParameters', 'requestFiles', 'requestExtra', 'this'], true)
                || ($multipleMethods && $field === 'requestMethod')) {
                throw new \InvalidArgumentException('请求字段不能生成 PHP 参数: ' . $field);
            }
            $type = $parameter['php_type'];
            $isRequired = $parameter['required'];
            $signature = ($isRequired || $type === 'mixed' ? $type : '?' . $type) . ' $' . $field;
            if ($isRequired) {
                $required[] = $signature;
            } else {
                $optional[] = $signature . ' = null';
            }
            $assignment = '$' . ($parameter['file'] ? 'requestFiles' : 'requestParameters')
                . '[' . var_export($field, true) . '] = $' . $field . ';';
            $arguments[] = $isRequired ? '        ' . $assignment . "\n"
                : '        if ($' . $field . ' !== null) { ' . $assignment . " }\n";
        }
        if ($definition['variadic']) {
            $optional[] = 'mixed ...$requestExtra';
        }
        $source = "<?php\ndeclare(strict_types=1);\n" . ($namespace === '' ? '' : "namespace $namespace;\n")
            . "\n/** 根据实际接口生成；接口变更后重新生成。 */\nfinal class $short extends \\$endpointClass\n{\n"
            . '    protected const INTERFACE_CLASS = \\' . ltrim($definition['interface'], '\\') . "::class;\n"
            . '    protected const HTTP_METHOD = ' . var_export($multipleMethods ? $methods : $methods[0], true) . ";\n\n"
            . '    public function request(' . implode(', ', [...$required, ...$optional])
            . "): \\pms\\unittest\\http\\response\\Response\n    {\n"
            . '        $requestParameters = ' . ($definition['variadic'] ? '$requestExtra' : '[]') . ";\n        \$requestFiles = [];\n"
            . implode('', $arguments)
            . '        return $this->send($requestParameters, $requestFiles' . ($multipleMethods ? ', $requestMethod' : '') . ");\n    }\n}\n";
        token_get_all($source, TOKEN_PARSE);
        return $source;
    }
}
