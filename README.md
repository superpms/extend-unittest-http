# extend-unittest-http

SuperPMS 的通用 HTTP 接口测试 Composer 包，使用 helper-fetch 发起真实请求。

## 职责

- Endpoint：构造身份请求头、公共参数和最终请求地址，发送真实 HTTP 请求。
- Generator：接收宿主整理的接口 class、HTTP 方法及 PHP 参数定义，生成独立请求类。
- DataAssertion / Response：按点路径断言 HTTP 响应或实际数据库查询结果。
- HttpTestCommand：供宿主测试流程继承，共享请求及断言统计，失败返回非零退出码。
- Setup：自动挂载宿主 utests/config.php，并加载 utests/autoload.php。

包通过 Composer 的 autoload.files 加载 bin/autoload.php，入口仅注册 TerminalLifecycleHook。宿主将包作为 Composer 依赖安装即可，不在项目 boot.json 中登记包入口；类加载使用包的 PSR-4 声明。

业务接口发现、validate 读取及实际生成命令归宿主 utests。包不保存接口清单，不读取 server 业务规则，不内置 OpenAPI 凭据。

## 目录

    src/pms/unittest/http/
    ├── Endpoint.php
    ├── assertion/
    ├── base/
    ├── contract/
    ├── exception/
    ├── generator/
    ├── request/
    ├── response/
    └── runtime/

## 宿主调用

生成类继承宿主的 Endpoint 适配类，构造时传入具体账号身份。执行实际请求时，按当前接口 class、身份终端、HTTP 配置和请求前缀动态生成地址。

宿主 Command 继承 pms\unittest\http\base\HttpTestCommand，在 test() 中显式编排接口流程及断言。公共请求头、业务成功码、SQL 查询和各测试账号由宿主维护。

单方法接口的 request(...) 参数对应宿主声明。多方法接口需显式传入 requestMethod；请求依赖型动态字段可以通过命名的可变参数提交。所有请求仍进入实际接口校验与认证流程。

## 许可

Apache-2.0，见 LICENSE.txt。
