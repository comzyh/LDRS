# LDRS

**L**AN **D**evice **R**eport **S**erivce

这是一个非常邪恶的软件，自用了很长的时间，用于监控某个局域网中有哪些设备在线，支持如下功能

- 定时向远程服务器汇报局域网内的设备（lan_device_reporter.py）
- 接收汇报，并存储，并支持简单的查看（anyonethere.php）
- Telegram 机器人支持 （anyonethere.php）
- 机器人支持对指定 MAC 地址命名，便于阅读，如果未命名，则显示制造商
- Repoter 支持 Linux （比如树莓派）和 Windows
- Repoter 可以注册为 Windows 服务

本程序力求文件数量精简，所以每个文件都很大

# Repoter 安装配置

> git clone https://github.com/comzyh/LDRS.git

修改配置, 编辑 lan_device_reporter.py 如下部分

```python
	'report_url': [
        'https://<your_host_here>/anyonethere.php',
    ],
```

执行：

> $ nohup python lan_device_reporter.py > ldrs.log &

你也可以使用诸如 systemd 或者 supervisord 等方式将这个软件运行为服务，或者使用 `windows_service.py` 将这个软件注册为 Windows 服务执行

# 服务端安装配置 

服务端程序是 anyonethere.php ， 单文件即可工作，要求所在目录可读写，并且 PHP 包含 Sqlite 扩展

将 anyonethere.php 上传到你的支持 PHP 服务器即可

# TelegramBot 的安装配置

Telegram 机器人士本程序的最方便的接口，如果你配置好机器人，你将可以使用 Telegram 调用本程序的如下功能

- **anyonethere** - Tell me if any one is there
- **whosthere** - List all devices in 10 minutes
- **subscribe** - Subscribe for device connect message
- **unsubscribe** - Unsubscribe for connect message
- **listdevices** - List all devices in 48 hours
- **name** - name <mac> <name> ;Set a name to device

当然，由于 GFW 的缘故，你需要将程序上传到一个能够科学上网的服务器，否则服务端无法和 Telegram 通信

utils 中包含了一份 MAC 地址和设备制造商的对应关系，执行 ·utils/macList.py· 可以在项目根目录下获得 `anyonethere.db` 一份，请将这个文件上传到你的服务器上，只有如此生成的数据库才包含了 MAC 和制造商的对应关系

然后，你需要创建一个Telegram 机器人

## 创建机器人

添加 BotFather 为联系人

https://telegram.me/BotFather

输入

> /start
> /newbot

按照提示输入你的 BOT 的name 和 username ，BOT 创建完成，然后你会得到一个BOT 的 token

**这个 Toekn 非常重要**，不过如果你忘记了，你可以随时使用 `/token` 向 BotFather 查询

然后向 BOT 添加命令，在 BotFather 中键入:

> /setcommands

选择你的BOT， 将 以下内容粘贴进去

```
anyonethere - Tell me if any one is there
whosthere - List all devices in 10 minutes
subscribe - Subscribe for device connect message
unsubscribe - Unsubscribe for connect message
listdevices - List all devices in 48 hours
name - name <mac> <name> ;Set a name to device
```

命令配置成功

修改 `anyonethere.php`, 修改如下`<your_token_here>`部分, `bot` 三个字是保留的

```php
$config = [
	'db_filename' => 'anyonethere.db',
	'bot_api_url' => 'https://api.telegram.org/bot<your_token_here>/',
	'default_reporter' => 'LabDesktop01'
];
```

## 配置 WebHook

当你的 BOT 收到一条指令的时候，Telegram 会向你的 `anyonethere.php` 发起一个 HTTP 请求，由 `anyonethere.php` 处理后给出答复，但是，你要先告诉 Telegram 的服务器你的 `anyonethere.php` 怎样才能访问得到

**WebHook 仅需要配置一次，但是必须配置**

你可以选择使用 Python ，或者使用 curl, 请自行替换下面的`<your_toekn_here>` 和 '<your_host_here>'

##### Python

```python
import requests

url = "https://api.telegram.org/bot<your_toekn_here>/setWebhook"

payload = "{\"url\":\"https://<your_host_here>/anyonethere.php\"}"
headers = {
    'content-type': "application/json",
    'cache-control': "no-cache",
    }

response = requests.request("POST", url, data=payload, headers=headers)

print(response.text)
```

##### curl

在 bash 中键入
```bash
curl --request POST \
  --url https://api.telegram.org/bot<your_toekn_here>/setWebhook \
  --header 'cache-control: no-cache' \
  --header 'content-type: application/json' \
  --data '{"url":"https://<your_host_here>/anyonethere.php"}'
```

至此，Telegram 机器人配置成功

Enjoy !